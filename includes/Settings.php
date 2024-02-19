<?php

namespace RRZE\Autoshare;

defined('ABSPATH') || exit;

use RRZE\WP\Settings\Settings as OptionsSettings;
use RRZE\Autoshare\Services\Bluesky\API as BlueskyAPI;
use RRZE\Autoshare\Services\Bluesky\Settings as BlueskySettings;
use RRZE\Autoshare\Services\Mastodon\API as MastodonAPI;
use RRZE\Autoshare\Services\Mastodon\Settings as MastodonSettings;
use RRZE\Autoshare\Services\Twitter\API as TwitterAPI;
use RRZE\Autoshare\Services\Twitter\Settings as TwitterSettings;

class Settings
{
    const OPTION_NAME = 'rrze_autoshare';

    protected $settings;

    public function __construct()
    {
        add_filter('rrze_wp_settings_option_type_map', function ($options) {
            $options['button-link'] = __NAMESPACE__ . '\Options\ButtonLink';
            $options['text-secure'] = __NAMESPACE__ . '\Options\TextSecure';
            return $options;
        });

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

        add_action('admin_init', [$this, 'connectAPI']);
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
}
