<?php

namespace RRZE\Autoshare\Services\Bluesky;

defined('ABSPATH') || exit;

use RRZE\Autoshare\Config;
use RRZE\Autoshare\Cron;
use RRZE\Autoshare\Encryption;
use RRZE\Autoshare\Utils;
use function RRZE\Autoshare\config;
use function RRZE\Autoshare\settings;

class API {
    public static function authorize(string $identifier, string $password): bool {
        $host = trailingslashit(config()->get('services.bluesky.defaults.domain'));
        $authorized = self::authorizeAccess($host, $identifier, $password);

        if (!$authorized) {
            self::revokeAccess();
        }

        return $authorized;
    }

    public static function revoke() {
        self::revokeAccess();
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
        $response = Utils::remoteRequest(
            'POST',
            $host . $endpoint,
            [
                'headers'    => [
                    'Content-Type' => 'application/json',
                ],
                'body' => wp_json_encode(
                    [
                        'identifier' => $identifier,
                        'password'   => $password,
                    ]
                ),
            ],
            config()->get('services.bluesky.limits.timeout')
        );

        if (
            is_wp_error($response) ||
            wp_remote_retrieve_response_code($response) >= 300
        ) {
            Utils::logRemoteError('Bluesky', 'authorize_access', $response, ['endpoint' => $endpoint]);
            return false;
        }

        $data = Utils::getJsonResponseBody(
            $response,
            'Bluesky',
            'authorize_access',
            ['endpoint' => $endpoint]
        );

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

        if (
            !self::storeToken('access_jwt', $data['accessJwt']) ||
            !self::storeToken('refresh_jwt', $data['refreshJwt']) ||
            !self::storeToken('did', $data['did'])
        ) {
            self::revokeAccess();
            return false;
        }

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
        $response = Utils::remoteRequest(
            'POST',
            $host . $endpoint,
            [
                'headers'    => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $accessToken,
                ]
            ],
            config()->get('services.bluesky.limits.timeout')
        );

        if (
            is_wp_error($response) ||
            wp_remote_retrieve_response_code($response) >= 300
        ) {
            Utils::logRemoteError('Bluesky', 'refresh_access_token', $response, ['endpoint' => $endpoint]);
            self::deactivateOnAuthorizationFailure($response);
            return false;
        }

        $data = Utils::getJsonResponseBody(
            $response,
            'Bluesky',
            'refresh_access_token',
            ['endpoint' => $endpoint]
        );

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
        if (
            !self::storeToken('access_jwt', sanitize_text_field($data['accessJwt'])) ||
            !self::storeToken('refresh_jwt', sanitize_text_field($data['refreshJwt']))
        ) {
            return false;
        }

        return true;
    }

    public static function refreshToken() {
        self::refreshAccessToken();
    }

    public static function publishPost($postId) {
        if (Utils::isPublicationDeferred('bluesky')) {
            return;
        }

        if (!self::refreshAccessToken()) {
            return;
        }

        $post = get_post($postId);

        $locale = get_locale();
        $langCode = substr($locale, 0, config()->get('services.bluesky.limits.lang_code_length'));
        $title = sanitize_text_field($post->post_title);
        $text = Utils::getPostContent($post, 'bluesky');
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

        $media = Utils::getImages($post, 'bluesky');
        if (!empty($media)) {
            $count = config()->get('services.bluesky.limits.media_count');
            $media = array_slice($media, 0, $count, true);

            foreach ($media as $id => $alt) {
                $image = Media::uploadImage($id, $alt);
            }
        }

        if (Utils::isPublicationDeferred('bluesky')) {
            return;
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

        if (!self::isConnected()) {
            return;
        }

        $accessToken = self::getAccessToken();
        $host = settings()->getOption(config()->get('services.bluesky.settings.domain'));
        $did = self::getDid();

        $host = trailingslashit($host);

        $endpoint = config()->get('services.bluesky.endpoints.create_record');
        $response = Utils::remoteRequest(
            'POST',
            $host . $endpoint,
            [
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
            ],
            config()->get('services.bluesky.limits.timeout')
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
        $body = Utils::getJsonResponseBody(
            $response,
            'Bluesky',
            'publish_post',
            ['endpoint' => $endpoint, 'post_id' => $postId]
        );

        if (!empty($body['uri'])) {
            $validatedResponse = [
                'id' => $body['uri'],
                'created_at' => $body['created_at'] ?? gmdate('c'),
            ];
        } else {
            $code = is_wp_error($response) ? '500' : wp_remote_retrieve_response_code($response);
            $message = is_wp_error($response)
                ? $response->get_error_message()
                : ($body['error'] ?? wp_remote_retrieve_response_message($response));
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
            Utils::deferPublication(
                'bluesky',
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

    public static function deactivateOnAuthorizationFailure($response): bool {
        if (!Utils::isAuthorizationFailure(
            $response,
            config()->get('services.bluesky.authentication.invalid_status_codes', [])
        )) {
            return false;
        }

        self::revokeAccess();
        settings()->deactivateService('bluesky');

        return true;
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
        return Encryption::getOption(config()->get('services.bluesky.options.' . $name));
    }

    private static function storeToken(string $name, string $value): bool {
        return Encryption::updateOption(
            config()->get('services.bluesky.options.' . $name),
            $value
        );
    }

}
