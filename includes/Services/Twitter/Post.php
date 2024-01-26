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

        add_action('rrze_autoshare_twitter_publish_post', [__CLASS__, 'publishPost']);
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

        wp_schedule_single_event(time(), 'rrze_autoshare_twitter_publish_post', [$post->ID]);
    }

    /**
     * Make the body of the tweet based on Title and URL.
     *
     * @param \WP_Post $post The post object.
     *
     * @return string
     */
    public static function buildBody(\WP_Post $post)
    {
        // Use $post->post_title instead of get_the_title() because 
        // get_the_title() may introduce texturized characters that Twitter won't decode.
        $body = sanitize_text_field($post->post_title);

        $url = get_the_permalink($post->ID);

        $url = esc_url($url);
        $isLocalEnv = in_array(wp_get_environment_type(), ['local', 'development'], true);
        $isLocalUrl = strpos(home_url(), '.test') || strpos(home_url(), '.local');
        $isLocal = $isLocalEnv || $isLocalUrl;
        $urlLength = (!$isLocal) ? 23 : strlen($url);
        $bodyMaxLength = 275 - $urlLength; // 275 instead of 280 because of the space between body and URL and the ellipsis.
        $body = sanitize_text_field($body);
        $body = html_entity_decode($body, ENT_QUOTES, get_bloginfo('charset'));
        $bodyLength = strlen($body);
        $ellipsis = ''; // Initialize as empty. Will be set if the tweet body is too long.

        while ($bodyMaxLength < $bodyLength) {
            // Don't use `&hellip;` here or it will display encoded when tweeting.
            $ellipsis = ' ...';

            // If there are no spaces in the tweet for whatever reason, 
            // truncate regardless of where spaces fall.
            if (false === strpos($body, ' ')) {
                $body = substr($body, 0, $bodyMaxLength);
                break;
            }

            // Cut off the last word in the text until the tweet is short enough.
            $words = explode(' ', $body);
            array_pop($words);
            $body = implode(' ', $words);
            $bodyLength = strlen($body);
        }

        return sprintf('%s%s %s', $body, $ellipsis, $url);
    }

    public static function publishPost($postId)
    {
        API::publishPost($postId);
    }

    public static function isEnabled($postType, $postId)
    {
        return (bool) get_metadata($postType, $postId, 'rrze_autoshare_twitter_enabled', true);
    }

    public static function isPublished($postType, $postId)
    {
        return (bool) get_metadata($postType, $postId, 'rrze_autoshare_twitter_published', true);
    }
}
