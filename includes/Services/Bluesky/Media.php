<?php

namespace RRZE\Autoshare\Services\Bluesky;

defined('ABSPATH') || exit;

use function RRZE\Autoshare\plugin;
use function RRZE\Autoshare\settings;

class Media
{
    public static function getImages($post)
    {
        $enableFeaturedImage = has_post_thumbnail($post->ID) &&
            settings()->getOption('bluesky_featured_image');
        if (!$enableFeaturedImage) {
            return [];
        }

        $imageIds = [];

        $featuredImage = get_post_thumbnail_id($post->ID);
        $imageIds[] = $featuredImage ? $featuredImage : '';

        $imageIds = array_values(array_unique($imageIds));

        $media = static::addAltText($post->get_post_type, $imageIds);

        return $media;
    }

    public static function uploadImage($postId, $alt = '')
    {
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
            return;
        }

        $body = file_get_contents($filePath);

        $mimeType = mime_content_type($filePath);

        $accessToken = get_option(API::ACCESS_JWT);
        $host = settings()->getOption('bluesky_domain');

        $host = trailingslashit($host);

        $wpVersion = get_bloginfo('version');
        $pluginVersion = plugin()->getVersion();
        $userAgent = 'WordPress/' . $wpVersion . '; ' . get_bloginfo('url');

        $response = wp_safe_remote_post(
            esc_url_raw($host . 'xrpc/com.atproto.repo.uploadBlob'),
            [
                'user-agent' => "$userAgent; RRZE-Autoshare/$pluginVersion",
                'headers'    => [
                    'Content-Type' => $mimeType,
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
                'body'        => $body,
                'timeout'     => 15,
            ]
        );

        if (
            is_wp_error($response) ||
            wp_remote_retrieve_response_code($response) >= 300
        ) {
            return false;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data['blob'])) {
            return false;
        }

        return $data['blob'];
    }

    private static function addAltText($postType, $imageIds)
    {
        $images = [];

        foreach ($imageIds as $postId) {
            $alt = get_metadata($postType, $postId, '_wp_attachment_image_alt', true);

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
