<?php

namespace RRZE\Autoshare\Services\Mastodon;

defined('ABSPATH') || exit;

use function RRZE\Autoshare\settings;

class Utils
{
    public static function validateUrl($value)
    {
        return filter_var($value, FILTER_VALIDATE_URL);
    }
}
