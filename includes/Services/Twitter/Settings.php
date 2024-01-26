<?php

namespace RRZE\Autoshare\Services\Twitter;

defined('ABSPATH') || exit;

class Settings
{
    protected $settings;

    protected $tab;

    public function __construct(\RRZE\Autoshare\Options\Settings $settings)
    {
        $this->settings = $settings;

        $this->tab = $this->settings->addTab(__('X (Twitter)', 'rrze-autoshare'));

        $this->sectionMain();

        $this->sectionConsumerKeys();
    }

    private function sectionMain()
    {
        $sectionMain = $this->tab->addSection(
            __('Main', 'rrze-autoshare'),
            [
                'description' => '',
            ]
        );

        $sectionMain->addOption('text', [
            'name' => 'twitter_username',
            'label' => __('Username', 'rrze-autoshare'),
            'description' => __('The Twitter account username.', 'rrze-autoshare'),
            'default' => ''
        ]);
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
        $sectionMain->addOption('checkbox', [
            'name' => 'twitter_featured_image',
            'label' => __('Featured Images', 'rrze-autoshare'),
            'description' => __('Include featured images', 'rrze-autoshare'),
            'default' => true
        ]);
        $sectionMain->addOption('button-link', [
            'name' => 'twitter_authorize_access_url',
            'label' => __('Access', 'rrze-autoshare'),
            'href' => [__NAMESPACE__ . '\API', 'authorizeAccessUrl'],
            'text' => [__NAMESPACE__ . '\API', 'authorizeAccessText'],
            'description' => [__NAMESPACE__ . '\API', 'authorizeAccessDescription'],
        ]);
    }

    private function sectionConsumerKeys()
    {
        $sectionKeys = $this->tab->addSection(
            __('Consumer Keys', 'rrze-autoshare'),
            [
                'description' => '',
            ]
        );
        $sectionKeys->addOption('text-secure', [
            'name' => 'twitter_api_key',
            'label' => __('API Key', 'rrze-autoshare'),
            'placeholder' => __('Paste your API Key here', 'rrze-autoshare')
        ]);
        $sectionKeys->addOption('text-secure', [
            'name' => 'twitter_api_key_secret',
            'label' => __('API Key Secret', 'rrze-autoshare'),
            'placeholder' => __('Paste your API Key Secret here', 'rrze-autoshare')
        ]);
    }
}
