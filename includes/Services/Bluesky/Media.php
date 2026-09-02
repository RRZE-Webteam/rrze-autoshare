<?php

namespace RRZE\Autoshare\Services\Bluesky;

defined('ABSPATH') || exit;

use RRZE\Autoshare\Utils;
use function RRZE\Autoshare\config;
use function RRZE\Autoshare\settings;

class Media {
    public static function uploadImage($postId, $alt = '') {
        if (wp_attachment_is_image($postId)) {
            $image = wp_get_attachment_image_src($postId, 'large');
        }

        $uploads = wp_upload_dir();

        if (!empty($image[0]) && 0 === strpos($image[0], $uploads['baseurl'])) {
            $url = $image[0];
        } else {
            $url = wp_get_attachment_url($postId);
        }

        $filePath = str_replace($uploads['baseurl'], $uploads['basedir'], $url);

        if (!is_file($filePath)) {
            Utils::logRemoteWarning(
                'Bluesky',
                'upload_image',
                'Bluesky image upload skipped because the attachment file does not exist.',
                [
                    'attachment_id' => $postId,
                ]
            );
            return;
        }

        $body = file_get_contents($filePath);

        $mimeType = mime_content_type($filePath);

        $accessToken = API::getAccessToken();
        $host = settings()->getOption(config()->get('services.bluesky.settings.domain'));

        $host = trailingslashit($host);

        $endpoint = config()->get('services.bluesky.endpoints.upload_blob');
        $response = Utils::remoteRequest(
            'POST',
            $host . $endpoint,
            [
                'headers'    => [
                    'Content-Type' => $mimeType,
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
                'body'        => $body,
            ],
            config()->get('services.bluesky.limits.timeout')
        );

        if (
            is_wp_error($response) ||
            wp_remote_retrieve_response_code($response) >= 300
        ) {
            Utils::logRemoteError(
                'Bluesky',
                'upload_image',
                $response,
                [
                    'endpoint' => $endpoint,
                    'attachment_id' => $postId,
                ]
            );
            Utils::deferPublication(
                'bluesky',
                $response,
                'upload_image',
                ['endpoint' => $endpoint, 'attachment_id' => $postId]
            );
            API::deactivateOnAuthorizationFailure($response);
            return false;
        }

        return Utils::getJsonResponseBody(
            $response,
            'Bluesky',
            'upload_image',
            [
                'endpoint' => $endpoint,
                'attachment_id' => $postId,
            ]
        );
    }

}
