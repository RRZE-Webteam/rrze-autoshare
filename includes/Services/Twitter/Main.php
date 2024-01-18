<?php

namespace RRZE\Autoshare\Services\Twitter;

defined('ABSPATH') || exit;

use function RRZE\Autoshare\settings;

class Main
{
    public static function init()
    {
        add_action('init', [__CLASS__, 'registerPostMeta']);
    }

    static public function isConnected()
    {
        return (bool) get_option('rrze_autoshare_twitter_connected');
    }

    static public function isSyndicated($postType, $postId)
    {
        return (bool) get_metadata($postType, $postId, 'rrze_autoshare_twitter_syndicated');
    }

    static public function registerPostMeta()
    {
        $supportedPostTypes = settings()->getOption('twitter_post_types');
        foreach ($supportedPostTypes as $postType) {
            register_meta($postType, 'rrze_autoshare_twitter_enabled', [
                'show_in_rest' => true,
                'single' => true,
                'type' => 'boolean',
                'default' => 'true',
            ]);
        }
    }
}
