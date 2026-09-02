<?php

namespace RRZE\Autoshare\Services\Bluesky;

defined('ABSPATH') || exit;

use RRZE\Autoshare\Utils;
use function RRZE\Autoshare\config;
use function RRZE\Autoshare\settings;

class Main {
    public static function init() {
        add_action('init', [__CLASS__, 'migrateStoredCredentials'], 5);
        add_action('init', [__CLASS__, 'registerPostMeta']);
        add_action('init', [__CLASS__, 'initPost']);
    }

    public static function migrateStoredCredentials() {
        $migrationOption = config()->get('migrations.bluesky_credentials');

        if (get_option($migrationOption)) {
            return;
        }

        $options = get_option(config()->get('option_name'), []);
        $credentials = config()->get('migrations.bluesky_legacy_credential_settings', []);
        $hasStoredCredentials = false;

        foreach ($credentials as $credential) {
            if (is_array($options) && array_key_exists($credential, $options)) {
                unset($options[$credential]);
                $hasStoredCredentials = true;
            }
        }

        if ($hasStoredCredentials) {
            update_option(config()->get('option_name'), $options);
        }

        update_option($migrationOption, '1', false);

        if ($hasStoredCredentials) {
            Utils::log(
                'notice',
                'Stored Bluesky credentials were removed after authorization. Existing Bluesky tokens remain active.'
            );
        }
    }

    public static function initPost() {
        Post::init();
    }

    public static function registerPostMeta() {
        if (!settings()->isServiceActive('bluesky')) {
            return;
        }

        $supportedPostTypes = settings()->getOption('bluesky_post_types');
        foreach ($supportedPostTypes as $postType) {
            register_post_meta(
                $postType,
                config()->get('services.bluesky.meta.enabled'),
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
}
