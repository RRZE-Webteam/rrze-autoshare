<?php

namespace RRZE\Autoshare\Services\Mastodon;

defined('ABSPATH') || exit;

class Settings
{
    protected $settings;

    public function __construct(\RRZE\Autoshare\Options\Settings $settings)
    {
        $this->settings = $settings;

        $tab = $this->settings->addTab(__('Mastodon', 'rrze-autoshare'));

        $sectionMain = $tab->addSection(
            __('Mastodon Settings', 'rrze-autoshare'),
            [
                'description' => __('Mastodon is a free social networking service. It has microblogging features similar to X.<br>Please complete the settings fields so that Autoshare can read and write to the Mastodon timeline.', 'rrze-autoshare')
            ]
        );

        $sectionMain->addOption('text', [
            'name' => 'mastodon_domain',
            'label' => __('Domain', 'rrze-autoshare'),
            'description' => __('The domain (URL) of the Mastodon service.', 'rrze-autoshare'),
            'default' => 'https://mastodon.social',
            'validate' => [
                [
                    'feedback' => __('The URL entered is not valid.', 'rrze-autoshare'),
                    'callback' => fn ($value) => filter_var($value, FILTER_VALIDATE_URL)
                ]
            ]
        ]);
        $sectionMain->addOption('text', [
            'name' => 'mastodon_username',
            'label' => __('Username', 'rrze-autoshare'),
            'description' => __('The Mastodon account username.', 'rrze-autoshare'),
            'default' => ''
        ]);
        $sectionMain->addOption('checkbox-multiple', [
            'name' => 'mastodon_post_types',
            'label' => __('Content Types', 'rrze-autoshare'),
            'description' => __('Select the type of content that Autoshare could use.', 'rrze-autoshare'),
            'options' => [
                'post' => __('Posts', 'rrze-autoshare'),
                'page' => __('Pages', 'rrze-autoshare')
            ],
            'default' => ['post']
        ]);
        $sectionMain->addOption('checkbox', [
            'name' => 'mastodon_featured_image',
            'label' => __('Featured Images', 'rrze-autoshare'),
            'description' => __('Include featured images', 'rrze-autoshare'),
            'default' => true
        ]);
        $sectionMain->addOption('button-link', [
            'name' => 'mastodon_authorize_access_url',
            'label' => __('Access', 'rrze-autoshare'),
            'href' => [__NAMESPACE__ . '\API', 'authorizeAccessUrl'],
            'text' => [__NAMESPACE__ . '\API', 'authorizeAccessText'],
            'description' => [__NAMESPACE__ . '\API', 'authorizeAccessDescription'],
        ]);
    }
}
