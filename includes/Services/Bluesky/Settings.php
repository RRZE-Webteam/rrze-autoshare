<?php

namespace RRZE\Autoshare\Services\Bluesky;

defined('ABSPATH') || exit;

use function RRZE\Autoshare\config;
use function RRZE\Autoshare\settings;

class Settings {
    protected $settings;

    public function __construct(\RRZE\Autoshare\Settings\Settings $settings) {
        $this->settings = $settings;

        $tab = $this->settings->addTab(__('Bluesky', 'rrze-autoshare'));

        $sectionMain = $tab->addSection(
            __('Bluesky Settings', 'rrze-autoshare'),
            [
                'description' => __('Bluesky, also known as Bluesky Social, is a social microblogging platform.<br>Please complete the settings fields so that Autoshare can read and write to the Bluesky timeline.', 'rrze-autoshare')
            ]
        );

        $sectionMain->addOption('text', [
            'name' => config()->get('services.bluesky.settings.domain'),
            'label' => __('Service URL', 'rrze-autoshare'),
            'description' => __('The URL of the Bluesky service.', 'rrze-autoshare'),
            'css' => [
                'input_class' => 'regular-text'
            ],
            'readonly' => true,
            'default' => config()->get('services.bluesky.defaults.domain'),
            'validate' => [
                [
                    'feedback' => __('The URL entered is not valid.', 'rrze-autoshare'),
                    'callback' => ['RRZE\Autoshare\Config', 'validateUrl']
                ],
                [
                    'feedback' => __('The Bluesky service URL cannot be changed.', 'rrze-autoshare'),
                    'callback' => ['RRZE\Autoshare\Config', 'validateBlueskyServiceUrl']
                ]
            ]
        ]);
        $sectionMain->addOption('checkbox-multiple', [
            'name' => config()->get('services.bluesky.settings.post_types'),
            'label' => __('Content Types', 'rrze-autoshare'),
            'description' => __('Select the type of content that Autoshare could use.', 'rrze-autoshare'),
            'options' => settings()->getPostTypes(),
            'default' => config()->get('services.bluesky.defaults.post_types')
        ]);
        $sectionMain->addOption('checkbox', [
            'name' => config()->get('services.bluesky.settings.featured_image'),
            'label' => __('Featured Images', 'rrze-autoshare'),
            'description' => __('Include featured images', 'rrze-autoshare'),
            'default' => config()->get('services.bluesky.defaults.featured_image')
        ]);

        $content = config()->get('services.bluesky.content');
        $contentDescription = sprintf(
            __('Accepted content type: %1$s. Maximum post length: %2$d characters.', 'rrze-autoshare'),
            __('Text', 'rrze-autoshare'),
            $content['max_length']
        );
        $sectionFormat = $tab->addSection(
            __('Post Format', 'rrze-autoshare'),
            [
                'description' => $contentDescription,
            ]
        );
        $sectionFormat->addOption('textarea', [
            'name' => config()->get('services.bluesky.settings.format'),
            'label' => __('Format', 'rrze-autoshare'),
            'description' => __('Use the placeholders {title}, {excerpt}, {url}, and {tags}. Empty lines are removed automatically.', 'rrze-autoshare'),
            'default' => config()->get('services.bluesky.defaults.format'),
            'css' => [
                'input_class' => 'large-text code',
            ],
            'validate' => [
                [
                    'feedback' => __('The format must contain {title}, {excerpt}, {url}, and {tags}.', 'rrze-autoshare'),
                    'callback' => ['RRZE\Autoshare\Config', 'validatePostFormat'],
                ],
            ],
        ]);
        $sectionMain->addOption('bluesky-authorize', [
            'name' => 'bluesky_authorize',
            'label' => __('Access', 'rrze-autoshare'),
            'transient' => true,
        ]);
    }
}
