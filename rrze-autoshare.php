<?php

/*
Plugin Name:        RRZE Autoshare
Plugin URI:         https://github.com/RRZE-Webteam/rrze-autoshare
Version:            2.0.1
Description:        Automatically shares published WordPress content on Bluesky and Mastodon.
Author:             RRZE-Webteam <webmaster@fau.de>
Author URI:         https://www.wp.rrze.fau.de
License:            GNU General Public License v3
License URI:        https://www.gnu.org/licenses/gpl-3.0.html
Text Domain:        rrze-autoshare
Domain Path:        /languages
Requires at least:  6.7
Requires PHP:       8.2
*/

namespace RRZE\Autoshare;

defined('ABSPATH') || exit;

/**
 * SPL Autoloader (PSR-4).
 *
 * @param string $class The fully-qualified class name.
 * @return void
 */
function autoload(string $class): void {
    $namespaces = [
        __NAMESPACE__ . '\\' => __DIR__ . '/includes/',
    ];

    foreach ($namespaces as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }

        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require $file;
        }
    }
}

spl_autoload_register(__NAMESPACE__ . '\autoload');

// Load the plugin's text domain for localization.
add_action('init', __NAMESPACE__ . '\loadTextdomain');


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
function activation(bool $networkWide = false) {
    return;
}

/**
 * Deactivation callback function.
 */
function deactivation(bool $networkWide = false) {
    if (!is_multisite() || !$networkWide) {
        Cron::clearSchedule();
        return;
    }

    $siteIds = get_sites([
        'fields' => 'ids',
    ]);

    foreach ($siteIds as $siteId) {
        switch_to_blog($siteId);
        Cron::clearSchedule();
        restore_current_blog();
    }
}

/**
 * Instantiate Plugin class.
 * @return object Plugin
 */
function plugin() {
    static $instance;
    if (null === $instance) {
        $instance = new Plugin(__FILE__);
    }
    return $instance;
}

/**
 * Instantiate Config class.
 * @return object Config
 */
function config() {
    static $instance;
    if (null === $instance) {
        $instance = new Config();
    }
    return $instance;
}

/**
 * Instantiate Settings class.
 * @return object Settings
 */
function settings() {
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
function systemRequirements(): string {
    if (!is_wp_version_compatible(plugin()->getRequiresWP())) {
        return sprintf(
            /* translators: 1: Server WordPress version number, 2: Required WordPress version number. */
            __('The server is running WordPress version %1$s. The plugin requires at least WordPress version %2$s.', 'rrze-autoshare'),
            wp_get_wp_version(),
            plugin()->getRequiresWP()
        );
    }

    if (!is_php_version_compatible(plugin()->getRequiresPHP())) {
        return sprintf(
            /* translators: 1: Server PHP version number, 2: Required PHP version number. */
            __('The server is running PHP version %1$s. The plugin requires at least PHP version %2$s.', 'rrze-autoshare'),
            PHP_VERSION,
            plugin()->getRequiresPHP()
        );
    }

    return '';
}

/**
 * Handle the loading of the plugin.
 *
 * This function is responsible for initializing the plugin, loading text domains for localization,
 * checking system requirements, and displaying error notices if necessary.
 */
function loaded() {
    // Trigger the 'loaded' method of the main plugin instance.
    plugin()->loaded();

    $wpCompatible = is_wp_version_compatible(plugin()->getRequiresWP());
    $phpCompatible = is_php_version_compatible(plugin()->getRequiresPHP());

    if (!$wpCompatible || !$phpCompatible) {
        add_action('admin_init', __NAMESPACE__ . '\\addRequirementsNotice');
        return;
    }

    // If there are no errors, create an instance of the 'Main' class and trigger its 'loaded' method.
    (new Main)->loaded();
}

function addRequirementsNotice(): void {
    if (!current_user_can('activate_plugins')) {
        return;
    }

    $hook = is_plugin_active_for_network(plugin()->getBaseName())
        ? 'network_admin_notices'
        : 'admin_notices';
    add_action($hook, __NAMESPACE__ . '\\renderRequirementsNotice');
}

function renderRequirementsNotice(): void {
    $error = systemRequirements();
    if ($error === '') {
        return;
    }

    printf(
        '<div class="notice notice-error"><p>' .
            /* translators: 1: The plugin name, 2: The error string. */
            esc_html__('Plugins: %1$s: %2$s', 'rrze-autoshare') .
            '</p></div>',
        esc_html(plugin()->getName()),
        esc_html($error)
    );
}

/**
 * Load plugin text domain.
 */
function loadTextdomain(): void {
    load_plugin_textdomain(
        config()->get('text_domain'),
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}
