<?php

namespace RRZE\Autoshare\Services\Bluesky;

defined('ABSPATH') || exit;

use RRZE\Autoshare\Utils;
use function RRZE\Autoshare\config;
use function RRZE\Autoshare\settings;

class Media {
    public static function getImages($post) {
        $enableFeaturedImage = has_post_thumbnail($post->ID) &&
            settings()->getOption(config()->get('services.bluesky.settings.featured_image'));
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
        $response = wp_safe_remote_post(
            esc_url_raw($host . $endpoint),
            [
                'user-agent' => config()->getUserAgent(),
                'headers'    => [
                    'Content-Type' => $mimeType,
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
                'body'        => $body,
                'timeout'     => config()->get('services.bluesky.limits.timeout'),
            ]
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
            return false;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
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
