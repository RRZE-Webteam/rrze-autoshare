<?php

namespace RRZE\Autoshare\Services\Bluesky;

defined('ABSPATH') || exit;

class Utils
{
    public static function getConnectionStatus()
    {
        if (get_option(API::ACCESS_JWT)) {
            return __('connected', 'rrze-autoshare');
        }
        return __('not connected', 'rrze-autoshare');
    }

    public static function validateUrl($value)
    {
        return filter_var($value, FILTER_VALIDATE_URL);
    }
}
