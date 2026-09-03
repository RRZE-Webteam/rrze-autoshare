<?php

namespace RRZE\Autoshare;

defined('ABSPATH') || exit;

use RRZE\Autoshare\Services\Bluesky\Main as Bluesky;
use RRZE\Autoshare\Services\Bluesky\API as BlueskyAPI;
use RRZE\Autoshare\Services\Mastodon\Main as Mastodon;
use RRZE\Autoshare\Services\Mastodon\API as MastodonAPI;

class Main {
    /**
     * Loaded
     */
    public function loaded() {
        add_filter('plugin_action_links_' . plugin()->getBaseName(), [$this, 'settingsLink']);

        add_action('enqueue_block_editor_assets', [$this, 'enqueueBlockEditorAssets'], 10, 0);
        add_action('init', [$this, 'registerPostMeta']);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        add_action('init', [Encryption::class, 'migrateStoredOptions'], 1);

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

    public function enqueueBlockEditorAssets() {
        global $post;
        $postType = get_post_type($post);
        $services = $this->getActiveServicesForPostType($postType);
        if (empty($services)) {
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
            'services' => $services,
            'shareRoute' => '/' . config()->get('rest.namespace') . sprintf(
                config()->get('rest.share_path'),
                $post->ID
            ),
            'metaKey' => config()->get('post_meta.enabled'),
            'labels' => [
                'panelTitle' => __('Autoshare', 'rrze-autoshare'),
                'autoshareEnabled' => __('Autoshare enabled', 'rrze-autoshare'),
                'sharePost' => __('Share Post', 'rrze-autoshare'),
                'sharingPost' => __('Sharing post...', 'rrze-autoshare'),
                'shareSucceeded' => __('The post was shared successfully.', 'rrze-autoshare'),
                'shareFailed' => __('The post could not be shared with: %s.', 'rrze-autoshare'),
                'selectService' => __('Select at least one service.', 'rrze-autoshare'),
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
                config()->get('default_post_types'),
                true
            )
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

    public function registerRestRoutes(): void {
        register_rest_route(
            config()->get('rest.namespace'),
            config()->get('rest.share_route'),
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'sharePost'],
                'permission_callback' => [$this, 'canSharePost'],
                'args' => [
                    'services' => [
                        'type' => 'array',
                        'required' => true,
                        'items' => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ]
        );
    }

    public function canSharePost(\WP_REST_Request $request): bool {
        return current_user_can('edit_post', absint($request->get_param('id')));
    }

    public function sharePost(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $postId = absint($request->get_param('id'));
        $post = get_post($postId);
        if (!$post instanceof \WP_Post || 'publish' !== $post->post_status) {
            return new \WP_Error(
                'rrze_autoshare_invalid_post',
                __('The post must be published before it can be shared.', 'rrze-autoshare'),
                ['status' => 400]
            );
        }

        $submittedServices = $request->get_param('services');
        $selectedServices = [];
        foreach ((array) $submittedServices as $service) {
            $service = is_scalar($service) ? sanitize_key($service) : '';
            if (
                $service !== ''
                && $this->isServiceAvailableForPostType($service, $post->post_type)
            ) {
                $selectedServices[] = $service;
            }
        }
        $selectedServices = array_values(array_unique($selectedServices));

        if (empty($selectedServices)) {
            return new \WP_Error(
                'rrze_autoshare_no_services',
                __('Select at least one active service.', 'rrze-autoshare'),
                ['status' => 400]
            );
        }

        $results = [];
        foreach ($selectedServices as $service) {
            if ('bluesky' === $service) {
                $results[$service] = BlueskyAPI::publishPost($postId);
            } elseif ('mastodon' === $service) {
                $results[$service] = MastodonAPI::publishPost($postId);
            }
        }

        return rest_ensure_response(['results' => $results]);
    }

    private function getActiveServicesForPostType(string $postType): array {
        $services = [];

        foreach (settings()->getServices() as $service => $label) {
            if ($this->isServiceAvailableForPostType($service, $postType)) {
                $services[] = [
                    'slug' => $service,
                    'label' => $label,
                ];
            }
        }

        return $services;
    }
}
