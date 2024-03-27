<?php

namespace RRZE\Autoshare\Services\Bluesky;

defined('ABSPATH') || exit;

use function RRZE\Autoshare\settings;

class Post
{
    public static function init()
    {
        add_action('transition_post_status', [__CLASS__, 'maybePublishOnService'], 10, 3);
        add_action('save_post', [__CLASS__, 'savePost'], 10, 2);
        add_action('rrze_autoshare_bluesky_publish_post', [__CLASS__, 'publishPost']);
    }

    public static function savePost($postId, $post)
    {
        if (wp_is_post_revision($post) || wp_is_post_autosave($post)) {
            return;
        }

        $supportedPostTypes = settings()->getOption('bluesky_post_types');
        if (!in_array($post->post_type, $supportedPostTypes)) {
            return;
        }

        $metaValue = isset($_POST['rrze_autoshare_bluesky_enabled']);
        update_post_meta($postId, 'rrze_autoshare_bluesky_enabled', $metaValue);
    }

    public static function maybePublishOnService($newStatus, $oldStatus, $post)
    {
        if ('publish' !== $newStatus || 'publish' === $oldStatus) {
            return;
        }

        $supportedPostTypes = settings()->getOption('bluesky_post_types');
        if (!in_array($post->post_type, $supportedPostTypes)) {
            return;
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            add_action(
                sprintf('rest_after_insert_%s', $post->post_type),
                function ($post) {
                    self::publishOnService($post->ID);
                }
            );
        } else {
            self::publishOnService($post->ID);
        }
    }

    private static function publishOnService($postId)
    {
        update_post_meta($postId, 'rrze_autoshare_bluesky_sent', gmdate('c'));
        delete_post_meta($postId, 'rrze_autoshare_bluesky_error');

        wp_schedule_single_event(time(), 'rrze_autoshare_bluesky_publish_post', [$postId]);
    }

    public static function publishPost($postId)
    {
        delete_post_meta($postId, 'rrze_autoshare_bluesky_sent');
        if (
            API::isConnected() &&
            self::isEnabled($postId) &&
            !self::isPublished($postId)
        ) {
            API::publishPost($postId);
        }
    }

    public static function isEnabled($postId)
    {
        return (bool) get_post_meta($postId, 'rrze_autoshare_bluesky_enabled', true);
    }

    public static function isPublished($postId)
    {
        return (bool) get_post_meta($postId, 'rrze_autoshare_bluesky_published', true);
    }

    public static function getContent(\WP_Post $post)
    {
        // $permalink = esc_url_raw(wp_get_shortlink($post->ID));
        $permalink = esc_url_raw(get_the_permalink($post->ID));

        // 292 instead of 300 because of the space between body and URL and the ellipsis.
        $textMaxLength = 292 - strlen($permalink);

        // Don't use get_the_title() because may introduce texturized characters.
        $title = $post->post_title;
        $excerpt = self::getExcerpt($post);
        $text = sanitize_text_field($title) . PHP_EOL . sanitize_textarea_field($excerpt);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, get_bloginfo('charset'));
        $textLength = mb_strlen($text);
        $ellipsis = ''; // Initialize as empty. Will be set if the text is too long.

        while ($textMaxLength < $textLength) {
            // Don't use `&hellip;` because may display encoded.
            $ellipsis = ' ...';

            // If there are no spaces in the text for whatever reason, 
            // truncate regardless of where spaces fall.
            if (false === mb_strpos($text, ' ')) {
                $text = mb_substr($text, 0, $textMaxLength);
                break;
            }

            // Cut off the last word in the text until the text is short enough.
            $words = explode(' ', $text);
            array_pop($words);
            $text = implode(' ', $words);
            $textLength = strlen($text);
        }

        return sprintf('%s%s %s', $text, $ellipsis, $permalink);
    }

    private static function getExcerpt($post)
    {
        $excerpt = $post->post_excerpt;
        $excerpt = preg_replace('~$excerptMore$~', '', $excerpt);
        $excerpt = wp_strip_all_tags($excerpt);
        $excerpt = html_entity_decode($excerpt, ENT_QUOTES | ENT_HTML5, get_bloginfo('charset'));
        return $excerpt;
    }
}
