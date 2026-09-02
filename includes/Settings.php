<?php

namespace RRZE\Autoshare;

defined('ABSPATH') || exit;

use RRZE\Autoshare\Settings\Settings as OptionsSettings;
use RRZE\Autoshare\Services\Bluesky\API as BlueskyAPI;
use RRZE\Autoshare\Services\Bluesky\Settings as BlueskySettings;
use RRZE\Autoshare\Services\Mastodon\API as MastodonAPI;
use RRZE\Autoshare\Services\Mastodon\Settings as MastodonSettings;
use RRZE\Autoshare\Options\BlueskyAuthorize;
use RRZE\Autoshare\GeneralSettings;

class Settings {
    protected $settings;

    protected $supportedPostTypes = [];

    public function __construct() {
        add_filter('rrze_wp_settings_option_type_map', [$this, 'setOptionTypeMap']);

        add_action('admin_init', [$this, 'connectAPI']);
        add_action('rrze_wp_settings_after_update_option', [$this, 'syncActiveServices'], 10, 2);
    }

    public function loaded() {
        add_action('init', [$this, 'init']);
    }

    public function init() {
        $this->setPostTypes();

        $this->settings = new OptionsSettings(__('Autoshare Settings', 'rrze-autoshare'), config()->get('admin_page_slug'));

        $this->settings->setCapability('manage_options')
            ->setOptionName(config()->get('option_name'))
            ->setMenuTitle(__('RRZE-Autoshare', 'rrze-autoshare'))
            ->setMenuPosition(config()->get('admin_menu_position'))
            ->setMenuParentSlug(config()->get('admin_parent_slug'));

        new GeneralSettings($this->settings);

        // Bluesky settings
        new BlueskySettings(@$this->settings);

        // Mastodon settings
        new MastodonSettings(@$this->settings);

        $this->settings->build();
    }

    public function setOptionTypeMap($options) {
        $options['button-link'] = __NAMESPACE__ . '\Options\ButtonLink';
        $options['bluesky-authorize'] = BlueskyAuthorize::class;
        $options['text-secure'] = __NAMESPACE__ . '\Options\TextSecure';
        return $options;
    }

    public function getOption($option) {
        return $this->settings->getOption($option);
    }

    public function getOptions() {
        return $this->settings->getOptions();
    }

    public function getServices() {
        $services = [];

        foreach (config()->get('services', []) as $slug => $service) {
            $services[$slug] = __($service['label'], 'rrze-autoshare');
        }

        return $services;
    }

    public function isServiceActive(string $service): bool {
        $activeServices = (array) $this->getOption(config()->get('general.active_services.setting'));

        return in_array($service, $activeServices, true);
    }

    public function syncActiveServices($optionName, $options) {
        if ($optionName !== config()->get('option_name')) {
            return;
        }

        $activeServices = (array) ($options[config()->get('general.active_services.setting')] ?? []);
        if (!in_array('bluesky', $activeServices, true)) {
            Cron::clearSchedule();
        }
    }

    public function connectAPI() {
        $page = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $tab = filter_input(INPUT_GET, 'tab', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if ($page !== config()->get('admin_page_slug')) {
            return;
        }
        switch ($tab) {
            case 'bluesky':
                BlueskyAPI::connect();
                break;
            case 'mastodon':
                MastodonAPI::connect();
                break;
        }
    }

    public function setPostTypes() {
        $defaultPostTypes = config()->get('default_post_types');
        $filteredPostTypes = apply_filters('rrze_autoshare_supported_post_types', $defaultPostTypes);
        if (empty($filteredPostTypes) || !is_array($filteredPostTypes)) {
            $filteredPostTypes = $defaultPostTypes;
        }

        $commonTypes = array_intersect($filteredPostTypes, $defaultPostTypes);
        if (count($commonTypes) !== count($defaultPostTypes)) {
            $filteredPostTypes = $defaultPostTypes;
        }

        $availablePostTypes = get_post_types(['public' => true], 'objects');
        foreach ($availablePostTypes as $postType) {
            if (in_array($postType->name, config()->get('excluded_post_types'))) {
                continue;
            }
            if (in_array($postType->name, $filteredPostTypes)) {
                $this->supportedPostTypes[$postType->name] = $postType->labels->name;
            }
        }
    }

    public function getPostTypes() {
        return $this->supportedPostTypes;
    }
}
