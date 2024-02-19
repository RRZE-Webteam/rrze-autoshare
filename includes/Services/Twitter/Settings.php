<?php

namespace RRZE\Autoshare\Services\Twitter;

defined('ABSPATH') || exit;

class Settings
{
    protected $settings;

    protected $tab;

    public function __construct(\RRZE\WP\Settings\Settings $settings)
    {
        $this->settings = $settings;

        $this->tab = $this->settings->addTab(__('X (Twitter)', 'rrze-autoshare'));

        $this->sectionMain();

        $this->sectionConsumerKeys();
    }

    private function sectionMain()
    {
        $sectionMain = $this->tab->addSection(
            __('X (Twitter) Settings', 'rrze-autoshare'),
            [
                'description' => self::sectionMainDescription(),
            ]
        );

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
        $sectionMain->addOption('checkbox', [
            'name' => 'twitter_enable_default',
            'label' => __('Enable by default', 'rrze-autoshare'),
            'description' => __('Enable Autoshare by default when publishing content', 'rrze-autoshare'),
            'default' => true
        ]);
        $sectionMain->addOption('checkbox', [
            'name' => 'twitter_featured_image',
            'label' => __('Featured Images', 'rrze-autoshare'),
            'description' => __('Include featured images', 'rrze-autoshare'),
            'default' => true
        ]);
        $sectionMain->addOption('button-link', [
            'name' => 'twitter_authorize_access_url',
            'label' => __('Access', 'rrze-autoshare'),
            'href' => [__NAMESPACE__ . '\API', 'authorizeAccessUrl'],
            'text' => [__NAMESPACE__ . '\API', 'authorizeAccessText'],
            'description' => [__NAMESPACE__ . '\API', 'authorizeAccessDescription'],
            'css' => [
                'input_class' => 'button button-secondary'
            ],
        ]);
    }

    private function sectionConsumerKeys()
    {
        $sectionKeys = $this->tab->addSection(
            __('Consumer Keys', 'rrze-autoshare'),
            [
                'description' => '',
            ]
        );
        $sectionKeys->addOption('text-secure', [
            'name' => 'twitter_api_key',
            'label' => __('API Key', 'rrze-autoshare'),
            'css' => [
                'input_class' => 'regular-text'
            ],
            'placeholder' => __('Paste your API Key here', 'rrze-autoshare')
        ]);
        $sectionKeys->addOption('text-secure', [
            'name' => 'twitter_api_key_secret',
            'label' => __('API Key Secret', 'rrze-autoshare'),
            'css' => [
                'input_class' => 'regular-text'
            ],
            'placeholder' => __('Paste your API Key Secret here', 'rrze-autoshare')
        ]);
    }

    private function sectionMainDescription()
    {
        if (API::isConnected()) {
            return '';
        }
        ob_start(); ?>
        <h4>
            <?php _e('1. Create a Twitter app', 'rrze-autoshare'); ?>
        </h4>
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
            <li><?php _e('Click on Projects & Apps on the left navigation menu and create a new App.', 'rrze-autoshare'); ?></li>
            <li><?php _e('Find the App and click it to show the Settings page for the App.', 'rrze-autoshare'); ?></li>
            <li><?php _e('Edit the User authentication settings of the the App.', 'rrze-autoshare'); ?></li>
            <li><?php _e('Set App permissions to <strong>Read and write</strong>.', 'rrze-autoshare'); ?></li>
            <li><?php _e('Set Types of App to <strong>Web App, Automated App or Bot</strong>.', 'rrze-autoshare'); ?></li>
            <li>
                <?php
                printf(
                    /* translators: %s: Callback URL for Twitter Auth */
                    __('On App info set the <code>Callback URL / Redirect URL</code> to <code>%s</code>.', 'rrze-autoshare'),
                    esc_url(admin_url('admin-post.php?action=rrze_authoshare_authorize_callback'))
                );
                ?>
            </li>
            <li>
                <?php
                printf(
                    /* translators: %s: Site URL. */
                    __('Set the <code>Website URL</code> to <code>%s</code> and click <code>Save</code>.', 'rrze-autoshare'),
                    esc_url(get_site_url())
                );
                ?>
            </li>
            <li><?php _e('Switch from the "Settings" tab to the "Keys and tokens" tab.', 'rrze-autoshare'); ?></li>
            <li><?php _e('Click on the <code>Regenerate</code> button in the <code>Consumer Keys</code> section.', 'rrze-autoshare'); ?></li>
            <li><?php _e('Copy the <code>API Key</code> and <code>API Key Secret</code> values and paste them below.', 'rrze-autoshare'); ?></li>
        </ul>
        <h4><?php _e('2. Connect your Twitter account', 'rrze-autoshare'); ?></h4>
        <ul>
            <li><?php _e('After saving settings, click the <code>Authorize Access</code> button and follow the instructions provided there to connect your Twitter account with this website.', 'rrze-autoshare'); ?></li>
        </ul>
<?php
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }
}
