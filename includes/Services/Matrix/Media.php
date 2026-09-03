<?php

namespace RRZE\Autoshare\Services\Matrix;

defined('ABSPATH') || exit;

use RRZE\Autoshare\Utils;
use function RRZE\Autoshare\config;
use function RRZE\Autoshare\settings;

class Media {
    public static function uploadImage(int $attachmentId): array|false {
        if (!wp_attachment_is_image($attachmentId)) {
            return false;
        }

        $filePath = get_attached_file($attachmentId);
        if (!is_string($filePath) || !is_file($filePath) || !is_readable($filePath)) {
            Utils::logRemoteWarning(
                'Matrix',
                'upload_image',
                'Matrix image upload skipped because the attachment file does not exist.',
                ['attachment_id' => $attachmentId]
            );

            return false;
        }

        $image = getimagesize($filePath);
        $mimeType = is_array($image) && !empty($image['mime'])
            ? sanitize_mime_type($image['mime'])
            : sanitize_mime_type((string) get_post_mime_type($attachmentId));
        $contents = file_get_contents($filePath);
        if ($mimeType === '' || false === $contents) {
            return false;
        }

        $filename = sanitize_file_name(basename($filePath));
        $endpoint = config()->get('services.matrix.endpoints.upload_media');
        $response = Utils::remoteRequest(
            'POST',
            trailingslashit(settings()->getOption(config()->get('services.matrix.settings.domain')))
                . ltrim($endpoint, '/')
                . '?filename=' . rawurlencode($filename),
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . API::getAccessToken(),
                    'Content-Type' => $mimeType,
                ],
                'body' => $contents,
            ],
            config()->get('services.matrix.limits.timeout')
        );
        $data = Utils::getJsonResponseBody(
            $response,
            'Matrix',
            'upload_image',
            [
                'endpoint' => $endpoint,
                'attachment_id' => $attachmentId,
            ]
        );
        if (!is_array($data) || empty($data['content_uri']) || !is_string($data['content_uri'])) {
            Utils::logRemoteError(
                'Matrix',
                'upload_image',
                $response,
                [
                    'endpoint' => $endpoint,
                    'attachment_id' => $attachmentId,
                ]
            );

            return false;
        }

        $info = [
            'mimetype' => $mimeType,
            'size' => filesize($filePath),
        ];
        if (is_array($image)) {
            $info['w'] = absint($image[0] ?? 0);
            $info['h'] = absint($image[1] ?? 0);
        }

        return [
            'filename' => $filename,
            'url' => sanitize_text_field($data['content_uri']),
            'info' => array_filter($info),
        ];
    }
}
