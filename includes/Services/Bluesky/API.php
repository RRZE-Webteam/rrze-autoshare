<?php

namespace RRZE\Autoshare\Services\Bluesky;

defined('ABSPATH') || exit;

use RRZE\Autoshare\Options\Encryption;
use function RRZE\Autoshare\plugin;
use function RRZE\Autoshare\settings;

class API
{
    const ACCESS_JWT = 'rrze_autoshare_bluesky_access_jwt';

    const REFRESH_JWT = 'rrze_autoshare_bluesky_refresh_jwt';

    const DID = 'rrze_autoshare_bluesky_did';

    public static function connect()
    {
        $host = settings()->getOption('bluesky_domain');
        $identifier = settings()->getOption('bluesky_identifier');
        $password = settings()->getOption('bluesky_password');

        if (!$host || !$identifier || !$password) {
            return false;
        }

        if (
            isset($_GET['action']) &&
            'authorize' === $_GET['action'] &&
            isset($_GET['_wpnonce']) &&
            wp_verify_nonce(sanitize_key($_GET['_wpnonce']), 'rrze-autoshare-bluesky-authorize')
        ) {
            if (!self::authorizeAccess($host, $identifier, $password)) {
                self::revokeAccess();
            }
        } elseif (
            isset($_GET['action']) &&
            'revoke' === $_GET['action'] &&
            isset($_GET['_wpnonce']) &&
            wp_verify_nonce(sanitize_key($_GET['_wpnonce']), 'rrze-autoshare-bluesky-revoke')
        ) {
            self::revokeAccess();
        } else {
            // Refresh token
            self::authorizeAccess($host, $identifier, $password);
        }
    }

    private static function authorizeAccess($host, $identifier, $password)
    {
        $host = trailingslashit($host);
        $password = Encryption::decrypt($password);
        $sessionUrl = $host . 'xrpc/com.atproto.server.createSession';
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
                        'identifier' => $identifier,
                        'password'   => $password,
                    ]
                ),
            ]
        );

        if (
            is_wp_error($response) ||
            wp_remote_retrieve_response_code($response) >= 300
        ) {
            return false;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (
            empty($data['accessJwt']) ||
            empty($data['refreshJwt']) ||
            empty($data['did'])
        ) {
            return false;
        }

        update_option(self::ACCESS_JWT, $data['accessJwt']);
        update_option(self::REFRESH_JWT, $data['refreshJwt']);
        update_option(self::DID, $data['did']);
        return true;
    }

    private static function revokeAccess()
    {
        delete_option(self::ACCESS_JWT);
        delete_option(self::REFRESH_JWT);
        delete_option(self::DID);
        return;
    }

    public static function refreshToken()
    {
        self::connect();
    }

    public static function publishPost($postId)
    {
        $post = get_post($postId);

        $accessToken = get_option(self::ACCESS_JWT);
        $host = settings()->getOption('bluesky_domain');
        $did = get_option(self::DID);

        $host = trailingslashit($host);

        $wpVersion = get_bloginfo('version');
        $pluginVersion = plugin()->getVersion();
        $userAgent = 'WordPress/' . $wpVersion . '; ' . get_bloginfo('url');

        $response = wp_safe_remote_post(
            $host . 'xrpc/com.atproto.repo.createRecord',
            [
                'user-agent' => "$userAgent; RRZE-Autoshare/$pluginVersion",
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
                update_metadata($post->post_type, $postId, 'rrze_autoshare_bluesky_published', true);
            }
        }
    }

    public static function isConnected()
    {
        return (bool) get_option(self::ACCESS_JWT);
    }

    public static function authorizeAccessText()
    {
        return self::isConnected() ? __('Revoke Access', 'rrze-autoshare') : __('Authorize Access', 'rrze-autoshare');
    }

    public static function authorizeAccessDescription()
    {
        return self::isConnected() ? __('You’ve authorized Autoshare to read and write to the Bluesky timeline.', 'rrze-autoshare') : __('Authorize Autoshare to read and write to the Bluesky timeline in order to publish.', 'rrze-autoshare');
    }

    public static function authoriteAccessUrl()
    {
        if (self::isConnected()) {
            return self::revokeUrl();
        } else {
            return self::authorizeUrl();
        }
    }

    private static function authorizeUrl()
    {
        return wp_nonce_url(
            add_query_arg(
                [
                    'page' => 'rrze_autoshare',
                    'tab'  => 'bluesky',
                    'action' => 'authorize'
                ],
                admin_url('options-general.php')
            ),
            'rrze-autoshare-bluesky-authorize',
            '_wpnonce'
        );
    }

    private static function revokeUrl()
    {
        return wp_nonce_url(
            add_query_arg(
                [
                    'page' => 'rrze_autoshare',
                    'tab'  => 'bluesky',
                    'action' => 'revoke'
                ],
                admin_url('options-general.php')
            ),
            'rrze-autoshare-bluesky-revoke',
            '_wpnonce'
        );
    }
}
