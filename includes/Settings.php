<?php

namespace RRZE\Autoshare;

defined('ABSPATH') || exit;

use RRZE\Autoshare\Services\Bluesky\API as BlueskyAPI;
use RRZE\Autoshare\Services\Mastodon\API as MastodonAPI;

class Settings {
    protected array $supportedPostTypes = [];

    public function __construct() {
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_init', [$this, 'connectMastodonAPI']);
        add_action(
            'admin_post_' . config()->get('services.bluesky.authorization.authorize_action'),
            [$this, 'authorizeBlueskyAccess']
        );
        add_action(
            'admin_post_' . config()->get('services.bluesky.authorization.revoke_action'),
            [$this, 'revokeBlueskyAccess']
        );
    }

    public function loaded() {
        add_action('init', [$this, 'setPostTypes']);
    }

    public function addAdminMenu() {
        add_submenu_page(
            config()->get('admin_parent_slug'),
            __('Autoshare Settings', 'rrze-autoshare'),
            __('RRZE-Autoshare', 'rrze-autoshare'),
            'manage_options',
            config()->get('admin_page_slug'),
            [$this, 'renderSettingsPage'],
            config()->get('admin_menu_position')
        );
    }

    public function registerSettings() {
        $this->setPostTypes();

        register_setting(
            config()->get('slug') . '_settings',
            config()->get('option_name'),
            [
                'sanitize_callback' => [$this, 'sanitizeOptions'],
            ]
        );

        $this->registerGeneralSettings();
        $this->registerBlueskySettings();
        $this->registerMastodonSettings();
    }

    public function sanitizeOptions($submittedOptions) {
        $options = $this->getOptions();
        $submittedOptions = is_array($submittedOptions) ? $submittedOptions : [];
        $activeServices = config()->get('general.active_services');

        if (array_key_exists($activeServices['setting'], $submittedOptions)) {
            $selectedServices = array_values(
                array_intersect(
                    array_keys($this->getServices()),
                    array_map('sanitize_key', (array) $submittedOptions[$activeServices['setting']])
                )
            );
            $options[$activeServices['setting']] = [];

            foreach ($selectedServices as $service) {
                if ($this->isServiceAuthorized($service)) {
                    $options[$activeServices['setting']][] = $service;
                }
            }
        }

        foreach (config()->get('services', []) as $service => $serviceConfig) {
            $settings = $serviceConfig['settings'];
            $defaults = $serviceConfig['defaults'];

            if (isset($settings['domain']) && array_key_exists($settings['domain'], $submittedOptions)) {
                $domain = esc_url_raw($submittedOptions[$settings['domain']] ?? '');
                if ('bluesky' === $service) {
                    $domain = $defaults['domain'];
                }
                $options[$settings['domain']] = $domain ?: $defaults['domain'];
            }

            if (isset($settings['username']) && array_key_exists($settings['username'], $submittedOptions)) {
                $options[$settings['username']] = sanitize_text_field(
                    $submittedOptions[$settings['username']] ?? ''
                );
            }

            if (array_key_exists($settings['post_types'], $submittedOptions)) {
                $options[$settings['post_types']] = array_values(
                    array_intersect(
                        array_keys($this->getPostTypes()),
                        array_map('sanitize_key', (array) $submittedOptions[$settings['post_types']])
                    )
                );
            }

            if (array_key_exists($settings['featured_image'], $submittedOptions)) {
                $options[$settings['featured_image']] = !empty($submittedOptions[$settings['featured_image']]);
            }

            if (array_key_exists($settings['format'], $submittedOptions)) {
                $format = sanitize_textarea_field($submittedOptions[$settings['format']]);
                if ('' === $format) {
                    add_settings_error(
                        config()->get('option_name'),
                        $settings['format'],
                        __('The format cannot be empty.', 'rrze-autoshare')
                    );
                } else {
                    $options[$settings['format']] = $format;
                }
            }
        }

        if (
            array_key_exists($activeServices['setting'], $submittedOptions)
            && !in_array('bluesky', $options[$activeServices['setting']], true)
        ) {
            Cron::clearSchedule();
        }

        return $options;
    }

