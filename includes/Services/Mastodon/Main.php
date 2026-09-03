<?php

namespace RRZE\Autoshare\Services\Mastodon;

defined('ABSPATH') || exit;

use function RRZE\Autoshare\config;
use function RRZE\Autoshare\settings;

class Main {
    public static function init() {
        add_action('init', [__CLASS__, 'registerPostMeta']);
        add_action('init', [__CLASS__, 'initPost']);
    }

    public static function initPost() {
        Post::init();
    }

    public static function isConnected() {
        return API::isConnected();
    }

    public static function isEnabled($postId) {
        return Post::isEnabled($postId);
    }

    public static function isSent($postId) {
        return Post::isSent($postId);
    }

    public static function isPublished($postId) {
        return Post::isPublished($postId);
    }

    public static function registerPostMeta() {
        if (!settings()->isServiceActive('mastodon')) {
            return;
        }

        foreach (config()->get('default_post_types') as $postType) {
            register_post_meta(
                $postType,
                config()->get('services.mastodon.meta.enabled'),
                [
                    'show_in_rest' => true,
                    'type' => 'boolean',
                    'single' => true,
                    'sanitize_callback' => 'rest_sanitize_boolean',
                    'auth_callback' => [__CLASS__, 'canEditPostMeta'],
                    'default' => 'false',
                ]
            );
        }
    }

    public static function canEditPostMeta(
        bool $allowed,
        string $metaKey,
        int $postId,
        int $userId
    ): bool {
        return user_can($userId, 'edit_post', $postId);
    }
}
