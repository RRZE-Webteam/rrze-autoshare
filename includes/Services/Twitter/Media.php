<?php

namespace RRZE\Autoshare\Services\Twitter;

defined('ABSPATH') || exit;

use function RRZE\Autoshare\settings;

class Media
{
    /**
     * Get the file of the featured image (if available).
     *
     * @param \WP_Post $post The post associated with the tweet.
     * @return null|int The featured image file or null if no image is available.
     */
    public static function getFeaturedImageFile($post)
    {
        $enableFeaturedImage = has_post_thumbnail($post->ID) &&
            settings()->getOption('twitter_featured_image');
        if (!$enableFeaturedImage) {
            return null;
        }

        $attachmentId = get_post_thumbnail_id($post);
        $metadata = wp_get_attachment_metadata($attachmentId);
        if (!is_array($metadata)) {
            return null;
        }

        $file = self::getLargestImage(
            get_attached_file($attachmentId),
            isset($metadata['sizes']) ? $metadata['sizes'] : []
        );
        if (!$file) {
            return null;
        }

        return $file;
    }

    /**
     * Retrieves the URL of the largest version of an attachment image.
     *
     * @param string $fullSizedFile The path to the full-sized image source file.
     * @param array  $sizes Intermediate size data from attachment meta.
     * @return string|null The image path, or null if no acceptable image found.
     */
    public static function getLargestImage($fullSizedFile, $sizes)
    {
        $fileSize = @filesize($fullSizedFile); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        $maxFileSize = 5000000; // 5 MB

        if ($fileSize && $maxFileSize >= $fileSize) {
            return $fullSizedFile;
        }

        if (empty($sizes)) {
            return null;
        }

        // Sort the image sizes in order of total width + height, descending.
        $sortSizes = function ($size1, $size2) {
            $size1Total = $size1['width'] + $size1['height'];
            $size2Total = $size2['width'] + $size2['height'];
            if ($size1Total === $size2Total) {
                return 0;
            }

            return $size1Total > $size2Total ? -1 : 1;
        };

        usort($sizes, $sortSizes);

        foreach ($sizes as $size) {
            $sizedFile = str_replace(basename($fullSizedFile), $size['file'], $fullSizedFile);
            $fileSize  = @filesize($sizedFile); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

            if ($fileSize && $maxFileSize >= $fileSize) {
                return $sizedFile;
            }
        }

        return null;
    }
}
