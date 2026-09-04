<?php

namespace RRZE\Autoshare\Services\Mastodon;

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
                'Mastodon',
                'upload_image',
                'Mastodon image upload skipped because the attachment file does not exist.',
                [
                    'attachment_id' => $postId,
                ]
            );
            return;
        }

        $boundary = md5(time());
        $eol      = "\r\n";

        $body = '--' . $boundary . $eol;

        if ('' !== $alt) {
            $body .= 'Content-Disposition: form-data; name="description";' . $eol . $eol;
            $body .= $alt . $eol;
            $body .= '--' . $boundary . $eol;
        }

        $body .= 'Content-Disposition: form-data; name="file"; filename="' . basename($filePath) . '"' . $eol;
        $body .= 'Content-Type: ' . mime_content_type($filePath) . $eol . $eol;
        $body .= file_get_contents($filePath) . $eol;
        $body .= '--' . $boundary . '--';

        $host = settings()->getOption('mastodon_domain');
        $accessToken = API::getAccessToken();
        if (!is_string($host) || !Utils::isHttpsUrl($host)) {
            Utils::log(
                'warning',
                'Mastodon media upload was not sent because the service URL does not use HTTPS.',
                ['service' => 'mastodon', 'attachment_id' => $postId]
            );
            return;
        }

        $endpoint = config()->get('services.mastodon.endpoints.media');
        $response = Utils::remoteRequest(
            'POST',
            $host . $endpoint,
            array(
                'headers'     => array(
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
                ),
                'data_format' => 'body',
                'body'        => $body,
            ),
            config()->get('services.mastodon.limits.timeout')
        );

        if (is_wp_error($response)) {
            Utils::logRemoteError(
                'Mastodon',
                'upload_image',
                $response,
                [
                    'endpoint' => $endpoint,
                    'attachment_id' => $postId,
                ]
            );
            Utils::deferPublication(
                'mastodon',
                $response,
                'upload_image',
                ['endpoint' => $endpoint, 'attachment_id' => $postId]
            );
            return;
        }

        if (wp_remote_retrieve_response_code($response) >= 300) {
            Utils::logRemoteError(
                'Mastodon',
                'upload_image',
                $response,
                [
                    'endpoint' => $endpoint,
                    'attachment_id' => $postId,
                ]
            );
            Utils::deferPublication(
                'mastodon',
                $response,
                'upload_image',
                ['endpoint' => $endpoint, 'attachment_id' => $postId]
            );
            API::deactivateOnAuthorizationFailure($response);
            return;
        }

        $media = Utils::getJsonResponseBody(
            $response,
            'Mastodon',
            'upload_image',
            [
                'endpoint' => $endpoint,
                'attachment_id' => $postId,
            ]
        );

        if (!empty($media['id'])) {
            return $media['id'];
        }

        Utils::logRemoteWarning(
            'Mastodon',
            'upload_image',
            'Mastodon media upload response did not contain a media ID.',
            [
                'endpoint' => $endpoint,
                'attachment_id' => $postId,
            ]
        );
    }

}
