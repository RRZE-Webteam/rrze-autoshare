<?php

namespace RRZE\Autoshare\Services\Bluesky;

defined('ABSPATH') || exit;

use RRZE\Autoshare\Utils;
use function RRZE\Autoshare\config;
use function RRZE\Autoshare\settings;

class Post {
    public static function init() {
        add_action('transition_post_status', [__CLASS__, 'maybePublishOnService'], 10, 3);
        add_action('save_post', [__CLASS__, 'savePost'], 10, 2);
        add_action(config()->get('services.bluesky.hooks.publish_post'), [__CLASS__, 'publishPost']);
    }

    public static function savePost($postId, $post) {
        if (!settings()->isServiceActive('bluesky')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        $supportedPostTypes = settings()->getOption('bluesky_post_types');
        if (!in_array($post->post_type, $supportedPostTypes)) {
            return;
        }

        if (isset($_POST['meta'])) {
            $metaKey = config()->get('services.bluesky.meta.enabled');
            $metaValue = isset($_POST[$metaKey]);
            update_post_meta($postId, $metaKey, $metaValue);
        }
    }

    public static function maybePublishOnService($newStatus, $oldStatus, $post) {
        if (!settings()->isServiceActive('bluesky')) {
            return;
        }

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
                [__CLASS__, 'publishRestInsertedPost']
            );
        } else {
            self::publishOnService($post->ID);
        }
    }

    public static function publishRestInsertedPost($post) {
        self::publishOnService($post->ID);
    }

    private static function publishOnService($postId) {
        update_post_meta($postId, config()->get('services.bluesky.meta.sent'), gmdate('c'));
        delete_post_meta($postId, config()->get('services.bluesky.meta.error'));

        wp_schedule_single_event(time(), config()->get('services.bluesky.hooks.publish_post'), [$postId]);
    }

    public static function publishPost($postId) {
        $postId = absint($postId);
        if (!$postId || !get_post($postId)) {
            return;
        }

        delete_post_meta($postId, config()->get('services.bluesky.meta.sent'));
        if (
            settings()->isServiceActive('bluesky') &&
            API::isConnected() &&
            self::isEnabled($postId) &&
            !self::isPublished($postId)
        ) {
            API::publishPost($postId);
        }
    }

    public static function isEnabled($postId) {
        return (bool) get_post_meta($postId, config()->get('services.bluesky.meta.enabled'), true);
    }

    public static function isSent($postId) {
        return (bool) get_post_meta($postId, config()->get('services.bluesky.meta.sent'), true);
    }

    public static function isPublished($postId) {
        return (bool) get_post_meta($postId, config()->get('services.bluesky.meta.published'), true);
    }

    public static function getContent(\WP_Post $post) {
        $permalink = esc_url_raw(get_the_permalink($post->ID));
        $title = apply_filters(config()->get('services.bluesky.filters.title'), $post->post_title);
        $title = sanitize_text_field($title);
        $excerpt = apply_filters(config()->get('services.bluesky.filters.excerpt'), self::getExcerpt($post));
        $excerpt = sanitize_textarea_field($excerpt);
        $tags = apply_filters(config()->get('services.bluesky.filters.hashtags'), self::getTags($post->ID));
        $tags = array_filter(array_map('sanitize_text_field', $tags));
        $tags = !empty($tags) ? implode(' ', $tags) : '';

        $format = settings()->getOption(config()->get('services.bluesky.settings.format'));

        return Utils::formatPostContent(
            $format,
            [
                '{title}' => $title,
                '{excerpt}' => $excerpt,
                '{url}' => $permalink,
                '{tags}' => $tags,
            ],
            config()->get('services.bluesky.content.max_length')
        );
    }

    private static function getExcerpt(\WP_Post $post): string
    {
        $excerpt = sanitize_textarea_field($post->post_excerpt);
        if (!empty($excerpt)) {
            $excerpt = preg_replace('~$excerptMore$~', '', $excerpt);
            $excerpt = wp_strip_all_tags($excerpt);
            $excerpt = html_entity_decode($excerpt, ENT_QUOTES | ENT_HTML5, get_bloginfo('charset'));
        }
        return $excerpt;
    }

    protected static function getTags(int $postId): array
    {
        $hashtags = [];

        // $tags = Utils::getTheTags($postId);
        // if (!$tags) {
        //     return $hashtags;
        // }

        // foreach ($tags as $tag) {
        //     $tagName = $tag->name;

        //     if (preg_match('/(\s|-)+/', $tagName)) {
        //         $tagName = preg_replace('~(\s|-)+~', ' ', $tagName);
        //         $tagName = explode(' ', $tagName);
        //         $tagName = implode('', array_map('ucfirst', $tagName));
        //     }

        //     $hashtags[] = '#' . $tagName;
        // }

        return $hashtags;
    }
}
