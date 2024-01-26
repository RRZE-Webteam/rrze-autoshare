<?php

namespace RRZE\Autoshare\Services\Bluesky;

defined('ABSPATH') || exit;

use function RRZE\Autoshare\settings;

class Post
{
    public static function init()
    {
        $supportedPostTypes = settings()->getOption('bluesky_post_types');
        foreach ($supportedPostTypes as $postType) {
            add_action("save_post_{$postType}", [__CLASS__, 'savePost'], 10, 2);
            add_action("rest_after_insert_{$postType}", [__CLASS__, 'restAfterInsert']);
        }

        add_action('rrze_autoshare_bluesky_publish_post', [__CLASS__, 'publishPost']);
    }

    public static function savePost($postId, $post)
    {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return;
        }

        if (wp_is_post_revision($post) || wp_is_post_autosave($post)) {
            return;
        }

        $supportedPostTypes = settings()->getOption('bluesky_post_types');
        if (!in_array($post->post_type, $supportedPostTypes)) {
            return;
        }

        $metaValue = isset($_POST['rrze_autoshare_bluesky_enabled']) ? true : false;
        update_metadata($post->post_type, $postId, 'rrze_autoshare_bluesky_enabled', $metaValue);

        self::publishOnService($post);
    }

    public static function restAfterInsert($post)
    {
        $supportedPostTypes = settings()->getOption('bluesky_post_types');
        if (!in_array($post->post_type, $supportedPostTypes)) {
            return;
        }

        self::publishOnService($post);
    }

    private static function publishOnService($post)
    {
        if (
            !API::isConnected() ||
            !self::isEnabled($post->post_type, $post->ID) ||
            self::isPublished($post->post_type, $post->ID)
        ) {
            return;
        }

        wp_schedule_single_event(time(), 'rrze_autoshare_bluesky_publish_post', [$post->ID]);
    }

    public static function publishPost($postId)
    {
        API::publishPost($postId);
    }

    public static function isEnabled($postType, $postId)
    {
        return (bool) get_metadata($postType, $postId, 'rrze_autoshare_bluesky_enabled', true);
    }

    public static function isPublished($postType, $postId)
    {
        return (bool) get_metadata($postType, $postId, 'rrze_autoshare_bluesky_published', true);
    }
}
