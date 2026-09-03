<?php

namespace RRZE\Autoshare\Services\Bluesky;

defined('ABSPATH') || exit;

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

        if (!in_array($post->post_type, config()->get('default_post_types'), true)) {
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

        if (!in_array($post->post_type, config()->get('default_post_types'), true)) {
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

}
