<?php

namespace RRZE\Autoshare\Services\Mastodon;

defined('ABSPATH') || exit;

use RRZE\Autoshare\Utils;
use function RRZE\Autoshare\config;
use function RRZE\Autoshare\settings;

class Media {
    public static function getImages($post) {
        $enableFeaturedImage = has_post_thumbnail($post->ID) &&
            settings()->getOption(config()->get('services.mastodon.settings.featured_image'));
        if (!$enableFeaturedImage) {
            return [];
        }

        $imageIds = [];

        $featuredImage = get_post_thumbnail_id($post->ID);
        $imageIds[] = $featuredImage ? $featuredImage : '';

        $imageIds = array_values(array_unique($imageIds));

        $media = static::addAltText($imageIds);

        return $media;
    }

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
        $accessToken = get_option(config()->get('services.mastodon.options.access_token'));

        $endpoint = config()->get('services.mastodon.endpoints.media');
        $response = wp_remote_post(
            esc_url_raw($host . $endpoint),
            array(
                'user-agent' => config()->getUserAgent(),
                'headers'     => array(
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
                ),
                'data_format' => 'body',
                'body'        => $body,
                'timeout'     => config()->get('services.mastodon.limits.timeout'),
            )
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
            return;
        }

        $media = json_decode($response['body']);

        if (!empty($media->id)) {
            return $media->id;
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

    private static function addAltText($imageIds) {
        $images = [];

        foreach ($imageIds as $postId) {
            $alt = get_post_meta($postId, '_wp_attachment_image_alt', true);

            if ('' === $alt) {
                $alt = wp_get_attachment_caption($postId);
            }

            $images[$postId] = is_string($alt)
                ? html_entity_decode($alt, ENT_QUOTES | ENT_HTML5, get_bloginfo('charset'))
                : '';
        }

        return $images;
    }
}
