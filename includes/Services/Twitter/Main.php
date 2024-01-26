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

    public static function isPublished($postType, $postId)
    {
        return (bool) get_metadata($postType, $postId, 'rrze_autoshare_twitter_published', true);
    }

    public static function registerPostMeta()
    {
        $supportedPostTypes = settings()->getOption('twitter_post_types');
        foreach ($supportedPostTypes as $postType) {
            register_meta(
                $postType,
                'rrze_autoshare_twitter_enabled',
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
