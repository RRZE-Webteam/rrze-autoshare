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
                'description' => __('Bluesky, also known as Bluesky Social, is a social microblogging platform.<br>Please complete the settings fields so that Autoshare can read and write to the Bluesky timeline.', 'rrze-autoshare')
            ]
        );

        $sectionMain->addOption('text', [
            'name' => 'bluesky_domain',
            'label' => __('Domain', 'rrze-autoshare'),
            'description' => __('The domain (URL) of the Bluesky service.', 'rrze-autoshare'),
            'default' => 'https://bsky.social',
            'validate' => [
                [
                    'feedback' => __('The URL entered is not valid.', 'rrze-autoshare'),
                    'callback' => fn ($value) => filter_var($value, FILTER_VALIDATE_URL)
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
        $sectionMain->addOption('checkbox', [
            'name' => 'bluesky_featured_image',
            'label' => __('Featured Images', 'rrze-autoshare'),
            'description' => __('Include featured images', 'rrze-autoshare'),
            'default' => true
        ]);
        $sectionMain->addOption('button-link', [
            'name' => 'bluesky_authorize_access_url',
            'label' => __('Access', 'rrze-autoshare'),
            'href' => [__NAMESPACE__ . '\API', 'authorizeAccessUrl'],
            'text' => [__NAMESPACE__ . '\API', 'authorizeAccessText'],
            'description' => [__NAMESPACE__ . '\API', 'authorizeAccessDescription'],
        ]);
    }
}
