<?php

namespace RRZE\Autoshare;

defined('ABSPATH') || exit;

use RRZE\Autoshare\Options\Settings as OptionsSettings;
use RRZE\Autoshare\Services\Bluesky;

class Settings
{
    const OPTION_NAME = 'rrze_autoshare';

    protected $settings;

    public function __construct()
    {
        $this->settings = new OptionsSettings(__('Autoshare Settings', 'rrze-autoshare'), 'rrze_autoshare');

        $this->settings->setCapability('manage_options')
            ->setOptionName(self::OPTION_NAME)
            ->setMenuTitle(__('Autoshare', 'rrze_autoshare'))
            ->setMenuPosition(6)
            ->setMenuParentSlug('options-general.php');

        $bluesky = $this->settings->addTab(__('Bluesky', 'rrze-autoshare'));

        $sectionBskyMain = $bluesky->addSection(
            __('Bluesky Settings', 'rrze-autoshare'),
            [
                'description' => sprintf(
                    '%1$s %2$s',
                    __('Status:', 'rrze-autoshare'),
                    Bluesky::getConnectionStatus()
                )
            ]
        );

        $twitter = $this->settings->addTab(__('Twitter', 'rrze-autoshare'));

        $sectionTwitterMain = $twitter->addSection(__('Twitter Settings', 'rrze-autoshare'));

        $sectionBskyMain->addOption('text', [
            'name' => 'bluesky_domain',
            'label' => __('Domain', 'rrze-autoshare'),
            'description' => __('The domain (URL) of the Bluesky instance.', 'rrze-autoshare'),
            'default' => 'https://bsky.social',
            'validate' => [
                [
                    'feedback' => __('The URL entered is not valid.', 'rrze-autoshare'),
                    'callback' => [$this, 'validateUrl']
                ]
            ]
        ]);
        $sectionBskyMain->addOption('text', [
            'name' => 'bluesky_identifier',
            'label' => __('Indentifier', 'rrze-autoshare'),
            'description' => __('The Bluesky account identifier.', 'rrze-autoshare'),
            'default' => ''
        ]);
        $sectionBskyMain->addOption('password', [
            'name' => 'bluesky_password',
            'label' => __('Password', 'rrze-autoshare'),
            'description' => __('The Bluesky account password.', 'rrze-autoshare')
        ]);
        $sectionBskyMain->addOption('checkbox-multiple', [
            'name' => 'bluesky_post_types',
            'label' => __('Content Types', 'rrze-autoshare'),
            'description' => __('Select the type of content that Autoshare could use.', 'rrze-autoshare'),
            'options' => [
                'post' => __('Posts', 'rrze-autoshare'),
                'page' => __('Pages', 'rrze-autoshare')
            ],
            'default' => ['post']
        ]);

        $sectionTwitterMain->addOption('checkbox-multiple', [
            'name' => 'twitter_post_types',
            'label' => __('Content Types', 'rrze-autoshare'),
            'description' => __('Select the type of content that Autoshare could use.', 'rrze-autoshare'),
            'options' => [
                'post' => __('Posts', 'rrze-autoshare'),
                'page' => __('Pages', 'rrze-autoshare')
            ],
            'default' => ['post']
        ]);

        $this->settings->build();
    }

    public function validateUrl($value)
    {
        return filter_var($value, FILTER_VALIDATE_URL);
    }

    public function getOption($option)
    {
        return $this->settings->getOption($option);
    }

    public function getOptions()
    {
        return $this->settings->getOptions();
    }

    /**
     * __call method
     * Method overloading.
     */
    public function __call(string $name, array $arguments)
    {
        if (!method_exists($this, $name)) {
            $message = sprintf('Call to undefined method %1$s::%2$s', __CLASS__, $name);
            do_action(
                'rrze.log.error',
                $message,
                [
                    'class' => __CLASS__,
                    'method' => $name,
                    'arguments' => $arguments
                ]
            );
            if (defined('WP_DEBUG') && WP_DEBUG) {
                throw new \Exception($message);
            }
        }
    }
}
