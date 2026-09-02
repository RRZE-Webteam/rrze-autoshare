<?php

namespace RRZE\Autoshare;

defined('ABSPATH') || exit;

use RRZE\Autoshare\Services\Bluesky\API as Bluesky;

class Cron {
    public static function init() {
        add_action(config()->get('services.bluesky.hooks.refresh_token'), [__CLASS__, 'blueskyRefreshToken']);
        add_action('init', [__CLASS__, 'activateScheduledEvents']);
    }

    public static function activateScheduledEvents() {
        $hook = config()->get('services.bluesky.hooks.refresh_token');

        if (!settings()->isServiceActive('bluesky') || !Bluesky::isConnected()) {
            self::clearSchedule();
            return;
        }

        if (!wp_next_scheduled($hook)) {
            wp_schedule_event(time(), 'weekly', $hook);
        }
    }

    public static function blueskyRefreshToken() {
        Bluesky::refreshToken();
    }

    public static function clearSchedule() {
        wp_clear_scheduled_hook(config()->get('services.bluesky.hooks.refresh_token'));
    }
}
