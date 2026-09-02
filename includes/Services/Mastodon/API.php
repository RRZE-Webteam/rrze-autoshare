<?php

namespace RRZE\Autoshare\Services\Mastodon;

defined('ABSPATH') || exit;

use RRZE\Autoshare\Utils;
use function RRZE\Autoshare\config;
use function RRZE\Autoshare\settings;

class API {
    public static function register() {
        $domain = settings()->getOption(config()->get('services.mastodon.settings.domain'));

        $endpoint = config()->get('services.mastodon.endpoints.apps');
        $response = wp_safe_remote_post(
            esc_url_raw($domain) . $endpoint,
            [
                'user-agent' => config()->getUserAgent(),
                'body' => [
                    'client_name'   => config()->get('services.mastodon.oauth.client_name'),
                    'redirect_uris' => add_query_arg(
                        [
                            'page' => config()->get('admin_page_slug'),
                            'tab' => 'mastodon'
                        ],
                        admin_url(
                            config()->get('admin_parent_slug')
                        )
                    ),
                    'scopes' => config()->get('services.mastodon.oauth.scope'),
                    'website' => home_url(),
                ],
            ]
        );

        if (
            is_wp_error($response) ||
            wp_remote_retrieve_response_code($response) >= 300
        ) {
            Utils::logRemoteError('Mastodon', 'register_app', $response, ['endpoint' => $endpoint]);
            delete_option(config()->get('services.mastodon.options.client_id'));
            delete_option(config()->get('services.mastodon.options.client_secret'));
            delete_option(config()->get('services.mastodon.options.access_token'));
            return;
        }

        $data = json_decode($response['body']);
        if (isset($data->client_id) && isset($data->client_secret)) {
            update_option(config()->get('services.mastodon.options.client_id'), $data->client_id);
            update_option(config()->get('services.mastodon.options.client_secret'), $data->client_secret);
        } else {
            Utils::logRemoteWarning(
                'Mastodon',
                'register_app',
                'Mastodon app registration response did not contain client credentials.',
                ['endpoint' => $endpoint]
            );
        }
    }

    public static function requestAccessToken($code) {
        $host = settings()->getOption(config()->get('services.mastodon.settings.domain'));
        $clientId = get_option(config()->get('services.mastodon.options.client_id'));
        $clientSecret = get_option(config()->get('services.mastodon.options.client_secret'));

        $endpoint = config()->get('services.mastodon.endpoints.token');
        $response = wp_safe_remote_post(
            esc_url_raw($host) . $endpoint,
            [
                'user-agent' => config()->getUserAgent(),
                'body' => [
                    'client_id'     => $clientId,
                    'client_secret' => $clientSecret,
                    'grant_type'    => 'authorization_code',
                    'code'          => $code,
                    'redirect_uri'  => add_query_arg(
                        [
                            'page' => config()->get('admin_page_slug'),
                            'tab'  => 'mastodon'
                        ],
                        admin_url(config()->get('admin_parent_slug'))
                    ),
                ],
            ]
        );

        if (
            is_wp_error($response) ||
            wp_remote_retrieve_response_code($response) >= 300
        ) {
            Utils::logRemoteError('Mastodon', 'request_access_token', $response, ['endpoint' => $endpoint]);
            return false;
        }

        $data = json_decode($response['body']);

        if (isset($data->access_token)) {
            update_option(config()->get('services.mastodon.options.access_token'), $data->access_token);
            if (!self::verifyAccessToken()) {
                return false;
            }
        } else {
            Utils::logRemoteWarning(
                'Mastodon',
                'request_access_token',
                'Mastodon access token response did not contain an access token.',
                ['endpoint' => $endpoint]
            );
            return false;
        }

        return true;
    }

