<?php

namespace RRZE\Autoshare;

defined('ABSPATH') || exit;

use RRZE\Autoshare\Settings\Settings as OptionsSettings;
use RRZE\Autoshare\Services\Bluesky\API as BlueskyAPI;
use RRZE\Autoshare\Services\Bluesky\Settings as BlueskySettings;
use RRZE\Autoshare\Services\Mastodon\API as MastodonAPI;
use RRZE\Autoshare\Services\Mastodon\Settings as MastodonSettings;
use RRZE\Autoshare\Services\Twitter\API as TwitterAPI;
use RRZE\Autoshare\Services\Twitter\Settings as TwitterSettings;

class Settings
{
    const OPTION_NAME = 'rrze_autoshare';

    const DEFAULT_POST_TYPES = ['post', 'page'];

    protected $settings;

    protected $supportedPostTypes = [];

    public function __construct()
    {
        add_filter('rrze_wp_settings_option_type_map', function ($options) {
            $options['button-link'] = __NAMESPACE__ . '\Options\ButtonLink';
            $options['text-secure'] = __NAMESPACE__ . '\Options\TextSecure';
            return $options;
        });

        add_action('admin_init', [$this, 'connectAPI']);
    }

    public function loaded()
    {
        add_action('init', [$this, 'init']);
    }

    public function init()
    {
        $this->setPostTypes();

        $this->settings = new OptionsSettings(__('Autoshare Settings', 'rrze-autoshare'), 'rrze_autoshare');

        $this->settings->setCapability('manage_options')
            ->setOptionName(self::OPTION_NAME)
            ->setMenuTitle(__('Autoshare', 'rrze_autoshare'))
            ->setMenuPosition(6)
            ->setMenuParentSlug('options-general.php');

        // Bluesky settings
        new BlueskySettings(@$this->settings);

        // Mastodon settings
        new MastodonSettings(@$this->settings);

        // Twitter settings
        new TwitterSettings(@$this->settings);

        $this->settings->build();
    }

    public function getOption($option)
    {
        return $this->settings->getOption($option);
    }

    public function getOptions()
    {
        return $this->settings->getOptions();
    }

    public function connectAPI()
    {
        $page = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $tab = filter_input(INPUT_GET, 'tab', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if ($page !== 'rrze_autoshare') {
            return;
        }
        switch ($tab) {
            case 'bluesky':
                BlueskyAPI::connect();
                break;
            case 'mastodon':
                MastodonAPI::connect();
                break;
            case 'x-twitter':
                // TwitterAPI::connect();
                break;
        }
    }

    public function setPostTypes()
    {
        $filteredPostTypes = apply_filters('rrze_autoshare_supported_post_types', self::DEFAULT_POST_TYPES);
        if (empty($filteredPostTypes) || !is_array($filteredPostTypes)) {
            $filteredPostTypes = self::DEFAULT_POST_TYPES;
        }

        $commonTypes = array_intersect($filteredPostTypes, self::DEFAULT_POST_TYPES);
        if (count($commonTypes) !== count(self::DEFAULT_POST_TYPES)) {
            $filteredPostTypes = self::DEFAULT_POST_TYPES;
        }

        $availablePostTypes = get_post_types(['public' => true], 'objects');
        foreach ($availablePostTypes as $postType) {
            if (in_array($postType->name, ['attachment', 'revision', 'nav_menu_item'])) {
                continue;
            }
            if (in_array($postType->name, $filteredPostTypes)) {
                $this->supportedPostTypes[$postType->name] = $postType->labels->name;
            }
        }
    }

    public function getPostTypes()
    {
        return $this->supportedPostTypes;
    }
}
