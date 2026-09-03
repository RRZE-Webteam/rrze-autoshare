<?php

namespace RRZE\Autoshare\Services\Matrix;

defined('ABSPATH') || exit;

use function RRZE\Autoshare\config;
use function RRZE\Autoshare\settings;

class Post {
    public static function init(): void {
        add_action('transition_post_status', [__CLASS__, 'maybePublishOnService'], 10, 3);
        add_action(config()->get('services.matrix.hooks.publish_post'), [__CLASS__, 'publishPost'], 10, 2);
    }

    public static function maybePublishOnService($newStatus, $oldStatus, $post): void {
        if (!$post instanceof \WP_Post) {
            return;
        }

        foreach (settings()->getPublicationTargetsForPost('matrix', $post, $newStatus, $oldStatus) as $roomId) {
            wp_schedule_single_event(time(), config()->get('services.matrix.hooks.publish_post'), [$post->ID, $roomId]);
        }
    }

    public static function publishPost(int $postId, string $roomId): void {
        if (settings()->isServiceActive('matrix') && settings()->isPostAutoshareEnabled($postId)) {
            API::publishPost($postId, $roomId);
        }
    }
}
