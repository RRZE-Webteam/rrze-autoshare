<?php

namespace RRZE\Autoshare\Services\Bluesky;

defined('ABSPATH') || exit;

use RRZE\Autoshare\Config;
use RRZE\Autoshare\Cron;
use RRZE\Autoshare\Settings\Encryption;
use RRZE\Autoshare\Utils;
use function RRZE\Autoshare\config;
use function RRZE\Autoshare\settings;

class API {
    public static function connect() {
        if (self::isAuthorizationRequest()) {
            self::handleAuthorizationRequest();
            return false;
        }

        if (
            isset($_GET['action']) &&
            'revoke' === $_GET['action'] &&
            isset($_GET['_wpnonce']) &&
            wp_verify_nonce(sanitize_key(wp_unslash($_GET['_wpnonce'])), 'rrze-autoshare-bluesky-revoke')
        ) {
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('You do not have enough permissions to do that.', 'rrze-autoshare'));
            }

            self::revokeAccess();
        }
    }

    private static function isAuthorizationRequest() {
        $authorization = config()->get('services.bluesky.authorization');

        return (
            'POST' === strtoupper($_SERVER['REQUEST_METHOD'] ?? '')
            && isset($_POST[$authorization['action_field']])
        );
    }

    private static function handleAuthorizationRequest() {
        $authorization = config()->get('services.bluesky.authorization');

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have enough permissions to do that.', 'rrze-autoshare'));
        }

        $nonce = isset($_POST[$authorization['nonce_field']]) ? sanitize_text_field(wp_unslash($_POST[$authorization['nonce_field']])) : '';
        if (!wp_verify_nonce($nonce, $authorization['nonce_action'])) {
            wp_die(esc_html__('The link you followed has expired. Please try again.', 'rrze-autoshare'));
        }

        $identifier = isset($_POST[$authorization['identifier_field']]) ? sanitize_text_field(wp_unslash($_POST[$authorization['identifier_field']])) : '';
        $password = isset($_POST[$authorization['password_field']]) ? sanitize_text_field(wp_unslash($_POST[$authorization['password_field']])) : '';
        $host = trailingslashit(config()->get('services.bluesky.defaults.domain'));
        $authorized = $identifier && $password && self::authorizeAccess($host, $identifier, $password);

        if (!$authorized) {
            self::revokeAccess();
        }

        $url = add_query_arg(
            config()->get('services.bluesky.authorization.notice_field'),
            $authorized ? 'success' : 'failed',
            self::settingsUrl()
        );
        wp_safe_redirect($url);
        exit;
    }

    private static function authorizeAccess($host, $identifier, $password) {
        $host = trailingslashit($host);

        if (!Config::validateBlueskyAppPassword($password)) {
            Utils::logRemoteWarning(
                'Bluesky',
                'authorize_access',
                'The configured Bluesky App Password has an invalid format.'
            );
            return false;
        }

        $endpoint = config()->get('services.bluesky.endpoints.create_session');
        $response = wp_safe_remote_post(
            esc_url_raw($host . $endpoint),
            [
                'user-agent' => config()->getUserAgent(),
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
            Utils::logRemoteError('Bluesky', 'authorize_access', $response, ['endpoint' => $endpoint]);
            return false;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (
            empty($data['accessJwt']) ||
            empty($data['refreshJwt']) ||
            empty($data['did'])
        ) {
            Utils::logRemoteWarning(
                'Bluesky',
                'authorize_access',
                'Bluesky authorization response did not contain the expected token data.'
            );
            self::revokeAccess();
            return false;
        }

        self::storeToken('access_jwt', $data['accessJwt']);
        self::storeToken('refresh_jwt', $data['refreshJwt']);
        self::storeToken('did', $data['did']);
        return true;
    }

    private static function revokeAccess() {
        delete_option(config()->get('services.bluesky.options.access_jwt'));
        delete_option(config()->get('services.bluesky.options.refresh_jwt'));
        delete_option(config()->get('services.bluesky.options.did'));
        Cron::clearSchedule();
    }

    private static function refreshAccessToken() {
        $host = settings()->getOption(config()->get('services.bluesky.settings.domain'));
        $host = trailingslashit($host);

        if (!$accessToken = self::getRefreshToken()) {
            return false;
        }

        $endpoint = config()->get('services.bluesky.endpoints.refresh_session');
        $response = wp_safe_remote_post(
            esc_url_raw($host . $endpoint),
            [
                'user-agent' => config()->getUserAgent(),
                'headers'    => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $accessToken,
                ]
            ]
        );

        if (
            is_wp_error($response) ||
            wp_remote_retrieve_response_code($response) >= 300
        ) {
            Utils::logRemoteError('Bluesky', 'refresh_access_token', $response, ['endpoint' => $endpoint]);
            return false;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (
            empty($data['accessJwt'])
            || empty($data['refreshJwt'])
        ) {
            Utils::logRemoteWarning(
                'Bluesky',
                'refresh_access_token',
                'Bluesky token refresh response did not contain the expected token data.'
            );
            return false;
        }
        self::storeToken('access_jwt', sanitize_text_field($data['accessJwt']));
        self::storeToken('refresh_jwt', sanitize_text_field($data['refreshJwt']));

        return true;
    }

    public static function refreshToken() {
        self::refreshAccessToken();
    }

    public static function publishPost($postId) {
        self::refreshAccessToken();

        $post = get_post($postId);

        $locale = get_locale();
        $langCode = substr($locale, 0, config()->get('services.bluesky.limits.lang_code_length'));
        $title = sanitize_text_field($post->post_title);
        $text = Post::getContent($post);
        if (empty($text)) {
            return;
        }

        $record = [
            '$type'     => config()->get('services.bluesky.record.type'),
            'text'      => $text,
            'langs'     => [$langCode],
            'createdAt' => gmdate('c', strtotime($post->post_date_gmt))
        ];

        $links = self::getLinks($text);
        if (!empty($links)) {
            $record = array_merge($record, $links);
        }

        $media = Media::getImages($post);
        if (!empty($media)) {
            $count = config()->get('services.bluesky.limits.media_count');
            $media = array_slice($media, 0, $count, true);

            foreach ($media as $id => $alt) {
                $image = Media::uploadImage($id, $alt);
            }
        }
        if (!empty($image['blob'])) {
            $embed = [
                'embed' => [
                    '$type' => config()->get('services.bluesky.record.embed_images_type'),
                    'images' => [
                        [
                            'alt' => $title,
                            'image' => $image['blob']
                        ],
                    ],
                ]
            ];
            $record = array_merge($record, $embed);
        }

        $accessToken = self::getAccessToken();
        $host = settings()->getOption(config()->get('services.bluesky.settings.domain'));
        $did = self::getDid();

        $host = trailingslashit($host);

        $endpoint = config()->get('services.bluesky.endpoints.create_record');
        $response = wp_safe_remote_post(
            esc_url_raw($host . $endpoint),
            [
                'user-agent' => config()->getUserAgent(),
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
                'body' => wp_json_encode(
                    [
                        'collection' => config()->get('services.bluesky.record.collection'),
                        'did'        => esc_html($did),
                        'repo'       => esc_html($did),
                        'record'     => $record,
                    ]
                ),
            ]
        );

        $response = self::validateResponse($response, $postId, $endpoint);

        self::updateStatusMeta($postId, $response);
    }

    private static function getLinks($text) {
        $urls = self::getUrlsFromText($text);
        $links = [];
        if (!empty($urls)) {
            foreach ($urls as $url) {
                $a = [
                    "index" => [
                        "byteStart" => $url['start'],
                        "byteEnd" => $url['end'],
                    ],
                    "features" => [
                        [
                            '$type' => config()->get('services.bluesky.record.facet_link_type'),
                            'uri' => $url['url'],
                        ],
                    ],
                ];

                $links[] = $a;
            }
            $links = [
                'facets' =>
                $links,
            ];
        }

        return $links;
    }

    private static function getUrlsFromText($text) {
        $regex = '/(https?:\/\/[^\s]+)/';
        preg_match_all($regex, $text, $matches, PREG_OFFSET_CAPTURE);

        $urlData = [];

        foreach ($matches[0] as $match) {
            $url = $match[0];
            $start = $match[1];
            $end = $start + strlen($url);

            $urlData[] = [
                'start' => $start,
                'end' => $end,
                'url' => $url,
            ];
        }

        return $urlData;
    }

    private static function validateResponse($response, int $postId, string $endpoint) {
        if (!is_wp_error($response)) {
            $body = json_decode($response['body']);
        }

        if (!empty($body->uri)) {
            $validatedResponse = [
                'id' => $body->uri,
                'created_at' => $body->created_at ?? gmdate('c'),
            ];
        } else {
            $code = is_wp_error($response) ? '500' : wp_remote_retrieve_response_code($response);
            $message = is_wp_error($response) ? $response->get_error_message() : $body->error;
            Utils::logRemoteError(
                'Bluesky',
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
                config()->get('services.bluesky.meta.error'),
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
                'bluesky_id' => sanitize_text_field($data['id']),
                'created_at' => sanitize_text_field($data['created_at']),
            ];
        } elseif (is_wp_error($data)) {
            $errorMessage = $data->error_data[config()->get('services.bluesky.meta.error')][0];
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
                'message' => __('This post was not published on Bluesky.', 'rrze-autoshare'),
            ];
        }

        update_post_meta($postId, config()->get('services.bluesky.meta.' . $status), $response);
    }

    public static function isConnected() {
        return (bool) self::getAccessToken();
    }

    public static function getAccessToken() {
        return self::getToken('access_jwt');
    }

    private static function getRefreshToken() {
        return self::getToken('refresh_jwt');
    }

    private static function getDid() {
        return self::getToken('did');
    }

    private static function getToken(string $name) {
        $value = get_option(config()->get('services.bluesky.options.' . $name));

        if (!is_string($value) || $value === '') {
            return false;
        }

        return Encryption::decrypt($value);
    }

    private static function storeToken(string $name, string $value) {
        update_option(
            config()->get('services.bluesky.options.' . $name),
            Encryption::encrypt($value)
        );
    }

    public static function authorizeAccessText() {
        return self::isConnected() ?
            __('Revoke Access', 'rrze-autoshare') :
            __('Authorize Access', 'rrze-autoshare');
    }

    public static function authorizeAccessDescription() {
        return self::isConnected() ?
            __('You’ve authorized Autoshare to read and write to the Bluesky timeline.', 'rrze-autoshare') :
            __('Authorize Autoshare to read and write to the Bluesky timeline.', 'rrze-autoshare');
    }

    public static function authorizeAccessUrl() {
        if (self::isConnected()) {
            return self::revokeUrl();
        }

        return '';
    }

    private static function revokeUrl() {
        return wp_nonce_url(
            add_query_arg(
                [
                    'page' => config()->get('admin_page_slug'),
                    'tab'  => 'bluesky',
                    'action' => 'revoke'
                ],
                admin_url(config()->get('admin_parent_slug'))
            ),
            'rrze-autoshare-bluesky-revoke',
            '_wpnonce'
        );
    }

    private static function settingsUrl() {
        return add_query_arg(
            [
                'page' => config()->get('admin_page_slug'),
                'tab' => 'bluesky',
            ],
            admin_url(config()->get('admin_parent_slug'))
        );
    }
}
