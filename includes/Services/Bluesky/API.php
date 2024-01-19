<?php

namespace RRZE\Autoshare\Services\Bluesky;

defined('ABSPATH') || exit;

use RRZE\Autoshare\Options\Encryption;
use function RRZE\Autoshare\settings;


class API
{
    const ACCESS_JWT = 'rrze_autoshare_bluesky_access_jwt';

    const REFRESH_JWT = 'rrze_autoshare_bluesky_refresh_jwt';

    const DID = 'rrze_autoshare_bluesky_did';

    public static function init()
    {
        add_action('save_post', [__CLASS__, 'saveMeta'], 11, 2);

        $supportedPostTypes = settings()->getOption('bluesky_post_types');
        foreach ($supportedPostTypes as $postType) {
            add_action("save_post_{$postType}", [__CLASS__, 'savePost'], 11, 2);
        }

        add_action('rrze_autoshare_bluesky_publish_post', [__CLASS__, 'syndicatePost']);
    }

    public static function connect()
    {
        $blueskyIdentifier = settings()->getOption('bluesky_identifier');
        $blueskyDomain = settings()->getOption('bluesky_domain');
        $blueskyPassword = settings()->getOption('bluesky_password');

        if ($blueskyDomain && $blueskyIdentifier && $blueskyPassword) {
            $blueskyDomain = trailingslashit($blueskyDomain);
            $blueskyPassword = Encryption::decrypt($blueskyPassword);
            $sessionUrl = $blueskyDomain . 'xrpc/com.atproto.server.createSession';
            $wpVersion = get_bloginfo('version');
            $userAgent = 'WordPress/' . $wpVersion . '; ' . get_bloginfo('url');

            $response = wp_safe_remote_post(
                esc_url_raw($sessionUrl),
                [
                    'user-agent' => "$userAgent; ActivityPub",
                    'headers'    => [
                        'Content-Type' => 'application/json',
                    ],
                    'body' => wp_json_encode(
                        [
                            'identifier' => $blueskyIdentifier,
                            'password'   => $blueskyPassword,
                        ]
                    ),
                ]
            );

            if (
                is_wp_error($response) ||
                wp_remote_retrieve_response_code($response) >= 300
            ) {
                delete_option(self::ACCESS_JWT);
                delete_option(self::REFRESH_JWT);
                delete_option(self::DID);
                return;
            }

            $data = json_decode(wp_remote_retrieve_body($response), true);

            if (
                !empty($data['accessJwt'])
                && !empty($data['refreshJwt'])
                && !empty($data['did'])
            ) {
                update_option(self::ACCESS_JWT, $data['accessJwt']);
                update_option(self::REFRESH_JWT, $data['refreshJwt']);
                update_option(self::DID, $data['did']);
            } else {
                delete_option(self::ACCESS_JWT);
                delete_option(self::REFRESH_JWT);
                delete_option(self::DID);
            }
        }
    }

    public static function isConnected()
    {
        return (bool) get_option(self::ACCESS_JWT);
    }

    public static function saveMeta($postId, $post)
    {
        if (wp_is_post_revision($post) || wp_is_post_autosave($post)) {
            return;
        }

        $supportedPostTypes = settings()->getOption('bluesky_post_types');
        if (!in_array($post->post_type, $supportedPostTypes)) {
            return;
        }

        if (!isset($_POST['rrze_autoshare_bluesky_enabled'])) {
            return;
        }

        $metaValue = (bool) $_POST['rrze_autoshare_bluesky_enabled'];

        update_metadata('post', $postId, 'rrze_autoshare_bluesky_enabled', $metaValue);
    }

    public static function savePost($postId, $post)
    {
        if (wp_is_post_revision($post) || wp_is_post_autosave($post)) {
            return;
        }

        if (post_password_required($post)) {
            return false;
        }

        $supportedPostTypes = settings()->getOption('bluesky_post_types');
        if (!in_array($post->post_type, $supportedPostTypes)) {
            return;
        }

        if (!self::isConnected()) {
            return;
        }

        $autoshare = (bool) get_metadata($post->post_type, $postId, 'rrze_autoshare_bluesky_enabled', true);
        if ($autoshare) {
            wp_schedule_single_event(time(), 'rrze_autoshare_bluesky_publish_post', [$postId]);
        }
    }

    public static function syndicatePost($postId)
    {
        $post = get_post($postId);

        $accessToken = get_option(self::ACCESS_JWT);
        $blueskyDomain = settings()->getOption('bluesky_domain');
        $did = get_option(self::DID);

        $blueskyDomain = trailingslashit($blueskyDomain);

        $wpVersion = get_bloginfo('version');
        $userAgent = 'WordPress/' . $wpVersion . '; ' . get_bloginfo('url');

        $response = wp_safe_remote_post(
            $blueskyDomain . 'xrpc/com.atproto.repo.createRecord',
            [
                'user-agent' => "$userAgent; Share on Bluesky",
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
                'body' => wp_json_encode(
                    [
                        'collection' => 'app.bsky.feed.post',
                        'did'        => esc_html($did),
                        'repo'       => esc_html($did),
                        'record'     => [
                            '$type'     => 'app.bsky.feed.post',
                            'text'      => esc_html(wp_trim_words(get_the_excerpt($post), 400, ' ...')),
                            'createdAt' => gmdate('c', strtotime($post->post_date_gmt)),
                            'embed'     => [
                                '$type'    => 'app.bsky.embed.external',
                                'external' => [
                                    'uri'         => wp_get_shortlink($post->ID),
                                    'title'       => esc_html($post->post_title),
                                    'description' => esc_html(wp_trim_words(get_the_excerpt($post), 55, ' ...')),
                                ],
                            ],
                        ],
                    ]
                ),
            ]
        );

        if (is_wp_error($response)) {
            update_metadata($post->post_type, $postId, 'rrze_autoshare_bluesky_error', $response->get_error_message());
        } else {
            $code = wp_remote_retrieve_response_code($response);
            if ($code >= 300) {
                update_metadata($post->post_type, $postId, 'rrze_autoshare_bluesky_error', $code);
            } else {
                delete_metadata($post->post_type, $postId, 'rrze_autoshare_bluesky_error');
                update_metadata($post->post_type, $postId, 'rrze_autoshare_bluesky_syndicated', true);
            }
        }
    }
}
