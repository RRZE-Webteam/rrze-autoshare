<?php

namespace RRZE\Autoshare;

defined('ABSPATH') || exit;

use RRZE\Autoshare\Services\Bluesky\API as BlueskyAPI;
use RRZE\Autoshare\Services\Mastodon\API as MastodonAPI;

class Settings {
    public function __construct() {
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueSettingsAssets']);
        add_action(
            'admin_post_' . config()->get('services.bluesky.authorization.authorize_action'),
            [$this, 'authorizeBlueskyAccess']
        );
        add_action(
            'admin_post_' . config()->get('services.bluesky.authorization.revoke_action'),
            [$this, 'revokeBlueskyAccess']
        );
        add_action(
            'admin_post_' . config()->get('services.mastodon.authorization.authorize_action'),
            [$this, 'authorizeMastodonAccess']
        );
        add_action(
            'admin_post_' . config()->get('services.mastodon.authorization.revoke_action'),
            [$this, 'revokeMastodonAccess']
        );
        add_action(
            'admin_post_' . config()->get('transmission_test.action'),
            [$this, 'testTransmission']
        );
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

    public function enqueueSettingsAssets(string $hook): void {
        if ('settings_page_' . config()->get('admin_page_slug') !== $hook) {
            return;
        }

        $assets = config()->get('assets');
        $test = config()->get('transmission_test');

        wp_enqueue_style(
            $assets['admin_style_handle'],
            plugins_url($assets['admin_style_file'], plugin()->getBasename()),
            [],
            plugin()->getVersion()
        );
        wp_enqueue_script(
            $assets['settings_script_handle'],
            plugins_url($assets['settings_script_file'], plugin()->getBasename()),
            $assets['settings_script_dependencies'],
            plugin()->getVersion(),
            true
        );
        wp_localize_script(
            $assets['settings_script_handle'],
            $assets['settings_script_object_name'],
            [
                'searchEndpoint' => '/wp/v2/search',
                'searchMinimumLength' => 2,
                'searchLimit' => 10,
                'searchType' => 'post',
                'searchSubtype' => 'post',
                'searchInputId' => $test['post_search_id'],
                'postIdInputId' => $test['post_id_input_id'],
                'resultsId' => $test['post_search_results_id'],
                'statusId' => $test['post_search_status_id'],
                'noResults' => __('No posts found.', 'rrze-autoshare'),
                'searchFailed' => __('Posts could not be loaded.', 'rrze-autoshare'),
                'selectedPost' => __('Selected post:', 'rrze-autoshare'),
            ]
        );
    }

    public function registerSettings() {
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
            <?php if ('general' === $tab) { ?>
                <?php $this->renderTransmissionTest(); ?>
            <?php } ?>
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

        $submittedIdentifier = $_POST[$authorization['identifier_field']] ?? '';
        $submittedPassword = $_POST[$authorization['password_field']] ?? '';
        $identifier = is_scalar($submittedIdentifier)
            ? sanitize_text_field(wp_unslash($submittedIdentifier))
            : '';
        $password = is_scalar($submittedPassword)
            ? trim(wp_unslash($submittedPassword))
            : '';
        $authorized = $identifier !== '' && $password !== ''
            ? BlueskyAPI::authorize($identifier, $password)
            : new \WP_Error('missing_credentials');
        $status = true === $authorized ? 'success' : $authorized->get_error_code();

        $this->redirectToServiceTab('bluesky', $status, $authorization['notice_field']);
    }

    public function revokeBlueskyAccess() {
        $authorization = config()->get('services.bluesky.authorization');
        $this->verifyServiceRequest($authorization['nonce_action'], $authorization['nonce_field']);
        BlueskyAPI::revoke();

        $this->redirectToServiceTab('bluesky', 'revoked', $authorization['notice_field']);
    }

    public function authorizeMastodonAccess(): void {
        $authorization = config()->get('services.mastodon.authorization');
        $this->verifyServiceRequest($authorization['nonce_action'], $authorization['nonce_field']);

        $submittedToken = $_POST[$authorization['token_field']] ?? '';
        $accessToken = is_scalar($submittedToken) ? trim(wp_unslash($submittedToken)) : '';
        $authorized = $accessToken !== '' && MastodonAPI::authorize($accessToken);

        $this->redirectToServiceTab(
            'mastodon',
            $authorized ? 'success' : 'failed',
            $authorization['notice_field']
        );
    }

    public function revokeMastodonAccess(): void {
        $authorization = config()->get('services.mastodon.authorization');
        $this->verifyServiceRequest($authorization['nonce_action'], $authorization['nonce_field']);
        MastodonAPI::revoke();

        $this->redirectToServiceTab('mastodon', 'revoked', $authorization['notice_field']);
    }

    public function testTransmission(): void {
        $test = config()->get('transmission_test');
        $this->verifyServiceRequest($test['nonce_action'], $test['nonce_field']);

        $submittedPostId = $_POST[$test['post_id_field']] ?? 0;
        $postId = is_scalar($submittedPostId) ? absint(wp_unslash($submittedPostId)) : 0;
        $submittedServices = $_POST[$test['services_field']] ?? [];
        $selectedServices = array_values(
            array_intersect(
                array_keys($this->getServices()),
                array_map(
                    'sanitize_key',
                    array_filter((array) $submittedServices, 'is_scalar')
                )
            )
        );
        $post = get_post($postId);
        $results = [];

        if (!$post instanceof \WP_Post || !current_user_can('edit_post', $postId)) {
            $results['invalid_post'] = false;
        } elseif (empty($selectedServices)) {
            $results['no_service'] = false;
        } else {
            foreach ($selectedServices as $service) {
                $result = $this->isServiceAuthorized($service)
                    ? $this->sendTransmissionTest($service, $postId)
                    : false;
                $success = is_array($result);
                $results[$service] = $result;
                Utils::log(
                    $success ? 'info' : 'warning',
                    'Transmission test completed.',
                    [
                        'service' => $service,
                        'post_id' => $postId,
                        'success' => $success,
                    ]
                );
            }
        }

        set_transient(
            $test['result_transient_prefix'] . get_current_user_id(),
            $results,
            MINUTE_IN_SECONDS
        );
        wp_safe_redirect($this->getSettingsUrl('general'));
        exit;
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

    private function sendTransmissionTest(string $service, int $postId): array|false {
        if ('bluesky' === $service) {
            return BlueskyAPI::testPost($postId);
        }

        if ('mastodon' === $service) {
            return MastodonAPI::testPost($postId);
        }

        return false;
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
        $this->registerServiceFeaturedImageField('mastodon', $page, 'rrze_autoshare_mastodon');
        $this->registerServiceFormatField('mastodon', $page);
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

    private function renderTransmissionTest(): void {
        $test = config()->get('transmission_test');
        $results = get_transient($test['result_transient_prefix'] . get_current_user_id());
        delete_transient($test['result_transient_prefix'] . get_current_user_id());
        ?>
        <hr>
        <h2><?php esc_html_e('Transmission Test', 'rrze-autoshare'); ?></h2>
        <?php $this->renderTransmissionTestResult($results); ?>
        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
            <input type="hidden" name="action" value="<?php echo esc_attr($test['action']); ?>">
            <p>
                <label for="<?php echo esc_attr($test['post_search_id']); ?>"><?php esc_html_e('Post', 'rrze-autoshare'); ?></label><br>
                <input
                    id="<?php echo esc_attr($test['post_search_id']); ?>"
                    type="search"
                    class="regular-text"
                    autocomplete="off"
                    aria-describedby="<?php echo esc_attr($test['post_search_status_id']); ?>"
                >
                <input
                    id="<?php echo esc_attr($test['post_id_input_id']); ?>"
                    name="<?php echo esc_attr($test['post_id_field']); ?>"
                    type="hidden"
                    required
                >
                <span id="<?php echo esc_attr($test['post_search_status_id']); ?>" class="rrze-autoshare-post-search-status" role="status"></span>
                <div id="<?php echo esc_attr($test['post_search_results_id']); ?>" class="rrze-autoshare-post-search-results" role="listbox"></div>
            </p>
            <fieldset>
                <legend><?php esc_html_e('Services', 'rrze-autoshare'); ?></legend>
                <?php foreach ($this->getServices() as $service => $label) { ?>
                    <?php $authorized = $this->isServiceAuthorized($service); ?>
                    <label for="rrze-autoshare-transmission-test-<?php echo esc_attr($service); ?>">
                        <input
                            id="rrze-autoshare-transmission-test-<?php echo esc_attr($service); ?>"
                            name="<?php echo esc_attr($test['services_field']); ?>[]"
                            type="checkbox"
                            value="<?php echo esc_attr($service); ?>"
                            <?php disabled(!$authorized); ?>
                        >
                        <?php echo esc_html($label); ?>
                    </label><br>
                <?php } ?>
            </fieldset>
            <?php wp_nonce_field($test['nonce_action'], $test['nonce_field']); ?>
            <?php submit_button(__('Run Transmission Test', 'rrze-autoshare'), 'secondary', 'submit', false); ?>
        </form>
        <?php
    }

    private function renderTransmissionTestResult($results): void {
        if (!is_array($results) || empty($results)) {
            return;
        }

        foreach ($results as $service => $result) {
            if ('invalid_post' === $service) {
                echo '<div class="notice notice-error inline"><p>' . esc_html__('The post does not exist or you cannot edit it.', 'rrze-autoshare') . '</p></div>';
            } elseif ('no_service' === $service) {
                echo '<div class="notice notice-error inline"><p>' . esc_html__('Select at least one service for the transmission test.', 'rrze-autoshare') . '</p></div>';
            } else {
                $label = $this->getServices()[$service] ?? $service;
                $success = is_array($result);
                $imageNotTransferred = $success && !empty($result['image_not_transferred']);
                if ($imageNotTransferred) {
                    $message = sprintf(
                        /* translators: %s: Service name. */
                        __('The text was sent to %s successfully, but the featured image could not be transmitted.', 'rrze-autoshare'),
                        $label
                    );
                    $class = 'notice-warning';
                } elseif ($success) {
                    $message = sprintf(
                        /* translators: %s: Service name. */
                        __('%s received the transmission test successfully.', 'rrze-autoshare'),
                        $label
                    );
                    $class = 'notice-success';
                } else {
                    $message = sprintf(
                        /* translators: %s: Service name. */
                        __('%s could not receive the transmission test. Check the log for details.', 'rrze-autoshare'),
                        $label
                    );
                    $class = 'notice-error';
                }
                $url = $success && !empty($result['url']) ? esc_url($result['url']) : '';
                echo '<div class="notice ' . esc_attr($class) . ' inline"><p>' . esc_html($message);
                if ($url !== '') {
                    echo ' <a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('View post', 'rrze-autoshare') . '</a>';
                }
                echo '</p></div>';
            }
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
                    <label for="rrze-autoshare-bluesky-identifier"><?php esc_html_e('Bluesky handle or email address', 'rrze-autoshare'); ?></label><br>
                    <input id="rrze-autoshare-bluesky-identifier" name="<?php echo esc_attr($authorization['identifier_field']); ?>" type="text" class="regular-text" autocomplete="username" required>
                    <p class="description"><?php esc_html_e('Use the complete handle without @, for example name.bsky.social. An email address is also accepted.', 'rrze-autoshare'); ?></p>
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
        $authorization = config()->get('services.mastodon.authorization');
        ?>
        <hr>
        <h2><?php esc_html_e('Access', 'rrze-autoshare'); ?></h2>
        <?php $this->renderMastodonAuthorizationNotice(); ?>
        <?php if (MastodonAPI::isConnected()) { ?>
            <p><?php esc_html_e('You’ve authorized Autoshare to read and write to the Mastodon timeline.', 'rrze-autoshare'); ?></p>
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                <input type="hidden" name="action" value="<?php echo esc_attr($authorization['revoke_action']); ?>">
                <?php wp_nonce_field($authorization['nonce_action'], $authorization['nonce_field']); ?>
                <?php submit_button(__('Revoke Access', 'rrze-autoshare'), 'secondary', 'submit', false); ?>
            </form>
        <?php } else { ?>
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                <input type="hidden" name="action" value="<?php echo esc_attr($authorization['authorize_action']); ?>">
                <p>
                    <label for="rrze-autoshare-mastodon-access-token"><?php esc_html_e('Access Token', 'rrze-autoshare'); ?></label><br>
                    <input id="rrze-autoshare-mastodon-access-token" name="<?php echo esc_attr($authorization['token_field']); ?>" type="password" class="regular-text" autocomplete="off" required>
                </p>
                <p class="description">
                    <?php esc_html_e('Grant the permissions write:statuses, write:media, and read:accounts to Autoshare.', 'rrze-autoshare'); ?>
                </p>
                <p class="description">
                    <?php
                    echo wp_kses(
                        sprintf(
                            /* translators: 1: Mastodon application settings URL, 2: Mastodon token documentation URL. */
                            __('Create an application and an access token with the permissions write:statuses, write:media, and read:accounts in <a href="%1$s" target="_blank" rel="noopener noreferrer">Mastodon application settings</a>. <a href="%2$s" target="_blank" rel="noopener noreferrer">Learn more about Mastodon access tokens</a>. The token is stored encrypted.', 'rrze-autoshare'),
                            esc_url(MastodonAPI::applicationSettingsUrl()),
                            esc_url($authorization['info_url'])
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

    private function renderBlueskyAuthorizationNotice() {
        $status = filter_input(INPUT_GET, config()->get('services.bluesky.authorization.notice_field'), FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        if ('success' === $status) {
            echo '<div class="notice notice-success inline"><p>' . esc_html__('Bluesky access was authorized.', 'rrze-autoshare') . '</p></div>';
        } elseif ('missing_credentials' === $status) {
            echo '<div class="notice notice-error inline"><p>' . esc_html__('Enter both a Bluesky handle or email address and an App Password.', 'rrze-autoshare') . '</p></div>';
        } elseif ('invalid_app_password_format' === $status) {
            echo '<div class="notice notice-error inline"><p>' . esc_html__('The App Password format is invalid. Create a new Bluesky App Password and enter it exactly as shown, including hyphens.', 'rrze-autoshare') . '</p></div>';
        } elseif ('credentials_rejected' === $status) {
            echo '<div class="notice notice-error inline"><p>' . esc_html__('Bluesky rejected the credentials. Use the complete handle without @, for example name.bsky.social, or the account email address. Do not use the normal account password; create a new App Password instead.', 'rrze-autoshare') . '</p></div>';
        } elseif ('authorization_rate_limited' === $status) {
            echo '<div class="notice notice-warning inline"><p>' . esc_html__('Bluesky temporarily limited authorization attempts. Wait before trying again.', 'rrze-autoshare') . '</p></div>';
        } elseif ('service_unavailable' === $status) {
            echo '<div class="notice notice-error inline"><p>' . esc_html__('Bluesky is temporarily unavailable. Try again later.', 'rrze-autoshare') . '</p></div>';
        } elseif ('connection_failed' === $status) {
            echo '<div class="notice notice-error inline"><p>' . esc_html__('Bluesky could not be reached. Check the server connection.', 'rrze-autoshare') . '</p></div>';
        } elseif ('unexpected_authorization_response' === $status) {
            echo '<div class="notice notice-error inline"><p>' . esc_html__('Bluesky returned an incomplete authorization response. No access tokens were saved.', 'rrze-autoshare') . '</p></div>';
        } elseif ('token_storage_failed' === $status) {
            echo '<div class="notice notice-error inline"><p>' . esc_html__('The Bluesky access tokens could not be stored securely. Check the server encryption configuration and the log.', 'rrze-autoshare') . '</p></div>';
        } elseif ('authorization_failed' === $status) {
            echo '<div class="notice notice-error inline"><p>' . esc_html__('Bluesky could not authorize access.', 'rrze-autoshare') . '</p></div>';
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

    private function renderMastodonAuthorizationNotice(): void {
        $noticeField = config()->get('services.mastodon.authorization.notice_field');
        $status = filter_input(INPUT_GET, $noticeField, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        if ('success' === $status) {
            echo '<div class="notice notice-success inline"><p>' . esc_html__('Mastodon access was authorized.', 'rrze-autoshare') . '</p></div>';
        } elseif ('failed' === $status) {
            echo '<div class="notice notice-error inline"><p>' . esc_html__('The Mastodon access token could not be verified. Check the token and service URL.', 'rrze-autoshare') . '</p></div>';
        } elseif ('revoked' === $status) {
            echo '<div class="notice notice-success inline"><p>' . esc_html__('Mastodon access was revoked.', 'rrze-autoshare') . '</p></div>';
        }
    }

    private function redirectToServiceTab(string $tab, string $status, string $noticeField) {
        wp_safe_redirect(
            add_query_arg(
                $noticeField,
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
