<?php

namespace RRZE\Autoshare\Services\Twitter;

defined('ABSPATH') || exit;

class Settings
{
    protected $settings;

    public function __construct(\RRZE\Autoshare\Options\Settings $settings)
    {
        $this->settings = $settings;

        $tab = $this->settings->addTab(__('Twitter', 'rrze-autoshare'));

        $sectionMain = $tab->addSection(__('Twitter Settings', 'rrze-autoshare'));

        $sectionMain->addOption('checkbox-multiple', [
            'name' => 'twitter_post_types',
            'label' => __('Content Types', 'rrze-autoshare'),
            'description' => __('Select the type of content that Autoshare could use.', 'rrze-autoshare'),
            'options' => [
                'post' => __('Posts', 'rrze-autoshare'),
                'page' => __('Pages', 'rrze-autoshare')
            ],
            'default' => ['post']
        ]);
    }
}
