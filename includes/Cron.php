<?php

namespace RRZE\Autoshare;

defined('ABSPATH') || exit;

use RRZE\Autoshare\Services\Bluesky\API as Bluesky;

/**
 * Class Cron
 *
 * A class for managing scheduled events using WP cron functionality.
 */
class Cron
{
    const BLUESKY_REFRESH_TOKEN = 'rrze_autoshare_bluesky_refresh_token';

    // Initialize the class by setting up action hooks
    public static function init()
    {
        // Add an action hook to run the 'blueskyRefreshToken' method when scheduled.
        add_action(self::BLUESKY_REFRESH_TOKEN, [__CLASS__, 'blueskyRefreshToken']);

        // Add an action hook to activate scheduled events during WP initialization.
        add_action('init', [__CLASS__, 'activateScheduledEvents']);
    }

    // Activate the scheduled event if it's not already scheduled
    public static function activateScheduledEvents()
    {
        // Check if the scheduled event is not already in the queue.
        if (!wp_next_scheduled(self::BLUESKY_REFRESH_TOKEN)) {
            // Schedule the event to run daily starting from the current time.
            wp_schedule_event(time(), 'daily', self::BLUESKY_REFRESH_TOKEN);
        }
    }

    // Method to be executed when the scheduled event is triggered
    public static function blueskyRefreshToken()
    {
        Bluesky::connect();
    }

    // Clear the scheduled event hook
    public static function clearSchedule()
    {
        // Clear the scheduled event hook for the specified action hook.
        wp_clear_scheduled_hook(self::BLUESKY_REFRESH_TOKEN);
    }
}
