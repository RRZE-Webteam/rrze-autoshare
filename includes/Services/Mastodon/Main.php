<?php

namespace RRZE\Autoshare\Services\Mastodon;

defined('ABSPATH') || exit;

use function RRZE\Autoshare\settings;

class Main
{
    public static function init()
    {
        add_action('init', [__CLASS__, 'registerPostMeta']);
        add_action('init', fn () => Post::init());
    }

    public static function isConnected()
    {
        return API::isConnected();
    }

    public static function isPublished($postType, $postId)
    {
        return Post::isPublished($postType, $postId);
    }

    public static function registerPostMeta()
    {
        $supportedPostTypes = settings()->getOption('mastodon_post_types');
        foreach ($supportedPostTypes as $postType) {
            register_meta(
                $postType,
                'rrze_autoshare_mastodon_enabled',
                [
                    'show_in_rest' => true,
                    'type' => 'boolean',
                    'single' => true,
                    'default' => 'true',
                ]
            );
        }
    }
}
