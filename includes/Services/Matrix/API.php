<?php

namespace RRZE\Autoshare\Services\Matrix;

defined('ABSPATH') || exit;

use RRZE\Autoshare\Encryption;
use RRZE\Autoshare\Utils;
use function RRZE\Autoshare\config;
use function RRZE\Autoshare\settings;

class API {
    public static function authorize(string $accessToken): true|\WP_Error {
        if ($accessToken === '') {
            return new \WP_Error('missing_access_token');
        }

        if (!self::storeOption('access_token', $accessToken)) {
            return new \WP_Error('token_storage_failed');
        }

        $verification = self::verifyAccessToken();
        if (true !== $verification) {
            self::revoke();

            return $verification;
        }

        return true;
    }

    public static function revoke(): void {
        self::deleteOption('access_token');
    }

    public static function isConnected(): bool {
        return false !== self::getOption('access_token');
    }

    public static function getAccessToken(): string|false {
        return self::getOption('access_token');
    }

    public static function publishPost(int $postId, string $roomId, bool $updateStatus = true): bool|array {
        if (Utils::isPublicationDeferred('matrix') || !self::isConnected()) {
            return false;
        }

        $post = get_post($postId);
        if (!$post instanceof \WP_Post) {
            return false;
        }

        $body = Utils::getPostContent($post, 'matrix');
        if ($body === '') {
            return false;
        }

        $payload = ['msgtype' => 'm.text', 'body' => $body];
        $formattedBody = Utils::getMatrixFormattedPostContent($post);
        if ($formattedBody !== '') {
            $payload['format'] = 'org.matrix.custom.html';
            $payload['formatted_body'] = $formattedBody;
        }
        $imageNotTransferred = false;
        $images = Utils::getImages($post, 'matrix');
        if (!empty($images)) {
            $images = array_slice($images, 0, config()->get('services.matrix.limits.media_count'), true);
            $attachmentId = (int) array_key_first($images);
            $image = Media::uploadImage($attachmentId);
            if (is_array($image)) {
                $payload = array_merge(
                    $payload,
                    [
                        'msgtype' => 'm.image',
                        'filename' => $image['filename'],
                        'url' => $image['url'],
                        'info' => $image['info'],
                    ]
                );
            } else {
                $imageNotTransferred = true;
                Utils::log(
                    'warning',
                    'Matrix post was published without the selected featured image.',
                    [
                        'service' => 'matrix',
                        'post_id' => $post->ID,
                        'attachment_id' => $attachmentId,
                    ]
                );
            }
        }
        $data = self::sendMessage($roomId, $payload, 'publish_post', $postId);
        if (!empty($data['event_id'])) {
            Utils::log('info', 'Post published on Matrix.', ['service' => 'matrix', 'post_id' => $postId, 'room_id' => $roomId, 'event_id' => sanitize_text_field($data['event_id']), 'sent_payload' => Utils::getLoggablePayload($payload)]);
            if ($updateStatus) {
                update_post_meta($postId, self::getRoomMetaKey('published_prefix', $roomId), sanitize_text_field($data['event_id']));
            }
            return [
                'event_id' => sanitize_text_field($data['event_id']),
                'image_not_transferred' => $imageNotTransferred,
            ];
        }

        return false;
    }

    private static function sendMessage(string $roomId, array $payload, string $operation, int $postId): array|false {
        $transactionId = wp_generate_uuid4();
        $endpoint = sprintf(
            config()->get('services.matrix.endpoints.send_message'),
            rawurlencode($roomId),
            rawurlencode($transactionId)
        );
        $response = Utils::remoteRequest(
            'PUT',
            trailingslashit(settings()->getOption(config()->get('services.matrix.settings.domain'))) . ltrim($endpoint, '/'),
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . self::getOption('access_token'),
                    'Content-Type' => 'application/json',
                ],
                'body' => wp_json_encode($payload),
            ],
            config()->get('services.matrix.limits.timeout')
        );
        $data = Utils::getJsonResponseBody(
            $response,
            'Matrix',
            $operation,
            ['post_id' => $postId, 'room_id' => $roomId, 'endpoint' => $endpoint]
        );
        if (is_array($data) && !empty($data['event_id'])) {
            return $data;
        }

        $message = is_wp_error($response) ? $response->get_error_message() : ($data['error'] ?? wp_remote_retrieve_response_message($response));
        Utils::logRemoteError(
            'Matrix',
            $operation,
            $response,
            [
                'post_id' => $postId,
                'room_id' => $roomId,
                'endpoint' => $endpoint,
                'error_message' => sanitize_text_field($message),
                'api_error' => sanitize_text_field($data['errcode'] ?? ''),
            ]
        );
        if (Utils::isAuthorizationFailure($response, config()->get('services.matrix.authentication.invalid_status_codes'))) {
            settings()->deactivateService('matrix');
        }

        return false;
    }

    private static function verifyAccessToken(): true|\WP_Error {
        $token = self::getOption('access_token');
        $host = settings()->getOption(config()->get('services.matrix.settings.domain'));
        if (false === $token) {
            return new \WP_Error('token_storage_failed');
        }
        if ($host === '') {
            return new \WP_Error('missing_homeserver_url');
        }

        $endpoint = config()->get('services.matrix.endpoints.whoami');
        $response = Utils::remoteRequest(
            'GET',
            trailingslashit($host) . ltrim($endpoint, '/'),
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ],
            config()->get('services.matrix.limits.timeout')
        );
        if (is_wp_error($response)) {
            return new \WP_Error('connection_failed');
        }

        $responseCode = wp_remote_retrieve_response_code($response);
        if (in_array($responseCode, [401, 403], true)) {
            return new \WP_Error('access_token_rejected');
        }
        if (404 === $responseCode) {
            return new \WP_Error('homeserver_url_invalid');
        }
        if ($responseCode >= 500) {
            return new \WP_Error('service_unavailable');
        }
        if ($responseCode >= 300) {
            return new \WP_Error('authorization_failed');
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (JSON_ERROR_NONE !== json_last_error() || !is_array($data) || empty($data['user_id'])) {
            return new \WP_Error('unexpected_authorization_response');
        }

        return true;
    }

    private static function getRoomMetaKey(string $prefix, string $roomId): string {
        return config()->get('services.matrix.meta.' . $prefix) . md5($roomId);
    }

    private static function getOption(string $name): string|false {
        return Encryption::getOption(config()->get('services.matrix.options.' . $name));
    }

    private static function storeOption(string $name, string $value): bool {
        return Encryption::updateOption(config()->get('services.matrix.options.' . $name), $value);
    }

    private static function deleteOption(string $name): bool {
        return delete_option(config()->get('services.matrix.options.' . $name));
    }
}
