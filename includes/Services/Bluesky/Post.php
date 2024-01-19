<?php

namespace RRZE\Autoshare\Services\Bluesky;

defined('ABSPATH') || exit;

use function RRZE\Autoshare\settings;

class Post
{
    public static function init()
    {
        add_action('save_post', [__CLASS__, 'saveMeta'], 11, 2);

        $supportedPostTypes = settings()->getOption('bluesky_post_types');
        foreach ($supportedPostTypes as $postType) {
            add_action("save_post_{$postType}", [__CLASS__, 'savePost'], 11, 2);
        }

        add_action('rrze_autoshare_bluesky_publish_post', [__CLASS__, 'syndicatePost']);
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

        if (!isset($_POST['rrze_autoshare_bluesky_enabled'])) {
            return;
        }

        $metaValue = (bool) $_POST['rrze_autoshare_bluesky_enabled'];

        update_metadata('post', $postId, 'rrze_autoshare_bluesky_enabled', $metaValue);
    }

    public static function savePost($postId, $post)
    {
        if (wp_is_post_revision($post) || wp_is_post_autosave($post)) {
            return;
        }

        if (post_password_required($post)) {
            return false;
        }

        $supportedPostTypes = settings()->getOption('bluesky_post_types');
        if (!in_array($post->post_type, $supportedPostTypes)) {
            return;
        }

        if (!API::isConnected()) {
            return;
        }

        $autoshare = (bool) get_metadata($post->post_type, $postId, 'rrze_autoshare_bluesky_enabled', true);
        if ($autoshare) {
            wp_schedule_single_event(time(), 'rrze_autoshare_bluesky_publish_post', [$postId]);
        }
    }

    public static function syndicatePost($postId)
    {
        API::syndicatePost($postId);
    }
}
