<?php

namespace RRZE\Autoshare\Services\Bluesky;

defined('ABSPATH') || exit;

use function RRZE\Autoshare\settings;

class Main
{
    public static function init()
    {
        add_action('init', [__CLASS__, 'registerPostMeta']);
        add_action('init', fn () => Post::init());
    }

    public static function registerPostMeta()
    {
        $supportedPostTypes = settings()->getOption('bluesky_post_types');
        foreach ($supportedPostTypes as $postType) {
            register_post_meta(
                $postType,
                'rrze_autoshare_bluesky_enabled',
                [
                    'show_in_rest' => true,
                    'type' => 'boolean',
                    'single' => true,
                    'sanitize_callback' => 'rest_sanitize_boolean',
                    'auth_callback' => function () {
                        return current_user_can('edit_posts');
                    },
                    'default' => 'false',
                ]
            );
        }
    }

    public static function isConnected()
    {
        return API::isConnected();
    }

    public static function isEnabled($postId)
    {
        return Post::isEnabled($postId);
    }

    public static function isSent($postId)
    {
        return Post::isSent($postId);
    }

    public static function isPublished($postId)
    {
        return Post::isPublished($postId);
    }
}
