<?php

namespace RRZE\Autoshare\Services\Mastodon;

defined('ABSPATH') || exit;

use function RRZE\Autoshare\settings;

class Post
{
    public static function init()
    {
        $supportedPostTypes = settings()->getOption('mastodon_post_types');
        foreach ($supportedPostTypes as $postType) {
            add_action("save_post_{$postType}", [__CLASS__, 'savePost'], 10, 2);
            add_action("rest_after_insert_{$postType}", [__CLASS__, 'restAfterInsert']);
        }

        add_action('rrze_autoshare_mastodon_publish_post', [__CLASS__, 'publishPost'], 10, 2);
    }

    public static function savePost($postId, $post)
    {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return;
        }

        if (wp_is_post_revision($post) || wp_is_post_autosave($post)) {
            return;
        }

        $supportedPostTypes = settings()->getOption('mastodon_post_types');
        if (!in_array($post->post_type, $supportedPostTypes)) {
            return;
        }

        $metaValue = isset($_POST['rrze_autoshare_mastodon_enabled']) ? true : false;
        update_metadata($post->post_type, $postId, 'rrze_autoshare_mastodon_enabled', $metaValue);

        self::publishOnService($post);
    }

    public static function restAfterInsert($post)
    {
        $supportedPostTypes = settings()->getOption('mastodon_post_types');
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

        update_metadata($post->post_type, $post->ID, 'rrze_autoshare_twitter_sent', gmdate('c'));
        wp_schedule_single_event(time(), 'rrze_autoshare_mastodon_publish_post', [$post->post_type, $post->ID]);
    }

    public static function publishPost($postType, $postId)
    {
        delete_metadata($postType, $postId, 'rrze_autoshare_twitter_sent');
        API::publishPost($postId);
    }

    public static function isEnabled($postType, $postId)
    {
        return (bool) get_metadata($postType, $postId, 'rrze_autoshare_mastodon_enabled', true);
    }

    public static function isPublished($postType, $postId)
    {
        return (bool) get_metadata($postType, $postId, 'rrze_autoshare_mastodon_published', true);
    }

    public static function getExcerpt($postId, $maxLength = 125)
    {
        if (0 === $maxLength) {
            return '';
        }

        $excerptMore = apply_filters('excerpt_more', ' [&hellip;]');

        $orig = apply_filters('the_excerpt', get_the_excerpt($postId));

        $excerpt = preg_replace("~$excerptMore$~", '', $orig);

        $excerpt = wp_strip_all_tags($orig);
        $excerpt = html_entity_decode($orig, ENT_QUOTES | ENT_HTML5, get_bloginfo('charset'));

        $shortened = mb_substr($excerpt, 0, $maxLength);
        $shortened = trim($shortened);

        if ($shortened === $excerpt) {
            return $orig;
        } elseif (ctype_punct(mb_substr($shortened, -1))) {
            $shortened .= ' …';
        } else {
            $shortened .= '…';
        }

        return $shortened;
    }

    public static function getTags($postId)
    {
        $hashtags = '';
        $tags = get_the_tags($postId);

        if ($tags && !is_wp_error($tags)) {
            foreach ($tags as $tag) {
                $tagName = $tag->name;

                if (preg_match('/(\s|-)+/', $tagName)) {
                    $tagName = preg_replace('~(\s|-)+~', ' ', $tagName);
                    $tagName = explode(' ', $tagName);
                    $tagName = implode('', array_map('ucfirst', $tagName));
                }

                $hashtags .= '#' . $tagName . ' ';
            }
        }

        return trim($hashtags);
    }    
}
