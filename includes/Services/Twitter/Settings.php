<?php

namespace RRZE\Autoshare\Services\Twitter;

defined('ABSPATH') || exit;

class Settings
{
    protected $settings;

    protected $tab;

    public function __construct(\RRZE\Autoshare\Options\Settings $settings)
    {
        $this->settings = $settings;

        $this->tab = $this->settings->addTab(__('Twitter', 'rrze-autoshare'));

        // Are API keys available?
        // if ($this->settings->getOption('twitter_api_key') && $this->settings->getOption('twitter_api_key_secret')) {
        $this->sectionMain();
        // }

        $this->sectionKeys();
    }

    private function sectionMain()
    {
        $sectionMain = $this->tab->addSection(
            __('Main', 'rrze-autoshare'),
            [
                'description' => $this->sectionMainDescription()
            ]
        );

        $sectionMain->addOption('checkbox-multiple', [
            'name' => 'twitter_post_types',
            'label' => __('Content Types', 'rrze-autoshare'),
            'description' => __('Select the type of content that Autoshare could use to write a tweet.', 'rrze-autoshare'),
            'options' => [
                'post' => __('Posts', 'rrze-autoshare'),
                'page' => __('Pages', 'rrze-autoshare')
            ],
            'default' => ['post']
        ]);

        $sectionMain->addOption('checkbox', [
            'name' => 'twitter_enable_default',
            'label' => __('Enable by default', 'rrze-autoshare'),
            'description' => __('Enable Autoshare by default when publishing content', 'rrze-autoshare'),
            'default' => true
        ]);

        $sectionMain->addOption('checkbox', [
            'name' => 'twitter_enable_upload',
            'label' => __('Image setting', 'rrze-autoshare'),
            'description' => __('Always add the featured image to tweets', 'rrze-autoshare'),
            'default' => true
        ]);

        $sectionMain->addOption('twitter-account', [
            'name' => 'twitter_account',
            'label' => __('Twitter account', 'rrze-autoshare')
        ]);        
    }

    private function sectionKeys()
    {
        $sectionKeys = $this->tab->addSection(
            __('Twitter Consumer Keys', 'rrze-autoshare'),
            [
                'description' => $this->sectionKeysDescription()
            ]
        );

        $sectionKeys->addOption('text-secure', [
            'name' => 'twitter_api_key',
            'label' => __('API Key', 'rrze-autoshare'),
            'placeholder' => __('paste your API Key here', 'rrze-autoshare')
        ]);

        $sectionKeys->addOption('text-secure', [
            'name' => 'twitter_api_key_secret',
            'label' => __('API Key Secret', 'rrze-autoshare'),
            'placeholder' => __('paste your API Key Secret here', 'rrze-autoshare')
        ]);
    }

    private function sectionMainDescription()
    {
        ob_start(); ?>
        <p><?php _e('Think of these as the user name and password that represents your App when making API requests.', 'rrze-autoshare'); ?>
        <?php
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }

    private function sectionKeysDescription()
    {
        ob_start(); ?>
        <p><?php _e('Think of these as the user name and password that represents your App when making API requests.', 'rrze-autoshare'); ?>
        <h4>1.
            <a href="https://developer.twitter.com" target="_blank">
                <?php _e('Sign up for a X (Twitter) developer account', 'rrze-autoshare'); ?>
            </a>
        </h4>
        <h4><?php _e('2. Configure the Twitter Consumer Keys', 'rrze-autoshare'); ?></h4>
        <ul>
            <li>
                <?php
                printf(
                    /* translators: %1$s: opening HTML <a> link tag, %2$s: closing HTML </a> link tag. */
                    __('Go to the %1$sX (Twitter) developer portal%2$s and Sign up for Free Account.', 'rrze-autoshare'),
                    '<a href="https://developer.twitter.com/en/portal/dashboard" target="_blank">',
                    '</a>'
                );
                ?>
            </li>
            <li><?php _e('Complete the "Developer agreement & policy" and click on the <code>Submit</code> button.', 'rrze-autoshare'); ?></li>
            <li><?php _e('Click on Projects & Apps on the left navigation menu.', 'rrze-autoshare'); ?></li>
            <li><?php _e('Note: The free plan only supports one App. If the App quota has been exceeded, an App must be deleted to create a new one.', 'rrze-autoshare'); ?></li>
            <li><?php _e('In the section Standalone Apps click on the <code>Create App</code> button.', 'rrze-autoshare'); ?></li>
            <li><?php _e('Name the App and click on the <code>Next</code> button.', 'rrze-autoshare'); ?></li>
            <li><?php _e('Copy the API Key and API Key Secret. These values will be required in the plugin settings. Click on the <code>App settings</code> button.', 'rrze-autoshare'); ?></li>
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
                    esc_url(admin_url('admin-post.php?action=rrze_authoshare_authorize_callback'))
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
