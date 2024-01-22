<?php

namespace RRZE\Autoshare;

defined('ABSPATH') || exit;

use RRZE\Autoshare\Services\Bluesky\Main as Bluesky;
use RRZE\Autoshare\Services\Mastodon\Main as Mastodon;
// use RRZE\Autoshare\Services\Mastodon\Main as Twitter;

class Main
{
    /**
     * __construct
     */
    public function __construct()
    {
        add_filter('plugin_action_links_' . plugin()->getBaseName(), [$this, 'settingsLink']);

        /* Enqueue Admin Assets */
        add_action('admin_enqueue_scripts', [$this, 'adminEnqueueScripts']);

        /* Enqueue Block Editor Assets */
        add_action('enqueue_block_editor_assets', [__CLASS__, 'enqueueBlockEditorAssets'], 10, 0);

        settings();

        Bluesky::init();
        Mastodon::init();
        // Twitter::init();

        Metabox::init();

        Cron::init();
    }

    /**
     * Add the settings link to the list of plugins.
     *
     * @param array $links
     * @return void
     */
    public function settingsLink($links)
    {
        $settingsLink = sprintf(
            '<a href="%s">%s</a>',
            admin_url('options-general.php?page=rrze_autoshare'),
            __('Settings', 'rrze_autoshare')
        );
        array_unshift($links, $settingsLink);
        return $links;
    }

    public function adminEnqueueScripts($hook)
    {
        if ($hook != 'post.php' && $hook != 'post-new.php') {
            return;
        }

        global $post;
        if (
            !in_array(get_post_type($post), settings()->getOption('bluesky_post_types'))
            && !in_array(get_post_type($post), settings()->getOption('mastodon_post_types'))
            // && !in_array(get_post_type($post), settings()->getOption('twitter_post_types'))
        ) {
            return;
        }

        wp_enqueue_style(
            'rrze-autoshare-admin',
            plugins_url('build/admin.style.css', plugin()->getBasename()),
            [],
            plugin()->getVersion()
        );
    }

    public static function enqueueBlockEditorAssets()
    {
        global $post;
        if (
            !in_array(get_post_type($post), settings()->getOption('bluesky_post_types'))
            && !in_array(get_post_type($post), settings()->getOption('mastodon_post_types'))
            // && !in_array(get_post_type($post), settings()->getOption('twitter_post_types'))
        ) {
            return;
        }

        wp_enqueue_style(
            'rrze-autoshare-blockeditor',
            plugins_url('build/blockeditor.style.css', plugin()->getBasename()),
            [],
            plugin()->getVersion()
        );

        $assetFile = include(plugin()->getPath('build') . 'blockeditor.asset.php');

        wp_enqueue_script(
            'rrze-autoshare-blockeditor',
            plugins_url('build/blockeditor.js', plugin()->getBasename()),
            $assetFile['dependencies'],
            plugin()->getVersion()
        );

        $localization = [
            'blueskyEnabled' => Bluesky::isConnected(),
            'blueskyPublished' => Bluesky::isPublished($post->post_type, $post->ID),
            'mastodonEnabled' => Mastodon::isConnected(),
            'mastodonPublished' => Mastodon::isPublished($post->post_type, $post->ID),
            // 'twitterEnabled' => Twitter::isConnected(),
            // 'twitterPublished' => Twitter::isPublished($post->post_type, $post->ID)            
        ];

        wp_localize_script(
            'rrze-autoshare-blockeditor',
            'autoshareObject',
            $localization
        );

        wp_set_script_translations(
            'rrze-autoshare',
            'rrze-autoshare',
            plugin()->getPath('languages')
        );
    }
}
