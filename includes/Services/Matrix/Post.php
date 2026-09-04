<?php

namespace RRZE\Autoshare\Services\Matrix;

defined('ABSPATH') || exit;

use function RRZE\Autoshare\config;
use function RRZE\Autoshare\settings;

class Post {
    private static array $restStatusTransitions = [];

    public static function init(): void {
        add_action('transition_post_status', [__CLASS__, 'maybePublishOnService'], 10, 3);
        add_action(config()->get('services.matrix.hooks.publish_post'), [__CLASS__, 'publishPost'], 10, 2);

        foreach (config()->get('default_post_types') as $postType) {
            add_action(
                sprintf('rest_after_insert_%s', $postType),
                [__CLASS__, 'publishRestInsertedPost'],
                10,
                3
            );
        }
    }

    public static function maybePublishOnService($newStatus, $oldStatus, $post): void {
        if (!$post instanceof \WP_Post) {
            return;
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            self::$restStatusTransitions[$post->ID] = [$newStatus, $oldStatus];

            return;
        }

        self::publishForTransition($post, $newStatus, $oldStatus);
    }

    public static function publishRestInsertedPost($post, $request, $creating): void {
        $transition = self::$restStatusTransitions[$post->ID] ?? null;
        unset(self::$restStatusTransitions[$post->ID]);

        if (!$post instanceof \WP_Post || !is_array($transition)) {
            return;
        }

        self::publishForTransition($post, $transition[0], $transition[1]);
    }

    private static function publishForTransition(\WP_Post $post, string $newStatus, string $oldStatus): void {
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
