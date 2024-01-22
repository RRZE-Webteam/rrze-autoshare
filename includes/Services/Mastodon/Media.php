<?php

namespace RRZE\Autoshare\Services\Mastodon;

defined('ABSPATH') || exit;

use function RRZE\Autoshare\settings;

class Media
{
    public static function getImages($post)
    {
        $enableFeaturedImage = has_post_thumbnail($post->ID) &&
            settings()->getOption('mastodon_featured_image');
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
        $accessToken = get_option(API::ACCESS_TOKEN);

        $response = wp_remote_post(
            esc_url_raw($host . '/api/v1/media'),
            array(
                'headers'     => array(
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
                ),
                'data_format' => 'body',
                'body'        => $body,
                'timeout'     => 15,
            )
        );

        if (is_wp_error($response)) {
            return;
        }

        $media = json_decode($response['body']);

        if (!empty($media->id)) {
            return $media->id;
        }
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
