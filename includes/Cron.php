<?php

namespace RRZE\Autoshare;

defined('ABSPATH') || exit;

use RRZE\Autoshare\Services\Bluesky\API as Bluesky;

class Cron {
    public static function init() {
        add_action(config()->get('services.bluesky.hooks.refresh_token'), [__CLASS__, 'blueskyRefreshToken']);
        add_action(
            'update_option_' . config()->get('option_name'),
            [__CLASS__, 'syncSchedule'],
            10,
            2
        );
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

    public static function syncSchedule($oldOptions, $newOptions): void {
        self::activateScheduledEvents();
    }

    public static function blueskyRefreshToken() {
        if (!settings()->isServiceActive('bluesky') || !Bluesky::isConnected()) {
            self::clearSchedule();
            return;
        }

        Bluesky::refreshToken();
    }

    public static function clearSchedule() {
        wp_clear_scheduled_hook(config()->get('services.bluesky.hooks.refresh_token'));
    }
}