    public function getOption($option) {
        $options = $this->getOptions();

        return $options[$option] ?? null;
    }

    public function getOptions() {
        $defaults = $this->getDefaultOptions();
        $options = get_option(config()->get('option_name'), []);
        $options = is_array($options) ? $options : [];

        return wp_parse_args(array_intersect_key($options, $defaults), $defaults);
    }

    public function getServices() {
        $services = [];

        foreach (config()->get('services', []) as $slug => $service) {
            $services[$slug] = __($service['label'], 'rrze-autoshare');
        }

        return $services;
    }

    public function getPostTypes() {
        return $this->supportedPostTypes;
    }

    public function isServiceActive(string $service): bool {
        return $this->isServiceAuthorized($service)
            && in_array(
                $service,
                (array) $this->getOption(config()->get('general.active_services.setting')),
                true
            );
    }

    public function isServiceAuthorized(string $service): bool {
        $callback = config()->get('services.' . $service . '.authentication.connection_callback');

        return is_callable($callback) && (bool) call_user_func($callback);
    }

    public function deactivateService(string $service): void {
        $activeServices = config()->get('general.active_services');
        $options = $this->getOptions();
        $active = [];

        foreach ((array) $options[$activeServices['setting']] as $activeService) {
            if ($activeService !== $service) {
                $active[] = $activeService;
            }
        }

        if ($active === $options[$activeServices['setting']]) {
            return;
        }

        $options[$activeServices['setting']] = $active;
        update_option(config()->get('option_name'), $options);

        if ('bluesky' === $service) {
            Cron::clearSchedule();
        }
    }

    public function setPostTypes() {
        $defaultPostTypes = config()->get('default_post_types');
        $filteredPostTypes = apply_filters('rrze_autoshare_supported_post_types', $defaultPostTypes);
        if (empty($filteredPostTypes) || !is_array($filteredPostTypes)) {
            $filteredPostTypes = $defaultPostTypes;
        }

        $commonTypes = array_intersect($filteredPostTypes, $defaultPostTypes);
        if (count($commonTypes) !== count($defaultPostTypes)) {
            $filteredPostTypes = $defaultPostTypes;
        }

        $this->supportedPostTypes = [];
        $availablePostTypes = get_post_types(['public' => true], 'objects');
        foreach ($availablePostTypes as $postType) {
            if (in_array($postType->name, config()->get('excluded_post_types'), true)) {
                continue;
            }

            if (in_array($postType->name, $filteredPostTypes, true)) {
                $this->supportedPostTypes[$postType->name] = $postType->labels->name;
            }
        }
    }

    public function renderSettingsPage() {
        $tab = $this->getCurrentTab();
        ?>
        <div class="wrap rrze-autoshare-settings">
            <h1><?php esc_html_e('Autoshare Settings', 'rrze-autoshare'); ?></h1>
            <?php settings_errors(config()->get('option_name')); ?>
            <?php $this->renderTabNavigation($tab); ?>
            <form action="options.php" method="post">
                <?php settings_fields(config()->get('slug') . '_settings'); ?>
                <input type="hidden" name="_wp_http_referer" value="<?php echo esc_url($this->getSettingsUrl($tab)); ?>">
                <?php do_settings_sections($this->getSettingsPage($tab)); ?>
                <?php submit_button(); ?>
            </form>
            <?php $this->renderServiceAccess($tab); ?>
        </div>
        <?php
    }

