<?php

namespace RRZE\Autoshare\Services\Bluesky;

defined('ABSPATH') || exit;

use function RRZE\Autoshare\settings;

class Main
{
    public static function init()
    {
        add_action('init', [__CLASS__, 'registerPostMeta']);

        Post::init();
    }

    public static function registerPostMeta()
    {
        $supportedPostTypes = settings()->getOption('bluesky_post_types');
        foreach ($supportedPostTypes as $postType) {
            register_meta($postType, 'rrze_autoshare_bluesky_enabled', [
                'show_in_rest' => true,
                'single' => true,
                'type' => 'boolean',
                'default' => 'true',
            ]);
        }
    }

    public static function isConnected()
    {
        return API::isConnected();
    }

    public static function isPublished($postType, $postId)
    {
        return Post::isPublished($postType, $postId);
    }
}
