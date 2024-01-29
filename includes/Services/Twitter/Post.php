<?php

namespace RRZE\Autoshare\Services\Twitter;

defined('ABSPATH') || exit;

use function RRZE\Autoshare\settings;

class Post
{
    public static function init()
    {
        $supportedPostTypes = settings()->getOption('twitter_post_types');
        foreach ($supportedPostTypes as $postType) {
            add_action("save_post_{$postType}", [__CLASS__, 'savePost'], 10, 2);
            add_action("rest_after_insert_{$postType}", [__CLASS__, 'restAfterInsert']);
        }

        add_action('rrze_autoshare_twitter_publish_post', [__CLASS__, 'publishPost'], 10, 2);
    }

    public static function savePost($postId, $post)
    {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return;
        }

        if (wp_is_post_revision($post) || wp_is_post_autosave($post)) {
            return;
        }

        $supportedPostTypes = settings()->getOption('twitter_post_types');
        if (!in_array($post->post_type, $supportedPostTypes)) {
            return;
        }

        $metaValue = isset($_POST['rrze_autoshare_twitter_enabled']) ? true : false;
        update_metadata($post->post_type, $postId, 'rrze_autoshare_twitter_enabled', $metaValue);

        self::publishOnService($post);
    }

    public static function restAfterInsert($post)
    {
        $supportedPostTypes = settings()->getOption('twitter_post_types');
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
        wp_schedule_single_event(time(), 'rrze_autoshare_twitter_publish_post', [$post->post_type, $post->ID]);
    }

    public static function publishPost($postType, $postId)
    {
        delete_metadata($postType, $postId, 'rrze_autoshare_twitter_sent');
        API::publishPost($postId);
    }

    public static function isEnabled($postType, $postId)
    {
        return (bool) get_metadata($postType, $postId, 'rrze_autoshare_twitter_enabled', true);
    }

    public static function isPublished($postType, $postId)
    {
        $sent = (bool) get_metadata($postType, $postId, 'rrze_autoshare_twitter_sent', true);
        $published = (bool) get_metadata($postType, $postId, 'rrze_autoshare_twitter_published', true);
        return $sent || $published;
    }

    public static function getContent(\WP_Post $post)
    {
        // Don't use get_the_title() because may introduce texturized characters.
        $text = sanitize_text_field($post->post_title);
        $permalink = esc_url_raw(get_the_permalink($post->ID));

        $isLocalEnv = in_array(wp_get_environment_type(), ['local', 'development'], true);
        $isLocalUrl = strpos(home_url(), '.test') || strpos(home_url(), '.local') || strpos(home_url(), '.localhost');
        $isLocal = $isLocalEnv || $isLocalUrl;
        $permalinkLength = (!$isLocal) ? 23 : strlen($permalink); // 23 is the length of t.co URL.
        $textMaxLength = 275 - $permalinkLength; // 275 instead of 280 because of the space between body and URL and the ellipsis.
        $text = sanitize_text_field($text);
        $text = html_entity_decode($text, ENT_QUOTES, get_bloginfo('charset'));
        $textLength = strlen($text);
        $ellipsis = ''; // Initialize as empty. Will be set if the text is too long.

        while ($textMaxLength < $textLength) {
            // Don't use `&hellip;` because may display encoded.
            $ellipsis = ' ...';

            // If there are no spaces in the text for whatever reason, 
            // truncate regardless of where spaces fall.
            if (false === strpos($text, ' ')) {
                $text = substr($text, 0, $textMaxLength);
                break;
            }

            // Cut off the last word in the text until the tweet is short enough.
            $words = explode(' ', $text);
            array_pop($words);
            $text = implode(' ', $words);
            $textLength = strlen($text);
        }

        return sprintf('%s%s %s', $text, $ellipsis, $permalink);
    }
}