    public function renderCheckboxMultipleField($args) {
        $name = $args['name'];
        $value = (array) $this->getOption($name);
        ?>
        <fieldset>
            <input name="<?php echo esc_attr(config()->get('option_name') . '[' . $name . '][]'); ?>" type="hidden" value="">
            <?php foreach ($args['options'] as $key => $label) { ?>
                <?php $disabled = in_array($key, $args['disabled_options'] ?? [], true); ?>
                <label for="<?php echo esc_attr($name . '_' . $key); ?>">
                    <input
                        id="<?php echo esc_attr($name . '_' . $key); ?>"
                        name="<?php echo esc_attr(config()->get('option_name') . '[' . $name . '][]'); ?>"
                        type="checkbox"
                        value="<?php echo esc_attr($key); ?>"
                        <?php checked(!$disabled && in_array($key, $value, true)); ?>
                        <?php disabled($disabled); ?>
                    >
                    <?php echo esc_html($label); ?>
                </label><br>
                <?php if ($disabled) { ?>
                    <span class="description"><?php esc_html_e('Disabled because no authorization is available.', 'rrze-autoshare'); ?></span><br>
                <?php } ?>
            <?php } ?>
            <?php if (!empty($args['description'])) { ?>
                <p class="description"><?php echo esc_html($args['description']); ?></p>
            <?php } ?>
        </fieldset>
        <?php
    }

    public function renderTextField($args) {
        $name = $args['name'];
        ?>
        <input
            id="<?php echo esc_attr($name); ?>"
            name="<?php echo esc_attr(config()->get('option_name') . '[' . $name . ']'); ?>"
            type="text"
            value="<?php echo esc_attr($this->getOption($name)); ?>"
            class="regular-text"
            <?php echo !empty($args['readonly']) ? 'readonly' : ''; ?>
        >
        <?php if (!empty($args['description'])) { ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php } ?>
        <?php
    }

    public function renderCheckboxField($args) {
        $name = $args['name'];
        ?>
        <input name="<?php echo esc_attr(config()->get('option_name') . '[' . $name . ']'); ?>" type="hidden" value="0">
        <label for="<?php echo esc_attr($name); ?>">
            <input
                id="<?php echo esc_attr($name); ?>"
                name="<?php echo esc_attr(config()->get('option_name') . '[' . $name . ']'); ?>"
                type="checkbox"
                value="1"
                <?php checked((bool) $this->getOption($name)); ?>
            >
            <?php echo esc_html($args['description']); ?>
        </label>
        <?php
    }

    public function renderTextareaField($args) {
        $name = $args['name'];
        ?>
        <textarea
            id="<?php echo esc_attr($name); ?>"
            name="<?php echo esc_attr(config()->get('option_name') . '[' . $name . ']'); ?>"
            class="large-text code"
            rows="5"
        ><?php echo esc_textarea($this->getOption($name)); ?></textarea>
        <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php
    }

    public function authorizeBlueskyAccess() {
        $authorization = config()->get('services.bluesky.authorization');
        $this->verifyServiceRequest($authorization['nonce_action'], $authorization['nonce_field']);

        $identifier = sanitize_text_field(wp_unslash($_POST[$authorization['identifier_field']] ?? ''));
        $password = sanitize_text_field(wp_unslash($_POST[$authorization['password_field']] ?? ''));
        $authorized = $identifier !== '' && $password !== '' && BlueskyAPI::authorize($identifier, $password);

        $this->redirectToServiceTab('bluesky', $authorized ? 'success' : 'failed');
    }

    public function revokeBlueskyAccess() {
        $authorization = config()->get('services.bluesky.authorization');
        $this->verifyServiceRequest($authorization['nonce_action'], $authorization['nonce_field']);
        BlueskyAPI::revoke();

        $this->redirectToServiceTab('bluesky', 'revoked');
    }

    public function connectMastodonAPI() {
        if ('mastodon' !== $this->getCurrentTab() || !current_user_can('manage_options')) {
            return;
        }

        MastodonAPI::connect();
    }

    private function registerGeneralSettings() {
        $page = $this->getSettingsPage('general');
        $activeServices = config()->get('general.active_services');

        add_settings_section('rrze_autoshare_active_services', __('Active Services', 'rrze-autoshare'), null, $page);
        add_settings_field(
            $activeServices['setting'],
            __('Services', 'rrze-autoshare'),
            [$this, 'renderCheckboxMultipleField'],
            $page,
            'rrze_autoshare_active_services',
            [
                'name' => $activeServices['setting'],
                'options' => $this->getServices(),
                'disabled_options' => $this->getUnauthorizedServices(),
                'description' => __('Select the services that Autoshare should use when publishing content.', 'rrze-autoshare'),
            ]
        );
    }

