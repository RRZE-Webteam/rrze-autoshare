<?php

namespace RRZE\Autoshare;

defined('ABSPATH') || exit;

use RRZE\Autoshare\Services\Bluesky\Main as Bluesky;
use RRZE\Autoshare\Services\Mastodon\Main as Mastodon;

class Main {
    /**
     * Loaded
     */
    public function loaded() {
        add_filter('plugin_action_links_' . plugin()->getBaseName(), [$this, 'settingsLink']);

        /* Enqueue Admin Assets */
        add_action('admin_enqueue_scripts', [$this, 'adminEnqueueScripts']);

        /* Enqueue Block Editor Assets */
        add_action('enqueue_block_editor_assets', [$this, 'enqueueBlockEditorAssets'], 10, 0);

        settings()->loaded();
        Metabox::init();

        Bluesky::init();
        Mastodon::init();

        Cron::init();
    }

    /**
     * Add the settings link to the list of plugins.
     *
     * @param array $links
     * @return void
     */
    public function settingsLink($links) {
        $settingsLink = sprintf(
            '<a href="%s">%s</a>',
            admin_url(config()->get('admin_parent_slug') . '?page=' . config()->get('admin_page_slug')),
            __('Settings', 'rrze-autoshare')
        );
        array_unshift($links, $settingsLink);
        return $links;
    }

    public function adminEnqueueScripts($hook) {
        if ($hook != 'post.php' && $hook != 'post-new.php') {
            return;
        }

        global $post;
        if (
            !$this->isServiceAvailableForPostType('bluesky', get_post_type($post))
            && !$this->isServiceAvailableForPostType('mastodon', get_post_type($post))
        ) {
            return;
        }

        wp_enqueue_style(
            config()->get('assets.admin_style_handle'),
            plugins_url(config()->get('assets.admin_style_file'), plugin()->getBasename()),
            [],
            plugin()->getVersion()
        );
    }

    public function enqueueBlockEditorAssets() {
        global $post;
        if (
            !$this->isServiceAvailableForPostType('bluesky', get_post_type($post))
            && !$this->isServiceAvailableForPostType('mastodon', get_post_type($post))
        ) {
            return;
        }

        wp_enqueue_script(
            config()->get('assets.admin_script_handle'),
            plugins_url(config()->get('assets.admin_script_file'), plugin()->getBasename()),
            config()->get('assets.admin_script_dependencies'),
            plugin()->getVersion()
        );

        $blueskyActive = settings()->isServiceActive('bluesky');
        $blueskyMetaEnabled = config()->get('services.bluesky.meta.enabled');
        $blueskyIsEnabled = $blueskyActive && (metadata_exists('post', $post->ID, $blueskyMetaEnabled) ? Bluesky::isEnabled($post->ID) : true);
        $blueskyIsPublished = Bluesky::isPublished($post->ID);
        $blueskyIsConnected = Bluesky::isConnected();

        $mastodonActive = settings()->isServiceActive('mastodon');
        $mastodonMetaEnabled = config()->get('services.mastodon.meta.enabled');
        $mastodonIsEnabled = $mastodonActive && (metadata_exists('post', $post->ID, $mastodonMetaEnabled) ? Mastodon::isEnabled($post->ID) : true);
        $mastodonIsPublished = Mastodon::isPublished($post->ID);
        $mastodonIsConnected = Mastodon::isConnected();

        $localization = [
            'blueskyActive' => $blueskyActive,
            'blueskyConnected' => $blueskyIsConnected,
            'blueskyEnabled' => $blueskyIsEnabled,
            'blueskyPublished' => $blueskyIsPublished,
            'mastodonActive' => $mastodonActive,
            'mastodonConnected' => $mastodonIsConnected,
            'mastodonEnabled' => $mastodonIsEnabled,
            'mastodonPublished' => $mastodonIsPublished,
            'metaKeys' => [
                'blueskyEnabled' => config()->get('services.bluesky.meta.enabled'),
                'mastodonEnabled' => config()->get('services.mastodon.meta.enabled'),
            ],
            'labels' => [
                'panelTitle' => __('Autoshare', 'rrze-autoshare'),
                'blueskyShare' => __('Share on Bluesky', 'rrze-autoshare'),
                'blueskyDisabled' => __('Share on Bluesky is disabled', 'rrze-autoshare'),
                'blueskyPublished' => __('It is published on Bluesky', 'rrze-autoshare'),
                'mastodonShare' => __('Share on Mastodon', 'rrze-autoshare'),
                'mastodonDisabled' => __('Share on Mastodon is disabled', 'rrze-autoshare'),
                'mastodonPublished' => __('It is published on Mastodon', 'rrze-autoshare'),
            ],
        ];

        wp_localize_script(
            config()->get('assets.admin_script_handle'),
            config()->get('assets.admin_script_object_name'),
            $localization
        );
    }

    private function isServiceAvailableForPostType(string $service, string $postType): bool {
        return (
            settings()->isServiceActive($service)
            && in_array(
                $postType,
                settings()->getOption(config()->get('services.' . $service . '.settings.post_types')),
                true
            )
        );
    }
}
