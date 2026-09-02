<?php

namespace RRZE\Autoshare\Services\Mastodon;

defined('ABSPATH') || exit;

use RRZE\Autoshare\Encryption;
use RRZE\Autoshare\Utils;
use function RRZE\Autoshare\config;
use function RRZE\Autoshare\settings;

class API {
    public static function register() {
        $domain = settings()->getOption(config()->get('services.mastodon.settings.domain'));

        $endpoint = config()->get('services.mastodon.endpoints.apps');
        $response = Utils::remoteRequest(
            'POST',
            $domain . $endpoint,
            [
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
            ],
            config()->get('services.mastodon.limits.timeout')
        );

        if (
            is_wp_error($response) ||
            wp_remote_retrieve_response_code($response) >= 300
        ) {
            Utils::logRemoteError('Mastodon', 'register_app', $response, ['endpoint' => $endpoint]);
            self::deleteCredentials();
            return;
        }

        $data = Utils::getJsonResponseBody($response, 'Mastodon', 'register_app', ['endpoint' => $endpoint]);
        if (!empty($data['client_id']) && !empty($data['client_secret'])) {
            if (
                !self::storeOption('client_id', $data['client_id']) ||
                !self::storeOption('client_secret', $data['client_secret'])
            ) {
                self::deleteCredentials();
            }
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
        $clientId = self::getOption('client_id');
        $clientSecret = self::getOption('client_secret');

        $endpoint = config()->get('services.mastodon.endpoints.token');
        $response = Utils::remoteRequest(
            'POST',
            $host . $endpoint,
            [
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
            ],
            config()->get('services.mastodon.limits.timeout')
        );

        if (
            is_wp_error($response) ||
            wp_remote_retrieve_response_code($response) >= 300
        ) {
            Utils::logRemoteError('Mastodon', 'request_access_token', $response, ['endpoint' => $endpoint]);
            return false;
        }

        $data = Utils::getJsonResponseBody(
            $response,
            'Mastodon',
            'request_access_token',
            ['endpoint' => $endpoint]
        );

        if (!empty($data['access_token'])) {
            if (!self::storeOption('access_token', $data['access_token'])) {
                return false;
            }
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
        $clientId = self::getOption('client_id');
        $clientSecret = self::getOption('client_secret');
        $accessToken = self::getOption('access_token');

        if (!$host || !$accessToken || !$clientId || !$clientSecret) {
            return false;
        }

        $endpoint = config()->get('services.mastodon.endpoints.revoke');
        $response = Utils::remoteRequest(
            'POST',
            $host . $endpoint,
            [
                'body' => [
                    'client_id'     => $clientId,
                    'client_secret' => $clientSecret,
                    'token'         => $accessToken,
                ],
            ],
            config()->get('services.mastodon.limits.timeout')
        );

        if (
            is_wp_error($response) ||
            wp_remote_retrieve_response_code($response) >= 300
        ) {
            Utils::logRemoteError('Mastodon', 'revoke_access', $response, ['endpoint' => $endpoint]);
            return false;
        }

        self::deleteCredentials();
        return true;
    }

    public static function verifyAccessToken() {
        if (!$host = settings()->getOption(config()->get('services.mastodon.settings.domain'))) {
            return false;
        }

        if (!$accessToken = self::getOption('access_token')) {
            return false;
        }

        $endpoint = config()->get('services.mastodon.endpoints.verify_credentials');
        $response = Utils::remoteRequest(
            'GET',
            $host . $endpoint,
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ],
            config()->get('services.mastodon.limits.timeout')
        );

        if (
            is_wp_error($response) ||
            wp_remote_retrieve_response_code($response) >= 300
        ) {
            Utils::logRemoteError('Mastodon', 'verify_access_token', $response, ['endpoint' => $endpoint]);
            self::deactivateOnAuthorizationFailure($response);
            self::deleteOption('access_token');
            return false;
        }

        $username = settings()->getOption(config()->get('services.mastodon.settings.username'));
        $account = Utils::getJsonResponseBody(
            $response,
            'Mastodon',
            'verify_access_token',
            ['endpoint' => $endpoint]
        );

        if (!empty($account['username'])) {
            if ($account['username'] !== $username) {
                Utils::logRemoteWarning(
                    'Mastodon',
                    'verify_access_token',
                    'Mastodon account verification returned a different username.',
                    [
                        'configured_username' => sanitize_text_field($username),
                        'received_username' => sanitize_text_field($account['username']),
                    ]
                );
                self::deleteOption('access_token');
                return false;
            }
        } else {
            Utils::logRemoteWarning(
                'Mastodon',
                'verify_access_token',
                'Mastodon account verification response did not contain a username.',
                ['endpoint' => $endpoint]
            );
            self::deleteOption('access_token');
            return false;
        }

        return true;
    }

    public static function connect() {
        if (!current_user_can('manage_options')) {
            return false;
        }

        if (
            !settings()->getOption(config()->get('services.mastodon.settings.domain')) ||
            !settings()->getOption(config()->get('services.mastodon.settings.username'))
        ) {
            return false;
        }

        $clientId = self::getOption('client_id');
        $clientSecret = self::getOption('client_secret');

        if (!$clientId || !$clientSecret) {
            self::register();
        } else {
            $accessToken = false !== self::getOption('access_token');
            $code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
            $state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';

            if ($code !== '' && !$accessToken) {
                if (!self::verifyAuthorizationState($state)) {
                    Utils::logRemoteWarning(
                        'Mastodon',
                        'request_access_token',
                        'Mastodon OAuth callback was rejected because its state is invalid.',
                        ['user_id' => get_current_user_id()]
                    );
                    return false;
                }

                return self::requestAccessToken($code);
            } elseif (
                isset($_GET['action']) &&
                'revoke' === sanitize_key(wp_unslash($_GET['action'])) &&
                isset($_GET['_wpnonce']) &&
                wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'rrze-autoshare-mastodon-revoke')
            ) {
                return self::revokeAccess();
            }
        }

        return true;
    }

