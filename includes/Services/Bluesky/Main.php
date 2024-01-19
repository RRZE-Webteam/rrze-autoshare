<?php

namespace RRZE\Autoshare\Services\Bluesky;

defined('ABSPATH') || exit;

use function RRZE\Autoshare\settings;

class Main
{
    public static function init()
    {
        add_action('init', [__CLASS__, 'registerPostMeta']);

        API::init();
    }

    static public function registerPostMeta()
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

    static public function isConnected()
    {
        return API::isConnected();
    }

    static public function isSyndicated($postType, $postId)
    {
        return (bool) get_metadata($postType, $postId, 'rrze_autoshare_bluesky_syndicated');
    }
}
