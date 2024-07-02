<?php

/*
Plugin Name:      RRZE Autoshare
Plugin URI:       https://github.com/RRZE-Webteam/rrze-autoshare
Description:      Automatically share the post title or custom message and a link to the post to Bluesky, Twitter and other social media.
Version:          1.5.0
Author:           RRZE-Webteam
Author URI:       https://blogs.fau.de/webworking/
License:          GNU General Public License v3.0
License URI:      https://www.gnu.org/licenses/gpl-3.0.en.html
Domain Path:      /languages
Text Domain:      rrze-autoshare
*/

namespace RRZE\Autoshare;

defined('ABSPATH') || exit;

use RRZE\WP\Plugin\Plugin;

const RRZE_PHP_VERSION = '8.2';
const RRZE_WP_VERSION = '6.5';

// Autoloader
require_once 'vendor/autoload.php';

register_activation_hook(__FILE__, __NAMESPACE__ . '\activation');
register_deactivation_hook(__FILE__, __NAMESPACE__ . '\deactivation');

add_action('plugins_loaded', __NAMESPACE__ . '\loaded');

/**
 * loadTextdomain
 */
function loadTextdomain()
{
    load_plugin_textdomain(
        'rrze-autoshare',
        false,
        sprintf('%s/languages/', dirname(plugin_basename(__FILE__)))
    );
}

/**
 * System requirements verification.
 * @return string Return an error message.
 */
function systemRequirements(): string
{
    global $wp_version;
    // Strip off any -alpha, -RC, -beta, -src suffixes.
    list($wpVersion) = explode('-', $wp_version);
    $phpVersion = phpversion();
    $error = '';
    if (!is_php_version_compatible(RRZE_PHP_VERSION)) {
        $error = sprintf(
            /* translators: %1$s: Server PHP version number, %2$s: Required PHP version number. */
            __('The server is running PHP version %1$s. The Plugin requires at least PHP version %2$s.', 'rrze-autoshare'),
            $phpVersion,
            RRZE_PHP_VERSION
        );
    } elseif (!is_wp_version_compatible(RRZE_WP_VERSION)) {
        $error = sprintf(
            /* translators: %1$s: Server WordPress version number, %2$s: Required WordPress version number. */
            __('The server is running WordPress version %1$s. The Plugin requires at least WordPress version %2$s.', 'rrze-autoshare'),
            $wpVersion,
            RRZE_WP_VERSION
        );
    }
    return $error;
}

/**
 * Activation callback function.
 */
function activation()
{
    loadTextdomain();
    if ($error = systemRequirements()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            sprintf(
                /* translators: %1$s: The plugin name, %2$s: The error string. */
                __('Plugins: %1$s: %2$s', 'rrze-autoshare'),
                plugin_basename(__FILE__),
                $error
            )
        );
    }
}

/**
 * Deactivation callback function.
 */
function deactivation()
{
    //
}

/**
 * Instantiate Plugin class.
 * @return object Plugin
 */
function plugin()
{
    static $instance;
    if (null === $instance) {
        $instance = new Plugin(__FILE__);
    }
    return $instance;
}

/**
 * Instantiate Settings class.
 * @return object Settings
 */
function settings()
{
    static $instance;
    if (null === $instance) {
        $instance = new Settings();
    }
    return $instance;
}

/**
 * Execute on 'plugins_loaded' API/action.
 * @return void
 */
function loaded()
{
    loadTextdomain();
    plugin()->loaded();
    if ($error = systemRequirements()) {
        add_action('admin_init', function () use ($error) {
            if (current_user_can('activate_plugins')) {
                $pluginData = get_plugin_data(plugin()->getFile());
                $pluginName = $pluginData['Name'];
                $tag = is_plugin_active_for_network(plugin()->getBaseName()) ? 'network_admin_notices' : 'admin_notices';
                add_action($tag, function () use ($pluginName, $error) {
                    printf(
                        '<div class="notice notice-error"><p>' .
                            /* translators: %1$s: The plugin name, %2$s: The error string. */
                            __('Plugins: %1$s: %2$s', 'rrze-autoshare') .
                            '</p></div>',
                        esc_html($pluginName),
                        esc_html($error)
                    );
                });
            }
        });
        return;
    }
    new Main();
}