    public static function revokeAccess() {
        $host = settings()->getOption(config()->get('services.mastodon.settings.domain'));
        $clientId = get_option(config()->get('services.mastodon.options.client_id'));
        $clientSecret = get_option(config()->get('services.mastodon.options.client_secret'));
        $accessToken = get_option(config()->get('services.mastodon.options.access_token'));

        if (!$host || !$accessToken || !$clientId || !$clientSecret) {
            return false;
        }

        $endpoint = config()->get('services.mastodon.endpoints.revoke');
        $response = wp_safe_remote_post(
            esc_url_raw($host) . $endpoint,
            [
                'user-agent' => config()->getUserAgent(),
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
            Utils::logRemoteError('Mastodon', 'revoke_access', $response, ['endpoint' => $endpoint]);
            return false;
        }

        delete_option(config()->get('services.mastodon.options.client_id'));
        delete_option(config()->get('services.mastodon.options.client_secret'));
        delete_option(config()->get('services.mastodon.options.access_token'));
        return true;
    }

    public static function verifyAccessToken() {
        if (!$host = settings()->getOption(config()->get('services.mastodon.settings.domain'))) {
            return false;
        }

        if (!$accessToken = get_option(config()->get('services.mastodon.options.access_token'))) {
            return false;
        }

        $endpoint = config()->get('services.mastodon.endpoints.verify_credentials');
        $response = wp_remote_get(
            esc_url_raw($host) . $endpoint,
            [
                'user-agent' => config()->getUserAgent(),
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]
        );

        if (
            is_wp_error($response) ||
            wp_remote_retrieve_response_code($response) >= 300
        ) {
            Utils::logRemoteError('Mastodon', 'verify_access_token', $response, ['endpoint' => $endpoint]);
            delete_option(config()->get('services.mastodon.options.access_token'));
            return false;
        }

        $username = settings()->getOption(config()->get('services.mastodon.settings.username'));
        $account = json_decode($response['body']);

        if (isset($account->username)) {
            if ($account->username !== $username) {
                Utils::logRemoteWarning(
                    'Mastodon',
                    'verify_access_token',
                    'Mastodon account verification returned a different username.',
                    [
                        'configured_username' => sanitize_text_field($username),
                        'received_username' => sanitize_text_field($account->username),
                    ]
                );
                delete_option(config()->get('services.mastodon.options.access_token'));
                return false;
            }
        } else {
            Utils::logRemoteWarning(
                'Mastodon',
                'verify_access_token',
                'Mastodon account verification response did not contain a username.',
                ['endpoint' => $endpoint]
            );
            delete_option(config()->get('services.mastodon.options.access_token'));
            return false;
        }

        return true;
    }

    public static function connect() {
        if (
            !settings()->getOption(config()->get('services.mastodon.settings.domain')) ||
            !settings()->getOption(config()->get('services.mastodon.settings.username'))
        ) {
            return;
        }

        $clientId = get_option(config()->get('services.mastodon.options.client_id'));
        $clientSecret = get_option(config()->get('services.mastodon.options.client_secret'));

        if (!$clientId || !$clientSecret) {
            self::register();
        } else {
            $accessToken = (bool) get_option(config()->get('services.mastodon.options.access_token'));
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

    public static function publishPost($postId) {
        $post = get_post($postId);

        $text = Post::getContent($post);
        if (empty($text)) {
            return;
        }

        $args = ['status' => $text];

        $queryString = http_build_query($args);

        $media = Media::getImages($post);

        if (!empty($media)) {
            $count = config()->get('services.mastodon.limits.media_count');
            $media = array_slice($media, 0, $count, true);

            foreach ($media as $id => $alt) {
                $mediaId = Media::uploadImage($id, $alt);

                if (!empty($mediaId)) {
                    $queryString .= '&media_ids[]=' . rawurlencode($mediaId);
                }
            }
        }

        $host = settings()->getOption(config()->get('services.mastodon.settings.domain'));
        $accessToken = get_option(config()->get('services.mastodon.options.access_token'));

        $endpoint = config()->get('services.mastodon.endpoints.statuses');
        $response = wp_remote_post(
            esc_url_raw($host . $endpoint),
            [
                'user-agent' => config()->getUserAgent(),
                'headers'     => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
                'data_format' => 'body',
                'body'        => $queryString,
                'timeout'     => config()->get('services.mastodon.limits.timeout'),
            ]
        );

        $response = self::validateResponse($response, $postId, $endpoint);

        self::updateStatusMeta($postId, $response);
    }

    private static function validateResponse($response, int $postId, string $endpoint) {
        if (!is_wp_error($response)) {
            $body = json_decode($response['body']);
        }

        if (!empty($body->id)) {
            $validatedResponse = [
                'id' => $body->id,
                'created_at' => $body->created_at ?? gmdate('c'),
            ];
        } else {
            $code = is_wp_error($response) ? '500' : wp_remote_retrieve_response_code($response);
            $message = is_wp_error($response) ? $response->get_error_message() : $body->error;
            Utils::logRemoteError(
                'Mastodon',
                'publish_post',
                $response,
                [
                    'endpoint' => $endpoint,
                    'post_id' => $postId,
                    'error_code' => sanitize_text_field($code),
                    'error_message' => sanitize_text_field($message),
                ]
            );
            $errors = [
                (object) [
                    'code' => sanitize_text_field($code),
                    'message' => sanitize_text_field($message),
                ],
            ];
            $validatedResponse = new \WP_Error(
                config()->get('services.mastodon.meta.error'),
                __('An error occurred while trying to publish.', 'rrze-autoshare'),
                $errors
            );
        }

        return $validatedResponse;
    }

    private static function updateStatusMeta($postId, $data) {
        if (!is_wp_error($data)) {
            $status = 'published';
            $response = [
                'status' => $status,
                'mastodon_id' => sanitize_text_field($data['id']),
                'created_at' => sanitize_text_field($data['created_at']),
            ];
        } elseif (is_wp_error($data)) {
            $errorMessage = $data->error_data[config()->get('services.mastodon.meta.error')][0];
            // translators: %d is the error code.
            $errorCodeText = $errorMessage->code ? sprintf(__('Error: %d. ', 'rrze-autoshare'), $errorMessage->code) : '';
            $status = 'error';
            $response = [
                'status'  => $status,
                'message' => sanitize_text_field($errorCodeText . $errorMessage->message),
            ];
        } else {
            $status = 'unknown';
            $response = [
                'status'  => $status,
                'message' => __('This post was not published on Mastodon.', 'rrze-autoshare'),
            ];
        }

        update_post_meta($postId, config()->get('services.mastodon.meta.' . $status), $response);
    }

    public static function isConnected() {
        return (bool) get_option(config()->get('services.mastodon.options.access_token'));
    }

    public static function authorizeAccessText() {
        return self::isConnected() ?
            __('Revoke Access', 'rrze-autoshare') :
            __('Authorize Access', 'rrze-autoshare');
    }

    public static function authorizeAccessDescription() {
        return self::isConnected() ?
            __('You’ve authorized Autoshare to read and write to the Mastodon timeline.', 'rrze-autoshare') :
            __('Authorize Autoshare to read and write to the Mastodon timeline.', 'rrze-autoshare');
    }

    public static function authorizeAccessUrl() {
        if (self::isConnected()) {
            return self::revokeUrl();
        } else {
            return self::authorizeUrl();
        }
    }

    private static function authorizeUrl() {
        $host = settings()->getOption(config()->get('services.mastodon.settings.domain'));
        $clientId = get_option(config()->get('services.mastodon.options.client_id'));
        $clientSecret = get_option(config()->get('services.mastodon.options.client_secret'));

        return $host . config()->get('services.mastodon.endpoints.authorize') . '?' . http_build_query(
            [
                'response_type' => 'code',
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri'  => esc_url_raw(
                    add_query_arg(
                        [
                            'page' => config()->get('admin_page_slug'),
                            'tab'  => 'mastodon'
                        ],
                        admin_url(config()->get('admin_parent_slug'))
                    )
                ),
                'scope' => config()->get('services.mastodon.oauth.scope'),
            ]
        );
    }

    private static function revokeUrl() {
        return wp_nonce_url(
            add_query_arg(
                [
                    'page' => config()->get('admin_page_slug'),
                    'tab'  => 'mastodon',
                    'action' => 'revoke'
                ],
                admin_url(config()->get('admin_parent_slug'))
            ),
            'rrze-autoshare-mastodon-revoke',
            '_wpnonce'
        );
    }
}
