<?php

namespace RRZE\Autoshare\Services\Mastodon;

defined('ABSPATH') || exit;

use function RRZE\Autoshare\config;
use function RRZE\Autoshare\settings;

class Main {
    public static function init() {
        add_action('init', [__CLASS__, 'initPost']);
    }

    public static function initPost() {
        Post::init();
    }

    public static function isConnected() {
        return API::isConnected();
    }

    public static function isEnabled($postId) {
        return Post::isEnabled($postId);
    }

    public static function isSent($postId) {
        return Post::isSent($postId);
    }

    public static function isPublished($postId) {
        return Post::isPublished($postId);
    }

}
