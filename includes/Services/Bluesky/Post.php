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
            add_action("save_post_{$postType}", [__CLASS__, 'saveMeta'], 11, 2);
            add_action("save_post_{$postType}", [__CLASS__, 'savePost'], 20, 2);
        }

        add_action('rrze_autoshare_bluesky_publish_post', [__CLASS__, 'publishPost']);
    }

    public static function saveMeta($postId, $post)
    {
        if (wp_is_post_revision($post) || wp_is_post_autosave($post)) {
            return;
        }

        $supportedPostTypes = settings()->getOption('bluesky_post_types');
        if (!in_array($post->post_type, $supportedPostTypes)) {
            return;
        }

        $metaValue = isset($_POST['rrze_autoshare_bluesky_enabled']) ? true : false;

        update_metadata($post->post_type, $postId, 'rrze_autoshare_bluesky_enabled', $metaValue);
    }

    public static function savePost($postId, $post)
    {
        if (wp_is_post_revision($post) || wp_is_post_autosave($post)) {
            return;
        }

        if (post_password_required($post)) {
            return;
        }

        $supportedPostTypes = settings()->getOption('bluesky_post_types');
        if (!in_array($post->post_type, $supportedPostTypes)) {
            return;
        }

        if (
            !API::isConnected() ||
            !self::isEnabled($post->post_type, $postId) ||
            self::isPublished($post->post_type, $postId)
        ) {
            return;
        }

        wp_schedule_single_event(time(), 'rrze_autoshare_bluesky_publish_post', [$postId]);
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
