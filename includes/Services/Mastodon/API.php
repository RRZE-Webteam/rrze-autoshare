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
        if (
            !settings()->getOption('mastodon_domain') ||
            !settings()->getOption('mastodon_username')
        ) {
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

    public static function publishPost($postId)
    {
        $post = get_post($postId);

        $status = '%title% %permalink%';

        $status = self::parseStatus($status, $post->ID);

        $status = wp_strip_all_tags(
            html_entity_decode($status, ENT_QUOTES | ENT_HTML5, get_bloginfo('charset'))
        );

        $permalink = esc_url_raw(get_permalink($post->ID));

        if (false === strpos($status, $permalink)) {
            if (false === strpos($status, "\n")) {
                $status .= ' ' . $permalink;
            } else {
                $status .= "\r\n\r\n" . $permalink;
            }
        }

        $args = ['status' => $status];

        $query_string = http_build_query($args);

        $media = Media::getImages($post);

        if (!empty($media)) {
            $count = 1;
            $media = array_slice($media, 0, $count, true);

            foreach ($media as $id => $alt) {
                $media_id = Media::uploadImage($id, $alt);

                if (!empty($media_id)) {
                    $query_string .= '&media_ids[]=' . rawurlencode($media_id);
                }
            }
        }

        $host = settings()->getOption('mastodon_domain');
        $accessToken = get_option(self::ACCESS_TOKEN);

        $response = wp_remote_post(
            esc_url_raw($host . '/api/v1/statuses'),
            [
                'headers'     => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
                'data_format' => 'body',
                'body'        => $query_string,
                'timeout'     => 15,
            ]
        );

        if (is_wp_error($response)) {
            return;
        }

        $status = json_decode($response['body']);

        if (!empty($status->url)) {
            delete_metadata($post->post_type, $postId, 'rrze_autoshare_mastodon_error');
            update_metadata($post->post_type, $post->ID, 'rrze_autoshare_mastodon_url', esc_url_raw($status->url));
            update_metadata($post->post_type, $postId, 'rrze_autoshare_mastodon_published', true);
        } elseif (!empty($status->error)) {
            update_metadata($post->post_type, $post->ID, 'rrze_autoshare_mastodon_error', sanitize_text_field($status->error));
        }
    }

    public static function parseStatus($status, $post_id)
    {
        $status = str_replace('%title%', get_the_title($post_id), $status);
        $status = str_replace('%tags%', Post::getTags($post_id), $status);

        $maxLength = mb_strlen(str_replace(array('%excerpt%', '%permalink%'), '', $status));
        $maxLength = max(0, 450 - $maxLength);

        $status = str_replace('%excerpt%', Post::getExcerpt($post_id, $maxLength), $status);

        $status = preg_replace('~(\r\n){2,}~', "\r\n\r\n", $status);
        $status = sanitize_textarea_field($status);

        $status = str_replace('%permalink%', esc_url_raw(get_permalink($post_id)), $status);

        return $status;
    }

    public static function isConnected()
    {
        return (bool) get_option(self::ACCESS_TOKEN);
    }

    public static function authorizeAccessText()
    {
        return self::isConnected() ? __('Revoke Access', 'rrze-autoshare') : __('Authorize Access', 'rrze-autoshare');
    }

    public static function authorizeAccessDescription()
    {
        return self::isConnected() ? __('You’ve authorized Autoshare to read and write to the Mastodon timeline.', 'rrze-autoshare') : __('Authorize Autoshare to read and write to the Mastodon timeline in order to publish.', 'rrze-autoshare');
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
}