    private function registerBlueskySettings() {
        $service = config()->get('services.bluesky');
        $settings = $service['settings'];
        $page = $this->getSettingsPage('bluesky');

        add_settings_section('rrze_autoshare_bluesky', __('Bluesky Settings', 'rrze-autoshare'), [$this, 'renderBlueskySectionDescription'], $page);
        add_settings_field(
            $settings['domain'],
            __('Service URL', 'rrze-autoshare'),
            [$this, 'renderTextField'],
            $page,
            'rrze_autoshare_bluesky',
            [
                'name' => $settings['domain'],
                'description' => __('The URL of the Bluesky service.', 'rrze-autoshare'),
                'readonly' => true,
            ]
        );
        $this->registerServicePostTypeField('bluesky', $page, 'rrze_autoshare_bluesky');
        $this->registerServiceFeaturedImageField('bluesky', $page, 'rrze_autoshare_bluesky');
        $this->registerServiceFormatField('bluesky', $page);
    }

    private function registerMastodonSettings() {
        $service = config()->get('services.mastodon');
        $settings = $service['settings'];
        $page = $this->getSettingsPage('mastodon');

        add_settings_section('rrze_autoshare_mastodon', __('Mastodon Settings', 'rrze-autoshare'), [$this, 'renderMastodonSectionDescription'], $page);
        add_settings_field(
            $settings['domain'],
            __('Service URL', 'rrze-autoshare'),
            [$this, 'renderTextField'],
            $page,
            'rrze_autoshare_mastodon',
            [
                'name' => $settings['domain'],
                'description' => __('The URL of the Mastodon service.', 'rrze-autoshare'),
            ]
        );
        add_settings_field(
            $settings['username'],
            __('Username', 'rrze-autoshare'),
            [$this, 'renderTextField'],
            $page,
            'rrze_autoshare_mastodon',
            [
                'name' => $settings['username'],
                'description' => __('The Mastodon account username.', 'rrze-autoshare'),
            ]
        );
        $this->registerServicePostTypeField('mastodon', $page, 'rrze_autoshare_mastodon');
        $this->registerServiceFeaturedImageField('mastodon', $page, 'rrze_autoshare_mastodon');
        $this->registerServiceFormatField('mastodon', $page);
    }

    private function registerServicePostTypeField(string $service, string $page, string $section) {
        $setting = config()->get('services.' . $service . '.settings.post_types');

        add_settings_field(
            $setting,
            __('Content Types', 'rrze-autoshare'),
            [$this, 'renderCheckboxMultipleField'],
            $page,
            $section,
            [
                'name' => $setting,
                'options' => $this->getPostTypes(),
                'description' => __('Select the type of content that Autoshare could use.', 'rrze-autoshare'),
            ]
        );
    }

    private function registerServiceFeaturedImageField(string $service, string $page, string $section) {
        $setting = config()->get('services.' . $service . '.settings.featured_image');

        add_settings_field(
            $setting,
            __('Featured Images', 'rrze-autoshare'),
            [$this, 'renderCheckboxField'],
            $page,
            $section,
            [
                'name' => $setting,
                'description' => __('Include featured images', 'rrze-autoshare'),
            ]
        );
    }

    private function registerServiceFormatField(string $service, string $page) {
        $content = config()->get('services.' . $service . '.content');
        $setting = config()->get('services.' . $service . '.settings.format');
        $description = sprintf(
            /* translators: 1: Accepted content type, 2: Maximum number of characters. */
            __('Accepted content type: %1$s. Maximum post length: %2$d characters.', 'rrze-autoshare'),
            __('Text', 'rrze-autoshare'),
            $content['max_length']
        );
        if (!empty($content['length_is_instance_specific'])) {
            $description .= ' ' . __('The actual limit can differ depending on the Mastodon instance.', 'rrze-autoshare');
        }

        add_settings_section(
            'rrze_autoshare_' . $service . '_format',
            __('Post Format', 'rrze-autoshare'),
            [$this, 'renderFormatSectionDescription'],
            $page,
            [
                'description' => $description,
            ]
        );
        add_settings_field(
            $setting,
            __('Format', 'rrze-autoshare'),
            [$this, 'renderTextareaField'],
            $page,
            'rrze_autoshare_' . $service . '_format',
            [
                'name' => $setting,
                'description' => __('Use the placeholders {title}, {excerpt}, {url}, and {tags}. Empty lines are removed automatically.', 'rrze-autoshare'),
            ]
        );
    }

