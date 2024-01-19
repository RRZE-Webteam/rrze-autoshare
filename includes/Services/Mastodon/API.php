<?php

namespace RRZE\Autoshare\Services\Mastodon;

defined('ABSPATH') || exit;

use function RRZE\Autoshare\settings;

class API
{
    const CLIENT_ID = 'rrze_autoshare_mastodon_client_id';

    const CLIENT_SECRET = 'rrze_autoshare_mastodon_client_secret';

    const ACCESS_TOKEN = 'rrze_autoshare_mastodon_access_token';

    public static function register()
    {
        $domain = settings()->getOption('mastodon_domain');
        $clientName = "RRZE-Autoshare";

        $response = wp_safe_remote_post(
            esc_url_raw($domain) . '/api/v1/apps',
            [
                'body' => [
                    'client_name'   => $clientName,
                    'redirect_uris' => add_query_arg(
                        [
                            'page' => 'rrze_autoshare',
                            'tab' => 'mastodon'
                        ],
                        admin_url(
                            'options-general.php'
                        )
                    ),
                    'scopes' => 'write:media write:statuses read:accounts read:statuses',
                    'website' => home_url(),
                ],
            ]
        );

        if (
            is_wp_error($response) ||
            wp_remote_retrieve_response_code($response) >= 300
        ) {
            delete_option(self::CLIENT_ID);
            delete_option(self::CLIENT_SECRET);
            delete_option(self::ACCESS_TOKEN);
            return;
        }

        $data = json_decode($response['body']);
        if (isset($data->client_id) && isset($data->client_secret)) {
            update_option(self::CLIENT_ID, $data->client_id);
            update_option(self::CLIENT_SECRET, $data->client_secret);
        }
    }

    public static function requestAccessToken($code)
    {
        $host = settings()->getOption('mastodon_domain');
        $clientId = get_option(self::CLIENT_ID);
        $clientSecret = get_option(self::CLIENT_SECRET);

        $response = wp_safe_remote_post(
            esc_url_raw($host) . '/oauth/token',
            [
                'body' => [
                    'client_id'     => $clientId,
                    'client_secret' => $clientSecret,
                    'grant_type'    => 'authorization_code',
                    'code'          => $code,
                    'redirect_uri'  => add_query_arg(
                        [
                            'page' => 'rrze_autoshare',
                            'tab'  => 'mastodon'
                        ],
                        admin_url('options-general.php')
                    ),
                ],
            ]
        );

        if (
            is_wp_error($response) ||
            wp_remote_retrieve_response_code($response) >= 300
        ) {
            return false;
        }

        $data = json_decode($response['body']);

        if (isset($data->access_token)) {
            update_option(self::ACCESS_TOKEN, $data->access_token);
            if (!self::verifyAccessToken()) {
                return false;
            }
        }

        return true;
    }

    public static function revokeAccess()
    {
        $host = settings()->getOption('mastodon_domain');
        $accessToken = get_option(self::ACCESS_TOKEN);
        $clientId = get_option(self::CLIENT_ID);
        $clientSecret = get_option(self::CLIENT_SECRET);

        if (!$host || !$accessToken || !$clientId || !$clientSecret) {
            return false;
        }

        $response = wp_safe_remote_post(
            esc_url_raw($host) . '/oauth/revoke',
            [
                'body' => [
                    'client_id'     => $clientId,
                    'client_secret' => $clientSecret,
                    'token'         => $accessToken,
                ],
            ]
        );

        if (
            is_wp_error($response) ||
            wp_remote_retrieve_response_code($response) >= 300
        ) {
            return false;
        }

        delete_option(self::ACCESS_TOKEN);
        return true;
    }

    public static function verifyAccessToken()
    {
        if (!$host = settings()->getOption('mastodon_domain')) {
            return false;
        }

        if (!$accessToken = settings()->getOption('mastodon_access_token')) {
            return false;
        }

        $response = wp_remote_get(
            esc_url_raw($host) . '/api/v1/accounts/verify_credentials',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]
        );

        if (
            is_wp_error($response) ||
            wp_remote_retrieve_response_code($response) >= 300
        ) {
            delete_option(self::ACCESS_TOKEN);
            return false;
        }

        $username = settings()->getOption('mastodon_username');
        $account = json_decode($response['body']);

        if (isset($account->username)) {
            if ($account->username !== $username) {
                delete_option(self::ACCESS_TOKEN);
                return false;
            }
        }

        return true;
    }

    public static function connect()
    {
        if (!settings()->getOption('mastodon_domain')) {
            return;
        }

        $clientId = get_option(API::CLIENT_ID);
        $clientSecret = get_option(API::CLIENT_SECRET);

        if (!$clientId || !$clientSecret) {
            self::register();
        } else {
            $accessToken = (bool) get_option(API::ACCESS_TOKEN);
            if (!empty($_GET['code']) && !$accessToken) {
                self::requestAccessToken(wp_unslash($_GET['code']));
            } elseif (
                isset($_GET['action']) &&
                'revoke' === $_GET['action'] &&
                isset($_GET['_wpnonce']) &&
                wp_verify_nonce(sanitize_key($_GET['_wpnonce']), 'rrze-autoshare-mastodon-revoke')
            ) {
                self::revokeAccess();
            }
        }
    }

    public static function isConnected()
    {
        return (bool) get_option(self::ACCESS_TOKEN);
    }

    public static function authorizeAccessText()
    {
        return get_option(self::ACCESS_TOKEN) ? __('Revoke Access', 'rrze-autoshare') : __('Authorize Access', 'rrze-autoshare');
    }

    public static function authorizeAccessDescription()
    {
        return get_option(self::ACCESS_TOKEN) ? __('You’ve authorized Autoshare to read and write to the Mastodon timeline.', 'rrze-autoshare') : __('Authorize Autoshare to read and write to the Mastodon timeline in order to enable syndication.', 'rrze-autoshare');
    }

    public static function authoriteAccessUrl()
    {
        if (!get_option(self::ACCESS_TOKEN)) {
            return self::authorizeUrl();
        } else {
            return self::revokeUrl();
        }
    }

    private static function authorizeUrl()
    {
        $host = settings()->getOption('mastodon_domain');
        $clientId = get_option(API::CLIENT_ID);
        $clientSecret = get_option(API::CLIENT_SECRET);

        return $host . '/oauth/authorize?' . http_build_query(
            [
                'response_type' => 'code',
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri'  => esc_url_raw(
                    add_query_arg(
                        [
                            'page' => 'rrze_autoshare',
                            'tab'  => 'mastodon'
                        ],
                        admin_url('options-general.php')
                    )
                ),
                'scope' => 'write:media write:statuses read:accounts read:statuses',
            ]
        );
    }

    private static function revokeUrl()
    {
        return wp_nonce_url(
            add_query_arg(
                [
                    'page' => 'rrze_autoshare',
                    'tab'  => 'mastodon',
                    'action' => 'revoke'
                ],
                admin_url('options-general.php')
            ),
            'rrze-autoshare-mastodon-revoke',
            '_wpnonce'
        );
    }

    public static function getConnectionStatus()
    {
        if (get_option(API::ACCESS_TOKEN)) {
            return __('Connected', 'rrze-autoshare');
        } elseif (get_option(API::CLIENT_ID)) {
            return __('Registered', 'rrze-autoshare');
        }
        return __('Not connected', 'rrze-autoshare');
    }
}
