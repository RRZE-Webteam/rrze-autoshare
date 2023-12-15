<?php

namespace RRZE\Autoshare;

defined('ABSPATH') || exit;

use RRZE\Autoshare\Options\Settings as OptionsSettings;
use RRZE\Autoshare\Services\Bluesky\Main as Bluesky;
use RRZE\Autoshare\Services\Bluesky\Settings as BlueskySettings;
use RRZE\Autoshare\Services\Twitter\Settings as TwitterSettings;

class Settings
{
    const OPTION_NAME = 'rrze_autoshare';

    protected $settings;

    public function __construct()
    {
        add_action('rrze_autoshare_post_update_option', [$this, 'postUpdateOption']);

        $this->settings = new OptionsSettings(__('Autoshare Settings', 'rrze-autoshare'), 'rrze_autoshare');

        $this->settings->setCapability('manage_options')
            ->setOptionName(self::OPTION_NAME)
            ->setMenuTitle(__('Autoshare', 'rrze_autoshare'))
            ->setMenuPosition(6)
            ->setMenuParentSlug('options-general.php');

        // Bluesky settings
        new BlueskySettings(@$this->settings);

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

    public function postUpdateOption()
    {
        Bluesky::connect();
    }
}
