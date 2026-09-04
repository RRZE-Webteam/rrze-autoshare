<?php

namespace RRZE\Autoshare\Services\Bluesky;

defined('ABSPATH') || exit;

use RRZE\Autoshare\Utils;
use function RRZE\Autoshare\config;
use function RRZE\Autoshare\settings;

class Post {
    private static array $restStatusTransitions = [];

    public static function init() {
        add_action('transition_post_status', [__CLASS__, 'maybePublishOnService'], 10, 3);
        add_action(config()->get('services.bluesky.hooks.publish_post'), [__CLASS__, 'publishPost']);

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

        if (settings()->shouldPublishPostToService('bluesky', $post, $newStatus, $oldStatus)) {
            self::publishOnService($post->ID);
        }
    }

    public static function publishRestInsertedPost($post, $request, $creating): void {
        $transition = self::$restStatusTransitions[$post->ID] ?? null;
        unset(self::$restStatusTransitions[$post->ID]);

        if (
            !is_array($transition)
            || !settings()->shouldPublishPostToService(
                'bluesky',
                $post,
                $transition[0],
                $transition[1]
            )
        ) {
            return;
        }

        self::publishOnService($post->ID);
    }

    private static function publishOnService($postId): void {
        $postId = absint($postId);
        if (!$postId || !get_post($postId) || self::isPublished($postId)) {
            return;
        }

        delete_post_meta($postId, Utils::getPublicationRetryMetaKey('bluesky'));
        update_post_meta($postId, config()->get('services.bluesky.meta.sent'), gmdate('c'));
        delete_post_meta($postId, config()->get('services.bluesky.meta.error'));

        self::schedulePublication($postId, time());
    }

    public static function publishPost($postId): void {
        $postId = absint($postId);
        if (!$postId || !get_post($postId)) {
            return;
        }

        if (self::isPublished($postId)) {
            self::clearPublicationState($postId);
            return;
        }

        if (!settings()->isServiceActive('bluesky') || !API::isConnected() || !self::isEnabled($postId)) {
            self::clearPublicationState($postId);
            return;
        }

        if (Utils::isPublicationDeferred('bluesky')) {
            self::scheduleRetry($postId);
            return;
        }

        if (API::publishPost($postId)) {
            self::clearPublicationState($postId);
            return;
        }

        if (Utils::isPublicationDeferred('bluesky')) {
            self::scheduleRetry($postId);
            return;
        }

        self::clearPublicationState($postId);
    }

    private static function schedulePublication(int $postId, int $timestamp): void {
        $hook = config()->get('services.bluesky.hooks.publish_post');
        if (!wp_next_scheduled($hook, [$postId])) {
            wp_schedule_single_event($timestamp, $hook, [$postId]);
        }
    }

    private static function scheduleRetry(int $postId): void {
        $retryAt = Utils::getPublicationRetryAt('bluesky');
        if (false === $retryAt) {
            self::clearPublicationState($postId);
            return;
        }

        $hook = config()->get('services.bluesky.hooks.publish_post');
        if (wp_next_scheduled($hook, [$postId])) {
            return;
        }

        $metaKey = Utils::getPublicationRetryMetaKey('bluesky');
        $attempts = absint(get_post_meta($postId, $metaKey, true));
        if ($attempts >= config()->get('publication_backoff.maximum_attempts')) {
            Utils::log(
                'error',
                'Bluesky publication retry limit reached.',
                ['service' => 'bluesky', 'post_id' => $postId, 'attempts' => $attempts]
            );
            self::clearPublicationState($postId);
            return;
        }

        update_post_meta($postId, $metaKey, $attempts + 1);
        self::schedulePublication($postId, max(time() + 1, $retryAt));
    }

    private static function clearPublicationState(int $postId): void {
        delete_post_meta($postId, config()->get('services.bluesky.meta.sent'));
        delete_post_meta($postId, Utils::getPublicationRetryMetaKey('bluesky'));
    }

    public static function isEnabled($postId) {
        return settings()->isPostAutoshareEnabled(absint($postId));
    }

    public static function isSent($postId) {
        return (bool) get_post_meta($postId, config()->get('services.bluesky.meta.sent'), true);
    }

    public static function isPublished($postId) {
        return (bool) get_post_meta($postId, config()->get('services.bluesky.meta.published'), true);
    }

}
