<?php

namespace RRZE\Autoshare\Services\Mastodon;

defined('ABSPATH') || exit;

use function RRZE\Autoshare\settings;

class Main
{
    public static function init()
    {
        add_action('init', [__CLASS__, 'registerPostMeta']);
    }

    public static function isConnected()
    {
        return API::isConnected();
    }

    public static function isSyndicated($postType, $postId)
    {
        return (bool) get_metadata($postType, $postId, 'rrze_autoshare_mastodon_syndicated');
    }

    public static function registerPostMeta()
    {
        $supportedPostTypes = settings()->getOption('mastodon_post_types');
        foreach ($supportedPostTypes as $postType) {
            register_meta($postType, 'rrze_autoshare_mastodon_enabled', [
                'show_in_rest' => true,
                'single' => true,
                'type' => 'boolean',
                'default' => 'true',
            ]);
        }
    }
}
