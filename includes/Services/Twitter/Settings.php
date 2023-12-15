<?php

namespace RRZE\Autoshare\Services\Twitter;

defined('ABSPATH') || exit;

class Settings
{
    protected $settings;

    public function __construct(\RRZE\Autoshare\Options\Settings $settings)
    {
        $this->settings = $settings;

        $tab = $this->settings->addTab(__('Twitter', 'rrze-autoshare'));

        $sectionMain = $tab->addSection(
            __('Twitter Settings', 'rrze-autoshare'),
            [
                'description' => $this->sectionMainDescription()
            ]
        );

        $sectionMain->addOption('text', [
            'name' => 'twitter_api_key',
            'label' => __('API Key', 'rrze-autoshare'),
            'placeholder' => __('paste your API Key here', 'rrze-autoshare')
        ]);
        $sectionMain->addOption('text', [
            'name' => 'twitter_api_secret',
            'label' => __('API Key Secret', 'rrze-autoshare'),
            'placeholder' => __('paste your API Key Secret here', 'rrze-autoshare')
        ]);

        if (!$this->settings->getOption('twitter_api_key') || !$this->settings->getOption('twitter_api_secret')) {
            return;
        }

        $sectionMain->addOption('checkbox-multiple', [
            'name' => 'twitter_post_types',
            'label' => __('Content Types', 'rrze-autoshare'),
            'description' => __('Select the type of content that Autoshare could use.', 'rrze-autoshare'),
            'options' => [
                'post' => __('Posts', 'rrze-autoshare'),
                'page' => __('Pages', 'rrze-autoshare')
            ],
            'default' => ['post']
        ]);
    }

    private function sectionMainDescription()
    {
        ob_start(); ?>
        <h4>
            <a href="https://developer.twitter.com/en/portal/petition/essential/basic-info" target="_blank">
                <?php _e('1. Sign up for a Twitter developer account', 'rrze-autoshare'); ?>
            </a>
        </h4>
        <ul>
            <li><?php _e('Click on "Sign up for Free Account" button to proceed with free access.', 'rrze-autoshare'); ?></li>
            <li><?php _e("Fill out the <code>Describe all of your use cases of Twitter's data and API</code> field.", 'rrze-autoshare'); ?></li>
            <li><?php _e('Click on "Submit" button, it will redirect you to Developer portal.', 'rrze-autoshare'); ?></li>
        </ul>
        <h4><?php _e('2. Configure access to your Twitter app access tokens', 'rrze-autoshare'); ?></h4>
        <ul>
            <li>
                <?php
                printf(
                    /* translators: %1$s: opening HTML <a> link tag, %2$s: closing HTML </a> link tag. */
                    __('Go to the %1$sTwitter developer portal%2$s', 'rrze-autoshare'),
                    '<a href="https://developer.twitter.com/en/portal/dashboard" target="_blank">',
                    '</a>'
                );
                ?>
            </li>
            <li><?php _e('Click on Projects & Apps on the left navigation menu.', 'rrze-autoshare'); ?></li>
            <li><?php _e('Find the App and click it to show the Settings page for the App.', 'rrze-autoshare'); ?></li>
            <li><?php _e('Click "Setup" under User authentication settings to setup Authentication.', 'rrze-autoshare'); ?></li>
            <li><?php _e('Enable <code>OAuth 1.0a</code> and Set App permissions to <strong>Read and write</strong>.', 'rrze-autoshare'); ?></li>
            <li>
                <?php
                printf(
                    /* translators: %s: Site URL. */
                    __('Set the <code>Website URL</code> to <code>%s</code>.', 'rrze-autoshare'),
                    esc_url(get_site_url())
                );
                ?>
            </li>
            <li>
                <?php
                printf(
                    /* translators: %s: Callback URL for Twitter Auth */
                    __('Set the <code>Callback URLs</code> fields to <code>%s</code> and click <code>Save</code>.', 'rrze-autoshare'),
                    esc_url(admin_url('admin-post.php?action=authoshare_authorize_callback'))
                );
                ?>
            </li>
            <li><?php _e('Switch from the "Settings" tab to the "Keys and tokens" tab.', 'rrze-autoshare'); ?></li>
            <li><?php _e('Click on the <code>Generate</code>/<code>Regenerate</code> button in the <code>Consumer Keys</code> section.', 'rrze-autoshare'); ?></li>
            <li><?php _e('Copy the <code>API Key</code> and <code>API Key Secret</code> values and paste them below.', 'rrze-autoshare'); ?></li>
        </ul>

        <h4><?php _e('3. Save settings', 'rrze-autoshare'); ?></h4>
        <ul>
            <li><?php _e('Click the <code>Save Changes</code> button below to save settings.', 'rrze-autoshare'); ?></li>
        </ul>

        <h4><?php _e('4. Connect your Twitter account', 'rrze-autoshare'); ?></h4>
        <ul>
            <li><?php _e('After saving settings, you will see the option to connect your Twitter account.', 'rrze-autoshare'); ?></li>
            <li><?php _e('Click the <code>Connect Twitter account</code> button and follow the instructions provided there to connect your Twitter account with this site.', 'rrze-autoshare'); ?></li>
        </ul>
<?php
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }
}
