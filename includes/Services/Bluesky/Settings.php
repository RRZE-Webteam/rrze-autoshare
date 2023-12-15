<?php

namespace RRZE\Autoshare\Services\Bluesky;

defined('ABSPATH') || exit;

class Settings
{
    protected $settings;

    public function __construct(\RRZE\Autoshare\Options\Settings $settings)
    {
        $this->settings = $settings;

        $tab = $this->settings->addTab(__('Bluesky', 'rrze-autoshare'));

        $sectionMain = $tab->addSection(
            __('Bluesky Settings', 'rrze-autoshare'),
            [
                'description' => sprintf(
                    '%1$s %2$s',
                    __('Status:', 'rrze-autoshare'),
                    Utils::getConnectionStatus()
                )
            ]
        );

        $sectionMain->addOption('text', [
            'name' => 'bluesky_domain',
            'label' => __('Domain', 'rrze-autoshare'),
            'description' => __('The domain (URL) of the Bluesky instance.', 'rrze-autoshare'),
            'default' => 'https://bsky.social',
            'validate' => [
                [
                    'feedback' => __('The URL entered is not valid.', 'rrze-autoshare'),
                    'callback' => [__NAMESPACE__ . '\Utils', 'validateUrl']
                ]
            ]
        ]);
        $sectionMain->addOption('text', [
            'name' => 'bluesky_identifier',
            'label' => __('Indentifier', 'rrze-autoshare'),
            'description' => __('The Bluesky account identifier.', 'rrze-autoshare'),
            'default' => ''
        ]);
        $sectionMain->addOption('password', [
            'name' => 'bluesky_password',
            'label' => __('Password', 'rrze-autoshare'),
            'description' => __('The Bluesky account password.', 'rrze-autoshare')
        ]);
        $sectionMain->addOption('checkbox-multiple', [
            'name' => 'bluesky_post_types',
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
