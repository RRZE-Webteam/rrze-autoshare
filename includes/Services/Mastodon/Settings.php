<?php

namespace RRZE\Autoshare\Services\Mastodon;

defined('ABSPATH') || exit;

use function RRZE\Autoshare\config;
use function RRZE\Autoshare\settings;

class Settings {
    protected $settings;

    public function __construct(\RRZE\Autoshare\Settings\Settings $settings) {
        $this->settings = $settings;

        $tab = $this->settings->addTab(__('Mastodon', 'rrze-autoshare'));

        $sectionMain = $tab->addSection(
            __('Mastodon Settings', 'rrze-autoshare'),
            [
                'description' => __('Mastodon is a free social networking service with microblogging features.<br>Please complete the settings fields so that Autoshare can read and write to the Mastodon timeline.', 'rrze-autoshare')
            ]
        );

        $sectionMain->addOption('text', [
            'name' => config()->get('services.mastodon.settings.domain'),
            'label' => __('Service URL', 'rrze-autoshare'),
            'description' => __('The URL of the Mastodon service.', 'rrze-autoshare'),
            'css' => [
                'input_class' => 'regular-text'
            ],
            'default' => config()->get('services.mastodon.defaults.domain'),
            'validate' => [
                [
                    'feedback' => __('The URL entered is not valid.', 'rrze-autoshare'),
                    'callback' => ['RRZE\Autoshare\Config', 'validateUrl']
                ]
            ]
        ]);
        $sectionMain->addOption('text', [
            'name' => config()->get('services.mastodon.settings.username'),
            'label' => __('Username', 'rrze-autoshare'),
            'description' => __('The Mastodon account username.', 'rrze-autoshare'),
            'css' => [
                'input_class' => 'regular-text'
            ],
            'default' => ''
        ]);
        $sectionMain->addOption('checkbox-multiple', [
            'name' => config()->get('services.mastodon.settings.post_types'),
            'label' => __('Content Types', 'rrze-autoshare'),
            'description' => __('Select the type of content that Autoshare could use.', 'rrze-autoshare'),
            'options' => settings()->getPostTypes(),
            'default' => config()->get('services.mastodon.defaults.post_types')
        ]);
        $sectionMain->addOption('checkbox', [
            'name' => config()->get('services.mastodon.settings.featured_image'),
            'label' => __('Featured Images', 'rrze-autoshare'),
            'description' => __('Include featured images', 'rrze-autoshare'),
            'default' => config()->get('services.mastodon.defaults.featured_image')
        ]);

        $content = config()->get('services.mastodon.content');
        $contentDescription = sprintf(
            __('Accepted content type: %1$s. Maximum post length: %2$d characters. The actual limit can differ depending on the Mastodon instance.', 'rrze-autoshare'),
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
            'name' => config()->get('services.mastodon.settings.format'),
            'label' => __('Format', 'rrze-autoshare'),
            'description' => __('Use the placeholders {title}, {excerpt}, {url}, and {tags}. Empty lines are removed automatically.', 'rrze-autoshare'),
            'default' => config()->get('services.mastodon.defaults.format'),
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
        $sectionMain->addOption('button-link', [
            'name' => config()->get('services.mastodon.settings.authorize_access_url'),
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
