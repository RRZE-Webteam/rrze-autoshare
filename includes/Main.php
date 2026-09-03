<?php

namespace RRZE\Autoshare;

defined('ABSPATH') || exit;

use RRZE\Autoshare\Services\Bluesky\Main as Bluesky;
use RRZE\Autoshare\Services\Mastodon\Main as Mastodon;
use RRZE\Autoshare\Services\Matrix\Main as Matrix;

class Main {
    /**
     * Loaded
     */
    public function loaded() {
        add_filter('plugin_action_links_' . plugin()->getBaseName(), [$this, 'settingsLink']);

        settings();

        add_action('enqueue_block_editor_assets', [$this, 'enqueueBlockEditorAssets'], 10, 0);
        add_action('init', [$this, 'registerPostMeta']);

        add_action('init', [Encryption::class, 'migrateStoredOptions'], 1);

        Bluesky::init();
        Mastodon::init();
        Matrix::init();

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

    public function enqueueBlockEditorAssets() {
        global $post;
        $postType = get_post_type($post);
        if (!in_array($postType, config()->get('default_post_types'), true)) {
            return;
        }

        wp_enqueue_style(
            config()->get('assets.admin_style_handle'),
            plugins_url(config()->get('assets.admin_style_file'), plugin()->getBasename()),
            [],
            plugin()->getVersion()
        );

        wp_enqueue_script(
            config()->get('assets.admin_script_handle'),
            plugins_url(config()->get('assets.admin_script_file'), plugin()->getBasename()),
            config()->get('assets.admin_script_dependencies'),
            plugin()->getVersion()
        );

        $localization = [
            'autoshareEnabled' => settings()->isPostAutoshareEnabled($post->ID),
            'metaKey' => config()->get('post_meta.enabled'),
            'labels' => [
                'panelTitle' => __('Autoshare', 'rrze-autoshare'),
                'autoshareEnabled' => __('Autoshare enabled', 'rrze-autoshare'),
            ],
        ];

        wp_localize_script(
            config()->get('assets.admin_script_handle'),
            config()->get('assets.admin_script_object_name'),
            $localization
        );
    }

    public function registerPostMeta(): void {
        foreach (config()->get('default_post_types') as $postType) {
            register_post_meta(
                $postType,
                config()->get('post_meta.enabled'),
                [
                    'show_in_rest' => true,
                    'type' => 'boolean',
                    'single' => true,
                    'sanitize_callback' => 'rest_sanitize_boolean',
                    'auth_callback' => [$this, 'canEditPostMeta'],
                    'default' => true,
                ]
            );
        }
    }

    public function canEditPostMeta(
        bool $allowed,
        string $metaKey,
        int $postId,
        int $userId
    ): bool {
        return user_can($userId, 'edit_post', $postId);
    }

}
