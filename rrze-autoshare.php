<?php

/*
Plugin Name:        RRZE Autoshare
Plugin URI:         https://github.com/RRZE-Webteam/rrze-autoshare
Version:            1.6.0
Description:        Automatically share the post title or custom message and a link to the post to Bluesky, Mastodon and X.
Author:             RRZE Webteam
Author URI:         https://blogs.fau.de/webworking/
License:            GNU General Public License Version 3
License URI:        https://www.gnu.org/licenses/gpl-3.0.html
Text Domain:        rrze-autoshare
Domain Path:        /languages
Requires at least:  6.7
Requires PHP:       8.2
*/

namespace RRZE\Autoshare;

defined('ABSPATH') || exit;

// Autoloader
require_once 'vendor/autoload.php';

// Load the plugin's text domain for localization.
add_action('init', fn() => load_plugin_textdomain('rrze-multilang', false, dirname(plugin_basename(__FILE__)) . '/languages'));


// Register activation hook for the plugin
register_activation_hook(__FILE__, __NAMESPACE__ . '\activation');

// Register deactivation hook for the plugin
register_deactivation_hook(__FILE__, __NAMESPACE__ . '\deactivation');

/**
 * Add an action hook for the 'plugins_loaded' hook.
 *
 * This code hooks into the 'plugins_loaded' action hook to execute a callback function when
 * WordPress has fully loaded all active plugins and the theme's functions.php file.
 */
add_action('plugins_loaded', __NAMESPACE__ . '\loaded');

/**
 * Activation callback function.
 */
function activation()
{
    //
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
 * Check system requirements for the plugin.
 *
 * This method checks if the server environment meets the minimum WordPress and PHP version requirements
 * for the plugin to function properly.
 *
 * @return string An error message string if requirements are not met, or an empty string if requirements are satisfied.
 */
function systemRequirements(): string
{
    // Get the global WordPress version.
    global $wp_version;

    // Get the PHP version.
    $phpVersion = phpversion();

    // Initialize an error message string.
    $error = '';

    // Check if the WordPress version is compatible with the plugin's requirement.
    if (!is_wp_version_compatible(plugin()->getRequiresWP())) {
        $error = sprintf(
            /* translators: 1: Server WordPress version number, 2: Required WordPress version number. */
            __('The server is running WordPress version %1$s. The plugin requires at least WordPress version %2$s.', 'rrze-multilang'),
            $wp_version,
            plugin()->getRequiresWP()
        );
    } elseif (!is_php_version_compatible(plugin()->getRequiresPHP())) {
        // Check if the PHP version is compatible with the plugin's requirement.
        $error = sprintf(
            /* translators: 1: Server PHP version number, 2: Required PHP version number. */
            __('The server is running PHP version %1$s. The plugin requires at least PHP version %2$s.', 'rrze-multilang'),
            $phpVersion,
            plugin()->getRequiresPHP()
        );
    }

    // Return the error message string, which will be empty if requirements are satisfied.
    return $error;
}

/**
 * Handle the loading of the plugin.
 *
 * This function is responsible for initializing the plugin, loading text domains for localization,
 * checking system requirements, and displaying error notices if necessary.
 */
function loaded()
{
    // Trigger the 'loaded' method of the main plugin instance.
    plugin()->loaded();

    // Check system requirements and store any error messages.
    if ($error = systemRequirements()) {
        // If there is an error, add an action to display an admin notice with the error message.
        add_action('admin_init', function () use ($error) {
            // Check if the current user has the capability to activate plugins.
            if (current_user_can('activate_plugins')) {
                // Get plugin data to retrieve the plugin's name.
                $pluginName = plugin()->getName();

                // Determine the admin notice tag based on network-wide activation.
                $tag = is_plugin_active_for_network(plugin()->getBaseName()) ? 'network_admin_notices' : 'admin_notices';

                // Add an action to display the admin notice.
                add_action($tag, function () use ($pluginName, $error) {
                    printf(
                        '<div class="notice notice-error"><p>' .
                            /* translators: 1: The plugin name, 2: The error string. */
                            esc_html__('Plugins: %1$s: %2$s', 'rrze-multilang') .
                            '</p></div>',
                        $pluginName,
                        $error
                    );
                });
            }
        });

        // Return to prevent further initialization if there is an error.
        return;
    }

    // If there are no errors, create an instance of the 'Main' class and trigger its 'loaded' method.
    (new Main)->loaded();
}
