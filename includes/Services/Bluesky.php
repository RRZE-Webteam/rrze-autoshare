<?php

namespace RRZE\Autoshare\Services;

use RRZE\Autoshare\Options\Encryption;

defined('ABSPATH') || exit;

use function RRZE\Autoshare\settings;

class Bluesky
{
    const ACCESS_JWT = 'rrze_autoshare_bluesky_access_jwt';

    const REFRESH_JWT = 'rrze_autoshare_bluesky_refresh_jwt';

    const DID = 'rrze_autoshare_bluesky_did';

    public static function init()
    {
        add_action('publish_post', [__CLASS__, 'publishPost']);
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

    public static function publishPost($postId)
    {
        wp_schedule_single_event(time(), 'rrze_autoshare_bluesky_send_post', [$postId]);
    }

    public static function sendPost($postId)
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
                                'external' => array(
                                    'uri'         => wp_get_shortlink($post->ID),
                                    'title'       => esc_html($post->post_title),
                                    'description' => esc_html(wp_trim_words(get_the_excerpt($post), 55, ' ...')),
                                ),
                            ],
                        ],
                    ]
                ),
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        } else {
            return null;
        }
    }

    public static function getConnectionStatus()
    {
        if (get_option(self::ACCESS_JWT)) {
            return __('connected', 'rrze-autoshare');
        }
        return __('not connected', 'rrze-autoshare');
    }
}
