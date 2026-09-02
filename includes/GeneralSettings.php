<?php

namespace RRZE\Autoshare;

defined('ABSPATH') || exit;

use function RRZE\Autoshare\config;
use function RRZE\Autoshare\settings;

class GeneralSettings {
    public function __construct(\RRZE\Autoshare\Settings\Settings $settings) {
        $tab = $settings->addTab(__('General', 'rrze-autoshare'));
        $section = $tab->addSection(__('Active Services', 'rrze-autoshare'));
        $activeServices = config()->get('general.active_services');

        $section->addOption('checkbox-multiple', [
            'name' => $activeServices['setting'],
            'label' => __('Services', 'rrze-autoshare'),
            'description' => __('Select the services that Autoshare should use when publishing content.', 'rrze-autoshare'),
            'options' => settings()->getServices(),
            'default' => $activeServices['default'],
        ]);
    }
}
