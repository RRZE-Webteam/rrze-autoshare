<?php

namespace RRZE\Autoshare;

defined('ABSPATH') || exit;

class Main
{
    /**
     * __construct
     */
    public function __construct()
    {
        add_filter('plugin_action_links_' . plugin()->getBaseName(), [$this, 'settingsLink']);

        add_action('admin_enqueue_scripts', [$this, 'adminEnqueueScripts']);

        settings();

        // Cron::init();
    }

    /**
     * Add the settings link to the list of plugins.
     *
     * @param array $links
     * @return void
     */
    public function settingsLink($links)
    {
        $settingsLink = sprintf(
            '<a href="%s">%s</a>',
            admin_url('options-general.php?page=rrze_autoshare'),
            __('Settings', 'rrze_autoshare')
        );
        array_unshift($links, $settingsLink);
        return $links;
    }

    public function adminEnqueueScripts()
    {
        $screen = get_current_screen();
        if (is_null($screen)) {
            return;
        }
    }
}
