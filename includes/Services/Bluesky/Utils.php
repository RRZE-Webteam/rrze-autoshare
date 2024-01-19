<?php

namespace RRZE\Autoshare\Services\Bluesky;

defined('ABSPATH') || exit;

class Utils
{
    public static function validateUrl($value)
    {
        return filter_var($value, FILTER_VALIDATE_URL);
    }
}