    private function renderTabNavigation(string $currentTab) {
        ?>
        <h2 class="nav-tab-wrapper">
            <?php foreach ($this->getTabs() as $tab => $label) { ?>
                <a href="<?php echo esc_url($this->getSettingsUrl($tab)); ?>" class="nav-tab <?php echo $tab === $currentTab ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html($label); ?>
                </a>
            <?php } ?>
        </h2>
        <?php
    }

    public function renderBlueskySectionDescription() {
        echo wp_kses_post(__('Bluesky, also known as Bluesky Social, is a social microblogging platform.<br>Please complete the settings fields so that Autoshare can read and write to the Bluesky timeline.', 'rrze-autoshare'));
    }

    public function renderMastodonSectionDescription() {
        echo wp_kses_post(__('Mastodon is a free social networking service with microblogging features.<br>Please complete the settings fields so that Autoshare can read and write to the Mastodon timeline.', 'rrze-autoshare'));
    }

    public function renderFormatSectionDescription($args) {
        if (!empty($args['description'])) {
            echo '<p>' . esc_html($args['description']) . '</p>';
        }
    }

    private function renderServiceAccess(string $tab) {
        if ('bluesky' === $tab) {
            $this->renderBlueskyAccess();
        } elseif ('mastodon' === $tab) {
            $this->renderMastodonAccess();
        }
    }

    private function renderBlueskyAccess() {
        $authorization = config()->get('services.bluesky.authorization');
        ?>
        <hr>
        <h2><?php esc_html_e('Access', 'rrze-autoshare'); ?></h2>
        <?php $this->renderBlueskyAuthorizationNotice(); ?>
        <?php if (BlueskyAPI::isConnected()) { ?>
            <p><?php esc_html_e('You’ve authorized Autoshare to read and write to the Bluesky timeline.', 'rrze-autoshare'); ?></p>
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                <input type="hidden" name="action" value="<?php echo esc_attr($authorization['revoke_action']); ?>">
                <?php wp_nonce_field($authorization['nonce_action'], $authorization['nonce_field']); ?>
                <?php submit_button(__('Revoke Access', 'rrze-autoshare'), 'secondary', 'submit', false); ?>
            </form>
        <?php } else { ?>
            <p><?php esc_html_e('Authorize Autoshare to read and write to the Bluesky timeline.', 'rrze-autoshare'); ?></p>
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                <input type="hidden" name="action" value="<?php echo esc_attr($authorization['authorize_action']); ?>">
                <p>
                    <label for="rrze-autoshare-bluesky-identifier"><?php esc_html_e('Username or email address', 'rrze-autoshare'); ?></label><br>
                    <input id="rrze-autoshare-bluesky-identifier" name="<?php echo esc_attr($authorization['identifier_field']); ?>" type="text" class="regular-text" autocomplete="username" required>
                </p>
                <p>
                    <label for="rrze-autoshare-bluesky-app-password"><?php esc_html_e('Bluesky App Password', 'rrze-autoshare'); ?></label><br>
                    <input id="rrze-autoshare-bluesky-app-password" name="<?php echo esc_attr($authorization['password_field']); ?>" type="password" class="regular-text" autocomplete="current-password" required>
                </p>
                <p class="description">
                    <?php
                    echo wp_kses(
                        sprintf(
                            /* translators: 1: Bluesky App Password settings URL, 2: Bluesky App Password information URL. */
                            __('Create a separate <a href="%1$s" target="_blank" rel="noopener noreferrer">Bluesky App Password</a>. Do not enter the Bluesky account password. <a href="%2$s" target="_blank" rel="noopener noreferrer">Learn more about App Passwords</a>. The entered data is used only once for authorization and is not saved.', 'rrze-autoshare'),
                            esc_url(config()->get('services.bluesky.app_password.settings_url')),
                            esc_url(config()->get('services.bluesky.app_password.info_url'))
                        ),
                        [
                            'a' => [
                                'href' => true,
                                'rel' => true,
                                'target' => true,
                            ],
                        ]
                    );
                    ?>
                </p>
                <?php wp_nonce_field($authorization['nonce_action'], $authorization['nonce_field']); ?>
                <?php submit_button(__('Authorize Access', 'rrze-autoshare'), 'secondary', 'submit', false); ?>
            </form>
        <?php } ?>
        <?php
    }

