<?php

namespace RRZE\Autoshare;

defined('ABSPATH') || exit;

use RRZE\Autoshare\Services\Bluesky;

/**
 * Class Cron
 *
 * A class for managing scheduled events using WP cron functionality.
 */
class Cron
{
    const BSKY_REFRESH_TOKEN = 'rrze_autoshare_bluesky_refresh_token';

    // Initialize the class by setting up action hooks
    public static function init()
    {
        // Add an action hook to run the 'bskyRefreshToken' method when scheduled.
        add_action(self::BSKY_REFRESH_TOKEN, [__CLASS__, 'bskyRefreshToken']);

        // Add an action hook to activate scheduled events during WP initialization.
        add_action('init', [__CLASS__, 'activateScheduledEvents']);
    }

    // Activate the scheduled event if it's not already scheduled
    public static function activateScheduledEvents()
    {
        // Check if the scheduled event is not already in the queue.
        if (!wp_next_scheduled(self::BSKY_REFRESH_TOKEN)) {
            // Schedule the event to run weekly starting from the current time.
            wp_schedule_event(time(), 'weekly', self::BSKY_REFRESH_TOKEN);
        }
    }

    // Method to be executed when the scheduled event is triggered
    public static function bskyRefreshToken()
    {
        Bluesky::connect();
    }

    // Clear the scheduled event hook
    public static function clearSchedule()
    {
        // Clear the scheduled event hook for the specified action hook.
        wp_clear_scheduled_hook(self::BSKY_REFRESH_TOKEN);
    }
}
