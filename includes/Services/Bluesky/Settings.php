<?php

namespace RRZE\Autoshare\Services\Bluesky;

defined('ABSPATH') || exit;

use function RRZE\Autoshare\settings;

class Settings
{
    protected $settings;

    public function __construct(\RRZE\Autoshare\Settings\Settings $settings)
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
            'label' => __('Service URL', 'rrze-autoshare'),
            'description' => __('The URL of the Bluesky service.', 'rrze-autoshare'),
            'css' => [
                'input_class' => 'regular-text'
            ],
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
            'label' => __('Username or email address', 'rrze-autoshare'),
            'description' => __('The Bluesky account username or email address.', 'rrze-autoshare'),
            'css' => [
                'input_class' => 'regular-text'
            ],
            'default' => ''
        ]);
        $sectionMain->addOption('password', [
            'name' => 'bluesky_password',
            'label' => __('Password', 'rrze-autoshare'),
            'description' => __('The Bluesky account password.', 'rrze-autoshare'),
            'css' => [
                'input_class' => 'regular-text'
            ],
        ]);
        $sectionMain->addOption('checkbox-multiple', [
            'name' => 'bluesky_post_types',
            'label' => __('Content Types', 'rrze-autoshare'),
            'description' => __('Select the type of content that Autoshare could use.', 'rrze-autoshare'),
            'options' => settings()->getPostTypes(),
            'default' => ['post']
        ]);
        $sectionMain->addOption('checkbox', [
            'name' => 'bluesky_enable_default',
            'label' => __('Enable by default', 'rrze-autoshare'),
            'description' => __('Enable Autoshare by default when publishing content', 'rrze-autoshare'),
            'default' => true
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
            'css' => [
                'input_class' => 'button button-secondary'
            ],
        ]);
    }
}
