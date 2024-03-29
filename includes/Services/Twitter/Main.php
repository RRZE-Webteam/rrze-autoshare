<?php

namespace RRZE\Autoshare\Services\Twitter;

defined('ABSPATH') || exit;

use function RRZE\Autoshare\settings;

class Main
{
    public static function init()
    {
        add_action('init', [__CLASS__, 'registerPostMeta']);
        add_action('init', function () {
            API::connect();
            Post::init();
        });
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

    public static function registerPostMeta()
    {
        $supportedPostTypes = settings()->getOption('twitter_post_types');
        foreach ($supportedPostTypes as $postType) {
            register_post_meta(
                $postType,
                'rrze_autoshare_twitter_enabled',
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
}
