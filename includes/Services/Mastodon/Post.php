<?php

namespace RRZE\Autoshare\Services\Mastodon;

defined('ABSPATH') || exit;

use function RRZE\Autoshare\config;
use function RRZE\Autoshare\settings;

class Post {
    private static array $restStatusTransitions = [];

    public static function init() {
        add_action('transition_post_status', [__CLASS__, 'maybePublishOnService'], 10, 3);
        add_action(config()->get('services.mastodon.hooks.publish_post'), [__CLASS__, 'publishPost']);

        foreach (config()->get('default_post_types') as $postType) {
            add_action(
                sprintf('rest_after_insert_%s', $postType),
                [__CLASS__, 'publishRestInsertedPost'],
                10,
                3
            );
        }
    }

    public static function maybePublishOnService($newStatus, $oldStatus, $post) {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            self::$restStatusTransitions[$post->ID] = [$newStatus, $oldStatus];
            return;
        }

        if (settings()->shouldPublishPostToService('mastodon', $post, $newStatus, $oldStatus)) {
            self::publishOnService($post->ID);
        }
    }

    public static function publishRestInsertedPost($post, $request, $creating): void {
        $transition = self::$restStatusTransitions[$post->ID] ?? null;
        unset(self::$restStatusTransitions[$post->ID]);

        if (
            !is_array($transition)
            || !settings()->shouldPublishPostToService(
                'mastodon',
                $post,
                $transition[0],
                $transition[1]
            )
        ) {
            return;
        }

        self::publishOnService($post->ID);
    }

    private static function publishOnService($postId) {
        update_post_meta($postId, config()->get('services.mastodon.meta.sent'), gmdate('c'));
        delete_post_meta($postId, config()->get('services.mastodon.meta.error'));

        wp_schedule_single_event(time(), config()->get('services.mastodon.hooks.publish_post'), [$postId]);
    }

    public static function publishPost($postId) {
        delete_post_meta($postId, config()->get('services.mastodon.meta.sent'));
        if (
            settings()->isServiceActive('mastodon') &&
            API::isConnected() &&
            self::isEnabled($postId) &&
            !self::isPublished($postId)
        ) {
            API::publishPost($postId);
        }
    }

    public static function isEnabled($postId) {
        return settings()->isPostAutoshareEnabled(absint($postId));
    }

    public static function isSent($postId) {
        return (bool) get_post_meta($postId, config()->get('services.mastodon.meta.sent'), true);
    }

    public static function isPublished($postId) {
        return (bool) get_post_meta($postId, config()->get('services.mastodon.meta.published'), true);
    }

}
