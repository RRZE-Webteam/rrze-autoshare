<?php

namespace RRZE\Autoshare\Services\Twitter;

defined('ABSPATH') || exit;

use RRZE\Autoshare\Utils;
use function RRZE\Autoshare\settings;

class Post
{
    public static function init()
    {
        add_action('transition_post_status', [__CLASS__, 'maybePublishOnService'], 10, 3);
        add_action('save_post', [__CLASS__, 'savePost'], 10, 2);
        add_action('rrze_autoshare_twitter_publish_post', [__CLASS__, 'publishPost']);
        add_action('rrze_autoshare_x_publish_post_directly', [__CLASS__, 'publishPostDirectly']);
        self::maybePublishPostDirectly();
    }

    public static function savePost($postId, $post)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        $supportedPostTypes = settings()->getOption('twitter_post_types');
        if (!in_array($post->post_type, $supportedPostTypes)) {
            return;
        }

        if (isset($_POST['meta'])) {
            $metaValue = isset($_POST['rrze_autoshare_twitter_enabled']);
            update_post_meta($postId, 'rrze_autoshare_twitter_enabled', $metaValue);
        }
    }

    public static function maybePublishOnService($newStatus, $oldStatus, $post)
    {
        if ('publish' !== $newStatus || 'publish' === $oldStatus) {
            return;
        }

        $supportedPostTypes = settings()->getOption('twitter_post_types');
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
        update_post_meta($postId, 'rrze_autoshare_twitter_sent', gmdate('c'));
        delete_post_meta($postId, 'rrze_autoshare_twitter_error');

        wp_schedule_single_event(time(), 'rrze_autoshare_twitter_publish_post', [$postId]);
    }

    public static function publishPost($postId)
    {
        delete_post_meta($postId, 'rrze_autoshare_twitter_sent');
        if (
            API::isConnected() &&
            self::isEnabled($postId) &&
            !self::isPublished($postId)
        ) {
            API::publishPost($postId);
        }
    }

    private static function maybePublishPostDirectly()
    {
        $postId = apply_filters('rrze_autoshare_x_publish_post_directly', 0);
        $postId = absint($postId);
        if (!$postId || !get_post($postId)) {
            return;
        }

        update_post_meta($postId, 'rrze_autoshare_twitter_sent', gmdate('c'));
        delete_post_meta($postId, 'rrze_autoshare_twitter_error');

        wp_schedule_single_event(time(), 'rrze_autoshare_x_publish_post_directly', [$postId]);
    }

    public static function publishPostDirectly($postId)
    {
        $postId = absint($postId);
        if (!$postId || !get_post($postId)) {
            return;
        }

        delete_post_meta($postId, 'rrze_autoshare_x_sent');
        if (
            API::isConnected() &&
            !self::isPublished($postId)
        ) {
            API::publishPost($postId);
        }
    }

    public static function isEnabled($postId)
    {
        return (bool) get_post_meta($postId, 'rrze_autoshare_twitter_enabled', true);
    }

    public static function isSent($postId)
    {
        return (bool) get_post_meta($postId, 'rrze_autoshare_twitter_sent', true);
    }

    public static function isPublished($postId)
    {
        return (bool) get_post_meta($postId, 'rrze_autoshare_twitter_published', true);
    }

    public static function getContent(\WP_Post $post)
    {
        $permalink = esc_url_raw(get_the_permalink($post->ID));

        $isLocalEnv = in_array(wp_get_environment_type(), ['local', 'development'], true);
        $isLocalUrl = strpos(home_url(), '.test') || strpos(home_url(), '.local') || strpos(home_url(), '.localhost');
        $isLocal = $isLocalEnv || $isLocalUrl;
        $permalinkLength = (!$isLocal) ? 23 : strlen($permalink); // 23 is the length of t.co URL.
        // 275 instead of 280 because of the space between body and URL and the ellipsis.
        $textMaxLength = 275 - $permalinkLength;

        // Don't use get_the_title() because may introduce texturized characters.
        $title = apply_filters('rrze_autoshare_x_title', $post->post_title);
        $title = sanitize_text_field($title);

        $excerpt = apply_filters('rrze_autoshare_x_excerpt', self::getExcerpt($post));
        $excerpt = sanitize_textarea_field($excerpt);

        $tags = apply_filters('rrze_autoshare_x_hashtags', self::getTags($post->ID));
        $tags = array_filter(array_map('sanitize_text_field', $tags));
        $tags = implode(' ', $tags);

        $text = $title;
        if ($tags) {
            $text .= PHP_EOL . $tags;
        }
        if ($excerpt) {
            $text .= PHP_EOL . $excerpt;
        }
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
            $textLength = mb_strlen($text);
        }

        return sprintf('%s%s %s', $text, $ellipsis, $permalink);
    }

    protected static function getExcerpt($post)
    {
        $excerpt = sanitize_textarea_field($post->post_excerpt);
        if (!empty($excerpt)) {
            $excerpt = preg_replace('~$excerptMore$~', '', $excerpt);
            $excerpt = wp_strip_all_tags($excerpt);
            $excerpt = html_entity_decode($excerpt, ENT_QUOTES | ENT_HTML5, get_bloginfo('charset'));
        }
        return $excerpt;
    }

    protected static function getTags($postId)
    {
        $hashtags = '';
        $tags = Utils::getTheTags($postId);
        if (!$tags) {
            return $hashtags;
        }

        foreach ($tags as $tag) {
            $tagName = $tag->name;

            if (preg_match('/(\s|-)+/', $tagName)) {
                $tagName = preg_replace('~(\s|-)+~', ' ', $tagName);
                $tagName = explode(' ', $tagName);
                $tagName = implode('', array_map('ucfirst', $tagName));
            }

            $hashtags .= '#' . $tagName . ' ';
        }

        return trim($hashtags);
    }
}