    public static function publishPost($postId) {
        if (Utils::isPublicationDeferred('mastodon')) {
            return;
        }

        $post = get_post($postId);

        $text = Utils::getPostContent($post, 'mastodon');
        if (empty($text)) {
            return;
        }

        $args = ['status' => $text];

        $queryString = http_build_query($args);

        $media = Utils::getImages($post, 'mastodon');

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

        if (Utils::isPublicationDeferred('mastodon')) {
            return;
        }

        if (!self::isConnected()) {
            return;
        }

        $host = settings()->getOption(config()->get('services.mastodon.settings.domain'));
        $accessToken = self::getOption('access_token');

        $endpoint = config()->get('services.mastodon.endpoints.statuses');
        $response = Utils::remoteRequest(
            'POST',
            $host . $endpoint,
            [
                'headers'     => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
                'data_format' => 'body',
                'body'        => $queryString,
            ],
            config()->get('services.mastodon.limits.timeout')
        );

        $response = self::validateResponse($response, $postId, $endpoint);

        self::updateStatusMeta($postId, $response);
    }

    private static function validateResponse($response, int $postId, string $endpoint) {
        $body = Utils::getJsonResponseBody(
            $response,
            'Mastodon',
            'publish_post',
            ['endpoint' => $endpoint, 'post_id' => $postId]
        );

        if (!empty($body['id'])) {
            $validatedResponse = [
                'id' => $body['id'],
                'created_at' => $body['created_at'] ?? gmdate('c'),
            ];
        } else {
            $code = is_wp_error($response) ? '500' : wp_remote_retrieve_response_code($response);
            $message = is_wp_error($response)
                ? $response->get_error_message()
                : ($body['error'] ?? wp_remote_retrieve_response_message($response));
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
            Utils::deferPublication(
                'mastodon',
                $response,
                'publish_post',
                ['endpoint' => $endpoint, 'post_id' => $postId]
            );
            self::deactivateOnAuthorizationFailure($response);
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
        return false !== self::getOption('access_token');
    }

    public static function deactivateOnAuthorizationFailure($response): bool {
        if (!Utils::isAuthorizationFailure(
            $response,
            config()->get('services.mastodon.authentication.invalid_status_codes', [])
        )) {
            return false;
        }

        self::deleteOption('access_token');
        settings()->deactivateService('mastodon');

        return true;
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
        $clientId = self::getOption('client_id');

        return $host . config()->get('services.mastodon.endpoints.authorize') . '?' . http_build_query(
            [
                'response_type' => 'code',
                'client_id'     => $clientId,
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
                'state' => self::createAuthorizationState(),
            ]
        );
    }

    private static function createAuthorizationState(): string {
        $state = wp_generate_password(64, false, false);
        set_transient(
            self::getAuthorizationStateKey(),
            wp_hash($state),
            config()->get('services.mastodon.oauth.state_lifetime')
        );

        return $state;
    }

    private static function verifyAuthorizationState(string $state): bool {
        $key = self::getAuthorizationStateKey();
        $storedState = get_transient($key);
        delete_transient($key);

        return is_string($storedState) && $state !== '' && hash_equals($storedState, wp_hash($state));
    }

    private static function getAuthorizationStateKey(): string {
        return config()->get('services.mastodon.oauth.state_transient_prefix') . get_current_user_id();
    }

    public static function getAccessToken(): string|false {
        return self::getOption('access_token');
    }

    private static function getOption(string $name): string|false {
        return Encryption::getOption(config()->get('services.mastodon.options.' . $name));
    }

    private static function storeOption(string $name, string $value): bool {
        return Encryption::updateOption(config()->get('services.mastodon.options.' . $name), $value);
    }

    private static function deleteOption(string $name): bool {
        return delete_option(config()->get('services.mastodon.options.' . $name));
    }

    private static function deleteCredentials(): void {
        foreach (array_keys(config()->get('services.mastodon.options')) as $name) {
            self::deleteOption($name);
        }
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
