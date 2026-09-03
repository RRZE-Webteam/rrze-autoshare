<?php

namespace RRZE\Autoshare\Services\Mastodon;

defined('ABSPATH') || exit;

use RRZE\Autoshare\Encryption;
use RRZE\Autoshare\Utils;
use function RRZE\Autoshare\config;
use function RRZE\Autoshare\settings;

class API {
    public static function authorize(string $accessToken): bool {
        if ($accessToken === '' || !self::storeOption('access_token', $accessToken)) {
            return false;
        }

        if (!self::verifyAccessToken(false)) {
            self::deleteOption('access_token');
            return false;
        }

        self::deleteLegacyCredentials();

        return true;
    }

    public static function revoke(): void {
        self::deleteOption('access_token');
        self::deleteLegacyCredentials();
    }

    private static function verifyAccessToken(bool $logFailure = true): bool {
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
            if ($logFailure) {
                Utils::logRemoteError('Mastodon', 'verify_access_token', $response, ['endpoint' => $endpoint]);
            }
            self::deactivateOnAuthorizationFailure($response);
            self::deleteOption('access_token');
            return false;
        }

        $account = $logFailure
            ? Utils::getJsonResponseBody($response, 'Mastodon', 'verify_access_token', ['endpoint' => $endpoint])
            : json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($account['username'])) {
            return true;
        } else {
            if ($logFailure) {
                Utils::logRemoteWarning(
                    'Mastodon',
                    'verify_access_token',
                    'Mastodon account verification response did not contain a username.',
                    ['endpoint' => $endpoint]
                );
            }
            self::deleteOption('access_token');
            return false;
        }
    }

    public static function publishPost($postId, bool $updateStatus = true): bool|array {
        if (Utils::isPublicationDeferred('mastodon')) {
            return false;
        }

        $post = get_post($postId);

        $text = Utils::getPostContent($post, 'mastodon');
        if (empty($text)) {
            return false;
        }

        $args = array_merge(['status' => $text], Utils::getServiceMetadata($post, 'mastodon'));

        $media = Utils::getImages($post, 'mastodon');
        $imageUploadFailed = false;

        if (!empty($media)) {
            $count = config()->get('services.mastodon.limits.media_count');
            $media = array_slice($media, 0, $count, true);

            foreach ($media as $id => $alt) {
                $mediaId = Media::uploadImage($id, $alt);

                if (empty($mediaId)) {
                    $imageUploadFailed = true;
                    continue;
                }

                $args['media_ids'][] = $mediaId;
            }
        }

        if (Utils::isPublicationDeferred('mastodon')) {
            return false;
        }

        if (!self::isConnected()) {
            return false;
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
                'body'        => self::buildStatusRequestBody($args),
            ],
            config()->get('services.mastodon.limits.timeout')
        );

        $response = self::validateResponse($response, $postId, $endpoint, $args);
        $imageNotTransferred = $imageUploadFailed || self::isImageMissing($response, count($media));

        if ($imageNotTransferred) {
            Utils::log(
                'warning',
                'Mastodon post was published without the selected featured image.',
                [
                    'service' => 'mastodon',
                    'post_id' => $post->ID,
                    'attachment_ids' => array_keys($media),
                ]
            );
        }

        if ($updateStatus) {
            self::updateStatusMeta($postId, $response);

            return !is_wp_error($response);
        }

        return self::getManualSendResult($response, $imageNotTransferred);
    }

    private static function validateResponse($response, int $postId, string $endpoint, array $sentPayload) {
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
                'url' => !empty($body['url']) && is_string($body['url'])
                    ? esc_url_raw($body['url'])
                    : '',
                'media_attachment_ids' => self::getMediaAttachmentIds($body),
            ];
            Utils::log(
                'info',
                'Post published on Mastodon.',
                [
                    'service' => 'mastodon',
                    'post_id' => $postId,
                    'record_id' => sanitize_text_field((string) $body['id']),
                    'record_url' => $validatedResponse['url'],
                    'sent_payload' => Utils::getLoggablePayload($sentPayload),
                ]
            );
        } else {
            $code = is_wp_error($response) ? '500' : wp_remote_retrieve_response_code($response);
            $message = is_wp_error($response)
                ? $response->get_error_message()
                : ($body['message'] ?? $body['error'] ?? wp_remote_retrieve_response_message($response));
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

    private static function getManualSendResult($response, bool $imageNotTransferred): array|false {
        if (!is_array($response)) {
            return false;
        }

        return [
            'url' => $response['url'] ?? '',
            'image_not_transferred' => $imageNotTransferred,
        ];
    }

    private static function buildStatusRequestBody(array $args): string {
        $mediaIds = $args['media_ids'] ?? [];
        unset($args['media_ids']);

        $body = http_build_query($args);
        foreach ($mediaIds as $mediaId) {
            $body .= ($body === '' ? '' : '&') . 'media_ids[]=' . rawurlencode((string) $mediaId);
        }

        return $body;
    }

    private static function isImageMissing($response, int $expectedImageCount): bool {
        if ($expectedImageCount === 0 || !is_array($response)) {
            return false;
        }

        return count($response['media_attachment_ids'] ?? []) < $expectedImageCount;
    }

    private static function getMediaAttachmentIds(array $response): array {
        $ids = [];
        $attachments = $response['media_attachments'] ?? [];
        if (!is_array($attachments)) {
            return $ids;
        }

        foreach ($attachments as $attachment) {
            if (is_array($attachment) && !empty($attachment['id'])) {
                $ids[] = sanitize_text_field((string) $attachment['id']);
            }
        }

        return $ids;
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

    public static function getAccessToken(): string|false {
        return self::getOption('access_token');
    }

    public static function applicationSettingsUrl(): string {
        $host = settings()->getOption(config()->get('services.mastodon.settings.domain'));
        if (!is_string($host) || $host === '') {
            return '';
        }

        return esc_url_raw(
            untrailingslashit($host) . config()->get('services.mastodon.authorization.application_settings_path')
        );
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

    private static function deleteLegacyCredentials(): void {
        foreach (config()->get('migrations.mastodon_legacy_service_options', []) as $option) {
            delete_option($option);
        }
    }
}