    private function renderMastodonAccess() {
        ?>
        <hr>
        <h2><?php esc_html_e('Access', 'rrze-autoshare'); ?></h2>
        <p>
            <a href="<?php echo esc_url(MastodonAPI::authorizeAccessUrl()); ?>" class="button button-secondary">
                <?php echo esc_html(MastodonAPI::authorizeAccessText()); ?>
            </a>
        </p>
        <p class="description"><?php echo esc_html(MastodonAPI::authorizeAccessDescription()); ?></p>
        <?php
    }

    private function renderBlueskyAuthorizationNotice() {
        $status = filter_input(INPUT_GET, config()->get('services.bluesky.authorization.notice_field'), FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        if ('success' === $status) {
            echo '<div class="notice notice-success inline"><p>' . esc_html__('Bluesky access was authorized.', 'rrze-autoshare') . '</p></div>';
        } elseif ('failed' === $status) {
            echo '<div class="notice notice-error inline"><p>' . esc_html__('Bluesky access could not be authorized. Check the account identifier and App Password.', 'rrze-autoshare') . '</p></div>';
        } elseif ('revoked' === $status) {
            echo '<div class="notice notice-success inline"><p>' . esc_html__('Bluesky access was revoked.', 'rrze-autoshare') . '</p></div>';
        }
    }

    private function verifyServiceRequest(string $action, string $nonceField) {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have enough permissions to do that.', 'rrze-autoshare'));
        }

        $nonce = sanitize_text_field(wp_unslash($_POST[$nonceField] ?? ''));
        if (!wp_verify_nonce($nonce, $action)) {
            wp_die(esc_html__('The link you followed has expired. Please try again.', 'rrze-autoshare'));
        }
    }

    private function redirectToServiceTab(string $tab, string $status) {
        wp_safe_redirect(
            add_query_arg(
                config()->get('services.bluesky.authorization.notice_field'),
                $status,
                $this->getSettingsUrl($tab)
            )
        );
        exit;
    }

    private function getTabs(): array {
        return [
            'general' => __('General', 'rrze-autoshare'),
            'bluesky' => __('Bluesky', 'rrze-autoshare'),
            'mastodon' => __('Mastodon', 'rrze-autoshare'),
        ];
    }

    private function getCurrentTab(): string {
        $tab = filter_input(INPUT_GET, 'tab', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $tabs = $this->getTabs();

        return isset($tabs[$tab]) ? $tab : 'general';
    }

    private function getSettingsPage(string $tab): string {
        return config()->get('admin_page_slug') . '_' . $tab;
    }

    private function getSettingsUrl(string $tab): string {
        return add_query_arg(
            [
                'page' => config()->get('admin_page_slug'),
                'tab' => $tab,
            ],
            admin_url(config()->get('admin_parent_slug'))
        );
    }

    private function getDefaultOptions(): array {
        $options = [];
        $activeServices = config()->get('general.active_services');
        $options[$activeServices['setting']] = $activeServices['default'];

        foreach (config()->get('services', []) as $service) {
            foreach ($service['settings'] as $key => $setting) {
                if (isset($service['defaults'][$key])) {
                    $options[$setting] = $service['defaults'][$key];
                }
            }
        }

        return $options;
    }

    private function getUnauthorizedServices(): array {
        $services = [];

        foreach (array_keys($this->getServices()) as $service) {
            if (!$this->isServiceAuthorized($service)) {
                $services[] = $service;
            }
        }

        return $services;
    }
}
