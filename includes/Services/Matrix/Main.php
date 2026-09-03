<?php

namespace RRZE\Autoshare\Services\Matrix;

defined('ABSPATH') || exit;

class Main {
    public static function init(): void {
        add_action('init', [__CLASS__, 'initPost']);
    }

    public static function initPost(): void {
        Post::init();
    }
}
