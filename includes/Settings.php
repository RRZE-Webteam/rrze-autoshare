<?php

namespace RRZE\Autoshare;

defined('ABSPATH') || exit;

use RRZE\Autoshare\Services\Bluesky\API as BlueskyAPI;
use RRZE\Autoshare\Services\Mastodon\API as MastodonAPI;
use RRZE\Autoshare\Services\Matrix\API as MatrixAPI;

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
        add_action('admin_post_' . config()->get('services.matrix.authorization.authorize_action'), [$this, 'authorizeMatrixAccess']);
        add_action('admin_post_' . config()->get('services.matrix.authorization.revoke_action'), [$this, 'revokeMatrixAccess']);
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

        wp_enqueue_style('dashicons');
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
                'searchLimit' => 5,
                'searchType' => 'post',
                'searchSubtype' => 'post',
                'searchInputId' => $test['post_search_id'],
                'postIdInputId' => $test['post_id_input_id'],
                'resultsId' => $test['post_search_results_id'],
                'statusId' => $test['post_search_status_id'],
                'noResults' => __('No posts found.', 'rrze-autoshare'),
                'searchFailed' => __('Posts could not be loaded.', 'rrze-autoshare'),
                'selectedPost' => __('Selected post:', 'rrze-autoshare'),
                'publicationRules' => [
                    'categoryAll' => __('any categories', 'rrze-autoshare'),
                    'categoryNone' => __('no selected categories', 'rrze-autoshare'),
                    /* translators: %s: Category name. */
                    'categorySingle' => __('the category %s', 'rrze-autoshare'),
                    /* translators: %s: Comma-separated category names. */
                    'categoryMultiple' => __('one of the categories %s', 'rrze-autoshare'),
                    'tagAll' => __('any or no tags', 'rrze-autoshare'),
                    'tagNone' => __('no selected tags', 'rrze-autoshare'),
                    /* translators: %s: Tag name. */
                    'tagSingle' => __('the tag %s', 'rrze-autoshare'),
                    /* translators: %s: Comma-separated tag names. */
                    'tagMultiple' => __('one of the tags %s', 'rrze-autoshare'),
                    'targetSingle' => __(' with target ', 'rrze-autoshare'),
                    'targetMultiple' => __(' with targets ', 'rrze-autoshare'),
                ],
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
        $this->registerPublicationRulesSettings($this->getSettingsPage('publication-rules'));
        $this->registerBlueskySettings();
        $this->registerMastodonSettings();
        $this->registerMatrixSettings();
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

        $options = $this->sanitizePublicationRules($options, $submittedOptions);

        $informativeLogging = config()->get('debug.informative_logging');
        if (
            $this->canManageDebugging()
            && array_key_exists($informativeLogging['setting'], $submittedOptions)
        ) {
            $options[$informativeLogging['setting']] = !empty(
                $submittedOptions[$informativeLogging['setting']]
            );
        }

        foreach (config()->get('services', []) as $service => $serviceConfig) {
            $settings = $serviceConfig['settings'];
            $defaults = $serviceConfig['defaults'];

            if (isset($settings['domain']) && array_key_exists($settings['domain'], $submittedOptions)) {
                $submittedDomain = $submittedOptions[$settings['domain']] ?? '';
                $domain = is_scalar($submittedDomain)
                    ? esc_url_raw(wp_unslash($submittedDomain))
                    : '';
                if ('bluesky' === $service) {
                    $domain = $defaults['domain'];
                } elseif ($domain !== '' && !Utils::isHttpsUrl($domain)) {
                    add_settings_error(
                        config()->get('option_name'),
                        $settings['domain'],
                        __('The service URL must use HTTPS.', 'rrze-autoshare')
                    );
                    $currentDomain = $this->getOption($settings['domain']);
                    $domain = is_string($currentDomain) && Utils::isHttpsUrl($currentDomain)
                        ? $currentDomain
                        : $defaults['domain'];
                }
                $options[$settings['domain']] = $domain ?: $defaults['domain'];
            }
            if ('matrix' === $service && isset($settings['rooms']) && array_key_exists($settings['rooms'], $submittedOptions)) {
                $rooms = preg_split('/\R/', (string) wp_unslash($submittedOptions[$settings['rooms']]));
                $rooms = is_array($rooms) ? array_filter(array_map('trim', $rooms), [__CLASS__, 'isValidMatrixRoomId']) : [];
                $rooms = array_values(array_unique($rooms));
                if (empty($rooms)) {
                    add_settings_error(
                        config()->get('option_name'),
                        $settings['rooms'],
                        __('Enter at least one valid Matrix room ID.', 'rrze-autoshare')
                    );
                } else {
                    $options[$settings['rooms']] = implode("\n", $rooms);
                }
            }

            if (isset($settings['featured_image']) && array_key_exists($settings['featured_image'], $submittedOptions)) {
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
            $services[$slug] = $service['label'];
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

    public function isPostAutoshareEnabled(int $postId): bool {
        $metaKey = config()->get('post_meta.enabled');

        return !metadata_exists('post', $postId, $metaKey)
            || rest_sanitize_boolean(get_post_meta($postId, $metaKey, true));
    }

    public function isServiceAuthorized(string $service): bool {
        $callback = config()->get('services.' . $service . '.authentication.connection_callback');

        if (!is_callable($callback)) {
            return false;
        }

        try {
            return (bool) call_user_func($callback);
        } catch (\Throwable $exception) {
            Utils::log(
                'error',
                'Service authorization state could not be determined.',
                [
                    'service' => sanitize_key($service),
                    'exception' => sanitize_text_field($exception->getMessage()),
                ]
            );

            return false;
        }
    }

    public function getMatrixRooms(): array {
        return $this->getServiceTargets('matrix');
    }

    public function getServiceTargets(string $service): array {
        $targets = config()->get('services.' . $service . '.targets', []);
        $setting = $targets['setting'] ?? '';
        if (!is_string($setting) || $setting === '') {
            return [];
        }

        $values = preg_split('/\R/', (string) $this->getOption($setting));
        $values = is_array($values) ? array_map('trim', $values) : [];
        $values = array_filter($values);
        $validationCallback = $targets['validation_callback'] ?? null;
        if (is_callable($validationCallback)) {
            $values = array_filter($values, $validationCallback);
        }

        return array_values(array_unique($values));
    }

    public static function isValidMatrixRoomId(string $roomId): bool {
        return (bool) preg_match('/^![^:\\s]+:[^\\s]+$/', $roomId);
    }

    public function shouldPublishPostToService(
        string $service,
        \WP_Post $post,
        string $newStatus,
        string $oldStatus
    ): bool {
        if (
            !$this->isServiceActive($service)
            || !in_array($post->post_type, config()->get('default_post_types'), true)
            || !$this->isInitialPublicationTransition($newStatus, $oldStatus)
        ) {
            return false;
        }

        $publicationRules = config()->get('general.publication_rules');
        if (
            $this->getOption($publicationRules['mode_setting'])
            !== $publicationRules['advanced_mode']
        ) {
            return 'publish' === $newStatus;
        }

        foreach ($this->getPublicationRules($service) as $rule) {
            if ($this->matchesPublicationRule($rule, $post, $newStatus)) {
                return true;
            }
        }

        return false;
    }

    public function getPublicationTargetsForPost(
        string $service,
        \WP_Post $post,
        string $newStatus,
        string $oldStatus
    ): array {
        if (!$this->shouldPublishPostToService($service, $post, $newStatus, $oldStatus)) {
            return [];
        }

        $targets = $this->getServiceTargets($service);
        if (empty($targets)) {
            return [];
        }

        $publicationRules = config()->get('general.publication_rules');
        if ($this->getOption($publicationRules['mode_setting']) !== $publicationRules['advanced_mode']) {
            return $targets;
        }

        $selectedTargets = [];
        foreach ($this->getPublicationRules($service) as $rule) {
            if ($this->matchesPublicationRule($rule, $post, $newStatus)) {
                $selectedTargets = array_merge(
                    $selectedTargets,
                    $rule[$publicationRules['targets_key']]
                );
            }
        }

        return array_values(array_unique($selectedTargets));
    }

    private function sanitizePublicationRules(array $options, array $submittedOptions): array {
        $publicationRules = config()->get('general.publication_rules');
        $modeSetting = $publicationRules['mode_setting'];
        $rulesSetting = $publicationRules['rules_setting'];

        if (array_key_exists($modeSetting, $submittedOptions)) {
            $submittedMode = is_scalar($submittedOptions[$modeSetting])
                ? sanitize_key($submittedOptions[$modeSetting])
                : '';
            $options[$modeSetting] = $publicationRules['advanced_mode'] === $submittedMode
                ? $publicationRules['advanced_mode']
                : $publicationRules['default_mode'];
        }

        if (!array_key_exists($rulesSetting, $submittedOptions)) {
            return $options;
        }

        $submittedRules = is_array($submittedOptions[$rulesSetting])
            ? $submittedOptions[$rulesSetting]
            : [];
        $rules = [];
        foreach (array_keys($this->getServices()) as $service) {
            if (!array_key_exists($service, $submittedRules)) {
                $rules[$service] = $this->supportsMultiplePublicationRules($service)
                    ? $this->getPublicationRules($service)
                    : $this->getPublicationRule($service);
                continue;
            }

            $submittedRule = $submittedRules[$service] ?? [];
            $submittedRule = is_array($submittedRule) ? $submittedRule : [];
            if (!$this->supportsMultiplePublicationRules($service)) {
                $rules[$service] = $this->sanitizePublicationRule($service, $submittedRule);
                continue;
            }

            $rules[$service] = $this->sanitizePublicationRuleList($service, $submittedRule);
        }
        $options[$rulesSetting] = $rules;

        return $options;
    }

    private function sanitizePublicationRule(string $service, array $submittedRule): array {
        $publicationRules = config()->get('general.publication_rules');
        $allowedStatuses = config()->get('services.' . $service . '.publication_statuses', []);
        $fallbackStatus = $allowedStatuses[0] ?? $publicationRules['rule_defaults']['status'];
        $submittedStatus = $submittedRule['status'] ?? null;
        if (
            (!is_scalar($submittedStatus) || '' === $submittedStatus)
            && !empty($submittedRule['statuses'])
        ) {
            $submittedStatus = is_array($submittedRule['statuses'])
                ? reset($submittedRule['statuses'])
                : $submittedRule['statuses'];
        }
        $status = is_scalar($submittedStatus) ? sanitize_key($submittedStatus) : '';
        $categoryMode = isset($submittedRule['category_mode']) && is_scalar($submittedRule['category_mode'])
            ? sanitize_key($submittedRule['category_mode'])
            : '';
        $tagMode = isset($submittedRule['tag_mode']) && is_scalar($submittedRule['tag_mode'])
            ? sanitize_key($submittedRule['tag_mode'])
            : '';
        $categoryMode = $publicationRules['selected_terms'] === $categoryMode
            ? $publicationRules['selected_terms']
            : $publicationRules['all_terms'];
        $tagMode = $publicationRules['selected_terms'] === $tagMode
            ? $publicationRules['selected_terms']
            : $publicationRules['all_terms'];
        $targets = $this->sanitizePublicationTargets($service, $submittedRule[$publicationRules['targets_key']] ?? []);

        return [
            'status' => in_array($status, $allowedStatuses, true) ? $status : $fallbackStatus,
            'category_mode' => $categoryMode,
            'category_ids' => $publicationRules['selected_terms'] === $categoryMode
                ? $this->sanitizeTermIds($submittedRule['category_ids'] ?? [], 'category')
                : [],
            'tag_mode' => $tagMode,
            'tag_ids' => $publicationRules['selected_terms'] === $tagMode
                ? $this->sanitizeTermIds($submittedRule['tag_ids'] ?? [], 'post_tag')
                : [],
            $publicationRules['targets_key'] => $targets,
        ];
    }

    private function sanitizePublicationRuleList(string $service, array $submittedRules): array {
        $rules = [];
        foreach ($submittedRules as $submittedRule) {
            if (is_array($submittedRule)) {
                $rules[] = $this->sanitizePublicationRule($service, $submittedRule);
            }
        }

        return empty($rules) ? [$this->sanitizePublicationRule($service, [])] : $rules;
    }

    private function sanitizePublicationTargets(string $service, $submittedTargets): array {
        $configuredTargets = $this->getServiceTargets($service);
        if (empty($configuredTargets)) {
            return [];
        }
        if (count($configuredTargets) === 1) {
            return $configuredTargets;
        }

        $submittedTargets = array_filter(
            array_map('sanitize_text_field', (array) $submittedTargets)
        );

        return array_values(array_intersect($configuredTargets, $submittedTargets));
    }

    private function sanitizeTermIds($submittedTermIds, string $taxonomy): array {
        $termIds = [];
        foreach ((array) $submittedTermIds as $termId) {
            if (is_scalar($termId)) {
                $termIds[] = absint($termId);
            }
        }
        $termIds = array_values(array_filter($termIds));
        if (empty($termIds)) {
            return [];
        }

        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'include' => $termIds,
            'hide_empty' => false,
            'fields' => 'ids',
        ]);

        return is_wp_error($terms) ? [] : array_map('absint', $terms);
    }

    private function getPublicationRules(string $service): array {
        $publicationRules = config()->get('general.publication_rules');
        $rules = $this->getOption($publicationRules['rules_setting']);
        $storedRules = is_array($rules) && isset($rules[$service]) && is_array($rules[$service])
            ? $rules[$service]
            : [];
        if (!$this->supportsMultiplePublicationRules($service)) {
            $storedRules = [$storedRules];
        } elseif (array_key_exists('status', $storedRules)) {
            $storedRules = [$storedRules];
        }

        $normalizedRules = [];
        foreach ($storedRules as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            $normalizedRules[] = $this->normalizePublicationRule($service, $rule);
        }

        return empty($normalizedRules)
            ? [$this->normalizePublicationRule($service, [])]
            : $normalizedRules;
    }

    private function getPublicationRule(string $service): array {
        return $this->getPublicationRules($service)[0];
    }

    private function normalizePublicationRule(string $service, array $rule): array {
        $publicationRules = config()->get('general.publication_rules');
        $targetsKey = $publicationRules['targets_key'];
        if (!array_key_exists($targetsKey, $rule) && !empty($this->getServiceTargets($service))) {
            $rule[$targetsKey] = $this->getServiceTargets($service);
        }

        return wp_parse_args(
            $this->sanitizePublicationRule($service, $rule),
            $publicationRules['rule_defaults']
        );
    }

    private function supportsMultiplePublicationRules(string $service): bool {
        return !empty(config()->get('services.' . $service . '.rules.multiple'));
    }

    private function matchesPublicationRule(array $rule, \WP_Post $post, string $newStatus): bool {
        return $newStatus === $rule['status']
            && $this->matchesTermRule($post->ID, 'category', $rule['category_mode'], $rule['category_ids'])
            && $this->matchesTermRule($post->ID, 'post_tag', $rule['tag_mode'], $rule['tag_ids']);
    }

    private function getPublicationRuleFieldName(string $service, string $field, int|string|null $index = null): string {
        $name = config()->get('option_name')
            . '[' . config()->get('general.publication_rules.rules_setting') . ']'
            . '[' . $service . ']';

        return null === $index ? $name . '[' . $field . ']' : $name . '[' . $index . '][' . $field . ']';
    }

    private function isInitialPublicationTransition(string $newStatus, string $oldStatus): bool {
        if ('draft' === $newStatus) {
            return in_array($oldStatus, ['new', 'auto-draft'], true);
        }

        return $newStatus !== $oldStatus;
    }

    private function matchesTermRule(int $postId, string $taxonomy, string $mode, array $selectedTermIds): bool {
        if ($mode === config()->get('general.publication_rules.all_terms')) {
            return true;
        }

        if (empty($selectedTermIds)) {
            return false;
        }

        $postTermIds = wp_get_post_terms($postId, $taxonomy, ['fields' => 'ids']);
        if (is_wp_error($postTermIds)) {
            return false;
        }

        return !empty(array_intersect($selectedTermIds, array_map('absint', $postTermIds)));
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
            <?php if ('transmission-test' === $tab) { ?>
                <?php $this->renderTransmissionTest(); ?>
            <?php } else { ?>
                <form action="options.php" method="post">
                    <?php settings_fields(config()->get('slug') . '_settings'); ?>
                    <input type="hidden" name="_wp_http_referer" value="<?php echo esc_url($this->getSettingsUrl($tab)); ?>">
                    <?php do_settings_sections($this->getSettingsPage($tab)); ?>
                    <?php submit_button(); ?>
                </form>
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
            <?php echo !empty($args['required']) ? 'required' : ''; ?>
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

    public function authorizeMatrixAccess(): void {
        $authorization = config()->get('services.matrix.authorization');
        $this->verifyServiceRequest($authorization['nonce_action'], $authorization['nonce_field']);
        $value = $_POST[$authorization['token_field']] ?? '';
        $token = is_scalar($value) ? trim(wp_unslash($value)) : '';
        $authorized = MatrixAPI::authorize($token);
        $status = true === $authorized ? 'success' : $authorized->get_error_code();

        $this->redirectToServiceTab('matrix', $status, $authorization['notice_field']);
    }

    public function revokeMatrixAccess(): void {
        $authorization = config()->get('services.matrix.authorization');
        $this->verifyServiceRequest($authorization['nonce_action'], $authorization['nonce_field']);
        MatrixAPI::revoke();
        $this->redirectToServiceTab('matrix', 'revoked', $authorization['notice_field']);
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
                $result = $this->sendPostAccordingToRules($service, $post);
                $success = is_array($result);
                $results[$service] = $result;
                Utils::log(
                    $success ? 'info' : 'warning',
                    'Manual post sharing completed.',
                    [
                        'service' => $service,
                        'post_id' => $postId,
                        'success' => $success,
                        'matched_rules' => !$success || empty($result['skipped']),
                    ]
                );
            }
        }

        set_transient(
            $test['result_transient_prefix'] . get_current_user_id(),
            $results,
            MINUTE_IN_SECONDS
        );
        wp_safe_redirect($this->getSettingsUrl('transmission-test'));
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

        if ($this->canManageDebugging()) {
            $this->registerDebugSettings($page);
        }
    }

    private function registerDebugSettings(string $page): void {
        $informativeLogging = config()->get('debug.informative_logging');

        add_settings_section(
            'rrze_autoshare_debugging',
            __('Debugging', 'rrze-autoshare'),
            null,
            $page
        );
        add_settings_field(
            $informativeLogging['setting'],
            __('Logging', 'rrze-autoshare'),
            [$this, 'renderCheckboxField'],
            $page,
            'rrze_autoshare_debugging',
            [
                'name' => $informativeLogging['setting'],
                'description' => __('Send informative messages to the info log channel.', 'rrze-autoshare'),
            ]
        );
    }

    private function registerPublicationRulesSettings(string $page): void {
        add_settings_section(
            'rrze_autoshare_publication_rules',
            __('Publication Rules', 'rrze-autoshare'),
            null,
            $page
        );
        add_settings_field(
            'rrze_autoshare_publication_rule_mode',
            __('Rules', 'rrze-autoshare'),
            [$this, 'renderPublicationRuleModeField'],
            $page,
            'rrze_autoshare_publication_rules'
        );

        foreach ($this->getServices() as $service => $label) {
            $sectionId = 'rrze_autoshare_publication_rules_' . $service;
            add_settings_section(
                $sectionId,
                $label,
                [$this, 'renderPublicationServiceRulesSection'],
                $page,
                ['service' => $service]
            );
            add_settings_field(
                $sectionId,
                __('Rules', 'rrze-autoshare'),
                [$this, 'renderPublicationServiceRulesField'],
                $page,
                $sectionId,
                [
                    'service' => $service,
                    'label' => $label,
                ]
            );
        }
    }

    public function renderPublicationRuleModeField(): void {
        $publicationRules = config()->get('general.publication_rules');
        $mode = $this->getOption($publicationRules['mode_setting']);
        $advanced = $publicationRules['advanced_mode'] === $mode;
        ?>
        <fieldset>
            <label>
                <input
                    class="rrze-autoshare-publication-rule-mode"
                    name="<?php echo esc_attr(config()->get('option_name') . '[' . $publicationRules['mode_setting'] . ']'); ?>"
                    type="radio"
                    value="<?php echo esc_attr($publicationRules['default_mode']); ?>"
                    <?php checked(!$advanced); ?>
                >
                <?php esc_html_e('Default rules', 'rrze-autoshare'); ?>
            </label>
            <p class="description"><?php esc_html_e('Send all newly published posts to all active services.', 'rrze-autoshare'); ?></p>
            <label>
                <input
                    class="rrze-autoshare-publication-rule-mode"
                    name="<?php echo esc_attr(config()->get('option_name') . '[' . $publicationRules['mode_setting'] . ']'); ?>"
                    type="radio"
                    value="<?php echo esc_attr($publicationRules['advanced_mode']); ?>"
                    <?php checked($advanced); ?>
                >
                <?php esc_html_e('Advanced rules', 'rrze-autoshare'); ?>
            </label>
            <p class="description"><?php esc_html_e('Set rules separately for each service.', 'rrze-autoshare'); ?></p>
        </fieldset>
        <?php
    }

    public function renderPublicationServiceRulesSection(array $args): void {
        $publicationRules = config()->get('general.publication_rules');
        $advanced = $this->getOption($publicationRules['mode_setting']) === $publicationRules['advanced_mode'];
        ?>
        <div class="rrze-autoshare-publication-service-section-marker" <?php echo !$advanced ? 'hidden' : ''; ?>></div>
        <?php
    }

    public function renderPublicationServiceRulesField(array $args): void {
        $service = $args['service'];
        $label = $args['label'];
        $serviceActive = $this->isServiceActive($service);
        $multipleRules = $this->supportsMultiplePublicationRules($service);
        $rules = $this->getPublicationRules($service);
        ?>
        <div
            class="rrze-autoshare-publication-service-rules"
            data-next-rule-index="<?php echo esc_attr(count($rules)); ?>"
        >
            <?php if (!$serviceActive) { ?>
                <div class="notice notice-warning inline">
                    <p><?php esc_html_e('This service is inactive. Its rules take effect only after the service is activated.', 'rrze-autoshare'); ?></p>
                </div>
            <?php } else { ?>
                <div class="rrze-autoshare-publication-service-rule-list">
                    <?php foreach ($rules as $index => $rule) { ?>
                        <?php $this->renderPublicationServiceRule($service, $label, $rule, $multipleRules ? $index : null, $multipleRules); ?>
                    <?php } ?>
                </div>
                <?php if ($multipleRules) { ?>
                    <template class="rrze-autoshare-publication-rule-template">
                        <?php $this->renderPublicationServiceRule($service, $label, $this->normalizePublicationRule($service, []), '__rule_index__', true); ?>
                    </template>
                    <p>
                        <button type="button" class="button rrze-autoshare-add-publication-rule">
                            <?php esc_html_e('Add rule', 'rrze-autoshare'); ?>
                        </button>
                    </p>
                <?php } ?>
            <?php } ?>
        </div>
        <?php
    }

    private function renderPublicationServiceRule(
        string $service,
        string $serviceLabel,
        array $rule,
        int|string|null $index,
        bool $removable
    ): void {
        $allowedStatuses = config()->get('services.' . $service . '.publication_statuses', []);
        ?>
        <fieldset class="rrze-autoshare-publication-service-rule">
            <?php $this->renderPublicationRuleSummary($service, $serviceLabel, $rule); ?>
            <p>
                <strong><?php esc_html_e('Post Status', 'rrze-autoshare'); ?></strong><br>
                <?php if (count($allowedStatuses) === 1) { ?>
                    <input
                        name="<?php echo esc_attr($this->getPublicationRuleFieldName($service, 'status', $index)); ?>"
                        type="hidden"
                        value="<?php echo esc_attr($allowedStatuses[0]); ?>"
                        class="rrze-autoshare-publication-status"
                        data-status-label="<?php echo esc_attr($this->getPublicationStatusLabel($allowedStatuses[0])); ?>"
                    >
                    <?php echo esc_html($this->getPublicationStatusLabel($allowedStatuses[0])); ?>
                <?php } else { ?>
                    <?php foreach ($allowedStatuses as $status) { ?>
                        <label>
                            <input
                                name="<?php echo esc_attr($this->getPublicationRuleFieldName($service, 'status', $index)); ?>"
                                type="radio"
                                value="<?php echo esc_attr($status); ?>"
                                class="rrze-autoshare-publication-status"
                                <?php checked($status === $rule['status']); ?>
                            >
                            <?php echo esc_html($this->getPublicationStatusLabel($status)); ?>
                        </label>
                    <?php } ?>
                <?php } ?>
            </p>
            <?php $this->renderTermRuleField($service, $rule, 'category', __('Categories', 'rrze-autoshare'), $index); ?>
            <?php $this->renderTermRuleField($service, $rule, 'tag', __('Tags', 'rrze-autoshare'), $index); ?>
            <?php $this->renderPublicationTargetsField($service, $rule, $index); ?>
            <?php if ($removable) { ?>
                <p>
                    <button type="button" class="button-link-delete rrze-autoshare-remove-publication-rule">
                        <?php esc_html_e('Remove rule', 'rrze-autoshare'); ?>
                    </button>
                </p>
            <?php } ?>
        </fieldset>
        <?php
    }

    private function renderPublicationRuleSummary(string $service, string $serviceLabel, array $rule): void {
        $targets = $this->getPublicationRuleTargets($service, $rule);
        ?>
        <p class="rrze-autoshare-publication-rule-summary" aria-live="polite">
            <?php
            printf(
                /* translators: %s: Service name. */
                esc_html__('Posts are sent to %s when they are ', 'rrze-autoshare'),
                esc_html($serviceLabel)
            );
            ?>
            <span
                class="rrze-autoshare-publication-summary-targets"
                data-targets="<?php echo esc_attr(implode('|', $targets)); ?>"
                <?php echo empty($targets) ? 'hidden' : ''; ?>
            >
                <span class="rrze-autoshare-publication-summary-target-prefix">
                    <?php echo esc_html(count($targets) === 1 ? __(' with target ', 'rrze-autoshare') : __(' with targets ', 'rrze-autoshare')); ?>
                </span>
                <strong class="rrze-autoshare-publication-summary-target-values"><?php echo esc_html(implode(', ', $targets)); ?></strong>
            </span>
            <strong class="rrze-autoshare-publication-summary-status"><?php echo esc_html($this->getPublicationStatusLabel($rule['status'])); ?></strong>
            <?php esc_html_e(' and are in ', 'rrze-autoshare'); ?>
            <strong class="rrze-autoshare-publication-summary-categories"><?php echo esc_html($this->getPublicationTermSummary($rule, 'category')); ?></strong>
            <?php esc_html_e(' and are marked with ', 'rrze-autoshare'); ?>
            <strong class="rrze-autoshare-publication-summary-tags"><?php echo esc_html($this->getPublicationTermSummary($rule, 'tag')); ?></strong><?php esc_html_e('.', 'rrze-autoshare'); ?>
        </p>
        <?php
    }

    private function getPublicationRuleTargets(string $service, array $rule): array {
        $targetsKey = config()->get('general.publication_rules.targets_key');
        $selectedTargets = isset($rule[$targetsKey]) && is_array($rule[$targetsKey])
            ? $rule[$targetsKey]
            : [];

        return array_values(array_intersect($this->getServiceTargets($service), $selectedTargets));
    }

    private function getPublicationTermSummary(array $rule, string $type): string {
        $publicationRules = config()->get('general.publication_rules');
        $modeKey = $type . '_mode';
        $idsKey = $type . '_ids';

        if ($publicationRules['all_terms'] === $rule[$modeKey]) {
            return 'category' === $type
                ? __('any categories', 'rrze-autoshare')
                : __('any or no tags', 'rrze-autoshare');
        }

        $terms = get_terms([
            'taxonomy' => 'category' === $type ? 'category' : 'post_tag',
            'include' => $rule[$idsKey],
            'hide_empty' => false,
        ]);
        $termNames = is_wp_error($terms) ? [] : wp_list_pluck($terms, 'name');

        if (empty($termNames)) {
            return 'category' === $type
                ? __('no selected categories', 'rrze-autoshare')
                : __('no selected tags', 'rrze-autoshare');
        }

        if (count($termNames) === 1) {
            if ('category' === $type) {
                return sprintf(
                    /* translators: %s: Category name. */
                    __('the category %s', 'rrze-autoshare'),
                    $termNames[0]
                );
            }

            return sprintf(
                /* translators: %s: Tag name. */
                __('the tag %s', 'rrze-autoshare'),
                $termNames[0]
            );
        }

        if ('category' === $type) {
            return sprintf(
                /* translators: %s: Comma-separated category names. */
                __('one of the categories %s', 'rrze-autoshare'),
                implode(', ', $termNames)
            );
        }

        return sprintf(
            /* translators: %s: Comma-separated tag names. */
            __('one of the tags %s', 'rrze-autoshare'),
            implode(', ', $termNames)
        );
    }

    private function renderTermRuleField(string $service, array $rule, string $type, string $label, int|string|null $index): void {
        $publicationRules = config()->get('general.publication_rules');
        $modeKey = $type . '_mode';
        $idsKey = $type . '_ids';
        $taxonomy = 'category' === $type ? 'category' : 'post_tag';
        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
        ]);
        ?>
        <fieldset class="rrze-autoshare-publication-term-rule" data-rule-type="<?php echo esc_attr($type); ?>">
            <legend><?php echo esc_html($label); ?></legend>
            <label>
                <input
                    name="<?php echo esc_attr($this->getPublicationRuleFieldName($service, $modeKey, $index)); ?>"
                    type="radio"
                    value="<?php echo esc_attr($publicationRules['all_terms']); ?>"
                    class="rrze-autoshare-publication-term-mode"
                    <?php checked($publicationRules['all_terms'] === $rule[$modeKey]); ?>
                >
                <?php esc_html_e('All', 'rrze-autoshare'); ?>
            </label>
            <label>
                <input
                    name="<?php echo esc_attr($this->getPublicationRuleFieldName($service, $modeKey, $index)); ?>"
                    type="radio"
                    value="<?php echo esc_attr($publicationRules['selected_terms']); ?>"
                    class="rrze-autoshare-publication-term-mode"
                    <?php checked($publicationRules['selected_terms'] === $rule[$modeKey]); ?>
                >
                <?php esc_html_e('Selected', 'rrze-autoshare'); ?>
            </label><br>
            <select class="rrze-autoshare-publication-term-ids" name="<?php echo esc_attr($this->getPublicationRuleFieldName($service, $idsKey, $index) . '[]'); ?>" multiple size="5">
                <?php if (!is_wp_error($terms)) { ?>
                    <?php foreach ($terms as $term) { ?>
                        <option value="<?php echo esc_attr($term->term_id); ?>" <?php selected(in_array($term->term_id, $rule[$idsKey], true)); ?>>
                            <?php echo esc_html($term->name); ?>
                        </option>
                    <?php } ?>
                <?php } ?>
            </select>
        </fieldset>
        <?php
    }

    private function renderPublicationTargetsField(string $service, array $rule, int|string|null $index): void {
        $targets = $this->getServiceTargets($service);
        if (empty($targets) || count($targets) === 1) {
            return;
        }

        $targetsKey = config()->get('general.publication_rules.targets_key');
        ?>
        <p>
            <label for="<?php echo esc_attr('rrze-autoshare-' . $service . '-targets-' . $index); ?>">
                <strong><?php esc_html_e('Targets', 'rrze-autoshare'); ?></strong>
            </label><br>
            <select
                id="<?php echo esc_attr('rrze-autoshare-' . $service . '-targets-' . $index); ?>"
                class="rrze-autoshare-publication-targets"
                name="<?php echo esc_attr($this->getPublicationRuleFieldName($service, $targetsKey, $index) . '[]'); ?>"
                multiple
                size="5"
            >
                <?php foreach ($targets as $target) { ?>
                    <option value="<?php echo esc_attr($target); ?>" <?php selected(in_array($target, $rule[$targetsKey], true)); ?>>
                        <?php echo esc_html($target); ?>
                    </option>
                <?php } ?>
            </select>
        </p>
        <?php
    }

    private function getPublicationStatusLabel(string $status): string {
        if ('publish' === $status) {
            return __('Publish', 'rrze-autoshare');
        }

        if ('draft' === $status) {
            return __('Draft', 'rrze-autoshare');
        }

        $statusObject = get_post_status_object($status);

        return $statusObject ? $statusObject->label : $status;
    }

    private function sendPostAccordingToRules(string $service, \WP_Post $post): array|false {
        if (
            !$this->isServiceActive($service)
            || !$this->isServiceAuthorized($service)
            || !$this->shouldPublishPostToService($service, $post, $post->post_status, '')
        ) {
            return ['skipped' => true];
        }

        if ('bluesky' === $service) {
            return BlueskyAPI::publishPost($post->ID, false);
        }

        if ('mastodon' === $service) {
            return MastodonAPI::publishPost($post->ID, false);
        }

        if ('matrix' === $service) {
            $results = [];
            foreach ($this->getPublicationTargetsForPost('matrix', $post, $post->post_status, '') as $roomId) {
                $result = MatrixAPI::publishPost($post->ID, $roomId, false);
                if (!is_array($result)) {
                    return false;
                }
                $results[$roomId] = $result;
            }

            if (empty($results)) {
                return ['skipped' => true];
            }

            return [
                'targets' => $results,
                'image_not_transferred' => in_array(true, wp_list_pluck($results, 'image_not_transferred'), true),
            ];
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

    private function registerMatrixSettings(): void {
        $settings = config()->get('services.matrix.settings');
        $page = $this->getSettingsPage('matrix');

        add_settings_section('rrze_autoshare_matrix', __('Matrix Settings', 'rrze-autoshare'), [$this, 'renderMatrixSectionDescription'], $page);
        add_settings_field($settings['domain'], __('Service URL', 'rrze-autoshare'), [$this, 'renderTextField'], $page, 'rrze_autoshare_matrix', ['name' => $settings['domain'], 'description' => __('The URL of the Matrix homeserver.', 'rrze-autoshare')]);
        add_settings_field(
            $settings['rooms'],
            __('Room IDs', 'rrze-autoshare'),
            [$this, 'renderTextareaField'],
            $page,
            'rrze_autoshare_matrix',
            [
                'name' => $settings['rooms'],
                'description' => __('Enter one Matrix room ID per line, for example !room:matrix.example.org.', 'rrze-autoshare'),
                'required' => true,
            ]
        );
        $this->registerServiceFeaturedImageField('matrix', $page, 'rrze_autoshare_matrix');
        $this->registerServiceFormatField('matrix', $page);
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
        $allowedFormats = config()->get('services.' . $service . '.allowed_formats', []);
        $contentType = $this->getContentTypeLabel($content['type']);
        $description = sprintf(
            /* translators: 1: Accepted content type, 2: Maximum number of characters. */
            __('Accepted content type: %1$s. Maximum post length: %2$d characters.', 'rrze-autoshare'),
            $contentType,
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
                'description' => $this->getFormatFieldDescription($content['type'], $allowedFormats),
            ]
        );
    }

    private function getContentTypeLabel(string $contentType): string {
        if ('rich_text' === $contentType) {
            return __('Rich text', 'rrze-autoshare');
        }

        return __('Text', 'rrze-autoshare');
    }

    private function getFormatFieldDescription(string $contentType, array $allowedFormats): string {
        $description = sprintf(
            /* translators: %s: Comma-separated list of supported placeholders. */
            __('Available placeholders: %s.', 'rrze-autoshare'),
            implode(', ', $allowedFormats)
        );

        return 'rich_text' === $contentType
            ? $description . ' ' . __('Empty lines are retained.', 'rrze-autoshare')
            : $description . ' ' . __('Empty lines are removed automatically.', 'rrze-autoshare');
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

    public function renderMatrixSectionDescription() {
        echo '<p>' . esc_html__('Matrix sends messages to configured rooms. The Matrix user represented by the access token must be a room member and have permission to send messages.', 'rrze-autoshare') . '</p>';
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
        } elseif ('matrix' === $tab) {
            $this->renderMatrixAccess();
        }
    }

    private function renderTransmissionTest(): void {
        $test = config()->get('transmission_test');
        $results = get_transient($test['result_transient_prefix'] . get_current_user_id());
        delete_transient($test['result_transient_prefix'] . get_current_user_id());
        ?>
        <hr>
        <h2><?php esc_html_e('Manually Send', 'rrze-autoshare'); ?></h2>
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
                    <?php $available = $this->isServiceActive($service) && $this->isServiceAuthorized($service); ?>
                    <label for="rrze-autoshare-transmission-test-<?php echo esc_attr($service); ?>">
                        <input
                            id="rrze-autoshare-transmission-test-<?php echo esc_attr($service); ?>"
                            name="<?php echo esc_attr($test['services_field']); ?>[]"
                            type="checkbox"
                            value="<?php echo esc_attr($service); ?>"
                            <?php checked($available); ?>
                            <?php disabled(!$available); ?>
                        >
                        <?php echo esc_html($label); ?>
                    </label><br>
                <?php } ?>
            </fieldset>
            <?php wp_nonce_field($test['nonce_action'], $test['nonce_field']); ?>
            <?php submit_button(__('Reshare Post According to Rules', 'rrze-autoshare'), 'secondary', 'submit', false); ?>
        </form>
        <?php
    }

    private function renderTransmissionTestResult($results): void {
        if (!is_array($results) || empty($results)) {
            return;
        }

        echo '<ul class="rrze-autoshare-transmission-test-results" aria-live="polite">';
        foreach ($results as $service => $result) {
            $success = false;
            if ('invalid_post' === $service) {
                $message = __('The post does not exist or you cannot edit it.', 'rrze-autoshare');
                $status = 'error';
            } elseif ('no_service' === $service) {
                $message = __('Select at least one active service.', 'rrze-autoshare');
                $status = 'error';
            } else {
                $label = $this->getServices()[$service] ?? $service;
                $success = is_array($result);
                $skipped = $success && !empty($result['skipped']);
                $imageNotTransferred = $success && !empty($result['image_not_transferred']);
                if ($skipped) {
                    $message = sprintf(
                        /* translators: %s: Service name. */
                        __('No rule applies to this post for %s.', 'rrze-autoshare'),
                        $label
                    );
                    $status = 'warning';
                } elseif ($imageNotTransferred) {
                    $message = sprintf(
                        /* translators: %s: Service name. */
                        __('The text was sent to %s successfully, but the featured image could not be transmitted.', 'rrze-autoshare'),
                        $label
                    );
                    $status = 'warning';
                } elseif ($success) {
                    $message = sprintf(
                        /* translators: %s: Service name. */
                        __('%s received the post successfully according to the rules.', 'rrze-autoshare'),
                        $label
                    );
                    $status = 'success';
                } else {
                    $message = sprintf(
                        /* translators: %s: Service name. */
                        __('%s could not receive the post. Check the log for details.', 'rrze-autoshare'),
                        $label
                    );
                    $status = 'error';
                }
            }

            $url = $success && !empty($result['url']) ? esc_url($result['url']) : '';
            $icon = $this->getTransmissionTestStatusIcon($status);
            echo '<li class="rrze-autoshare-transmission-test-result rrze-autoshare-transmission-test-result-' . esc_attr($status) . '">';
            echo '<span class="dashicons ' . esc_attr($icon) . '" aria-hidden="true"></span>';
            echo '<span>' . esc_html($message);
            if ($url !== '') {
                echo ' <a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('View post', 'rrze-autoshare') . '</a>';
            }
            echo '</span></li>';
        }
        echo '</ul>';
    }

    private function getTransmissionTestStatusIcon(string $status): string {
        if ('success' === $status) {
            return 'dashicons-yes-alt';
        }

        if ('warning' === $status) {
            return 'dashicons-warning';
        }

        return 'dashicons-dismiss';
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

    private function renderMatrixAccess(): void {
        $authorization = config()->get('services.matrix.authorization');
        $status = (string) filter_input(INPUT_GET, $authorization['notice_field'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        ?>
        <hr>
        <h2><?php esc_html_e('Access', 'rrze-autoshare'); ?></h2>
        <?php $this->renderMatrixAuthorizationNotice($status); ?>
        <?php if (MatrixAPI::isConnected()) { ?>
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                <input type="hidden" name="action" value="<?php echo esc_attr($authorization['revoke_action']); ?>">
                <?php wp_nonce_field($authorization['nonce_action'], $authorization['nonce_field']); ?>
                <?php submit_button(__('Revoke Access', 'rrze-autoshare'), 'secondary', 'submit', false); ?>
            </form>
        <?php } else { ?>
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" autocomplete="off">
                <input type="hidden" name="action" value="<?php echo esc_attr($authorization['authorize_action']); ?>">
                <p>
                    <label for="rrze-autoshare-matrix-access-token"><?php esc_html_e('Access Token', 'rrze-autoshare'); ?></label><br>
                    <input
                        id="rrze-autoshare-matrix-access-token"
                        name="<?php echo esc_attr($authorization['token_field']); ?>"
                        type="password"
                        value=""
                        class="regular-text"
                        autocomplete="new-password"
                        data-1p-ignore="true"
                        data-lpignore="true"
                        required
                    >
                </p>
                <p class="description"><?php esc_html_e('Create a Matrix access token for a user that is allowed to send messages in every configured room. The token is stored encrypted.', 'rrze-autoshare'); ?></p>
                <?php wp_nonce_field($authorization['nonce_action'], $authorization['nonce_field']); ?>
                <?php submit_button(__('Authorize Access', 'rrze-autoshare'), 'secondary', 'submit', false); ?>
            </form>
        <?php } ?>
        <?php
    }

    private function renderMatrixAuthorizationNotice(string $status): void {
        $messages = [
            'success' => ['success', __('Matrix access was authorized.', 'rrze-autoshare')],
            'missing_access_token' => ['error', __('Enter a Matrix access token.', 'rrze-autoshare')],
            'missing_homeserver_url' => ['error', __('Save a Matrix homeserver URL before authorizing access.', 'rrze-autoshare')],
            'access_token_rejected' => ['error', __('The Matrix homeserver rejected the access token.', 'rrze-autoshare')],
            'homeserver_url_invalid' => ['error', __('The Matrix homeserver URL does not provide the required Matrix client API. Check the URL and save the settings before authorizing access.', 'rrze-autoshare')],
            'connection_failed' => ['error', __('The Matrix homeserver could not be reached. Check the server URL and network connection.', 'rrze-autoshare')],
            'service_unavailable' => ['warning', __('The Matrix homeserver is temporarily unavailable. Try again later.', 'rrze-autoshare')],
            'unexpected_authorization_response' => ['error', __('The Matrix homeserver returned an unexpected response. No access token was saved.', 'rrze-autoshare')],
            'token_storage_failed' => ['error', __('The Matrix access token could not be stored securely. Check the server encryption configuration and the log.', 'rrze-autoshare')],
            'authorization_failed' => ['error', __('Matrix could not authorize access.', 'rrze-autoshare')],
            'revoked' => ['success', __('Matrix access was revoked.', 'rrze-autoshare')],
        ];
        if (!isset($messages[$status])) {
            return;
        }

        [$type, $message] = $messages[$status];
        echo '<div class="notice notice-' . esc_attr($type) . ' inline"><p>' . esc_html($message) . '</p></div>';
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
            'publication-rules' => __('Publication Rules', 'rrze-autoshare'),
            'transmission-test' => __('Manually Send', 'rrze-autoshare'),
            'bluesky' => __('Bluesky', 'rrze-autoshare'),
            'mastodon' => __('Mastodon', 'rrze-autoshare'),
            'matrix' => __('Matrix', 'rrze-autoshare'),
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
        $publicationRules = config()->get('general.publication_rules');
        $options[$publicationRules['mode_setting']] = $publicationRules['default_mode'];
        $options[$publicationRules['rules_setting']] = $this->getDefaultPublicationRules();
        $informativeLogging = config()->get('debug.informative_logging');
        $options[$informativeLogging['setting']] = $informativeLogging['default'];

        foreach (config()->get('services', []) as $service) {
            foreach ($service['settings'] as $key => $setting) {
                if (isset($service['defaults'][$key])) {
                    $options[$setting] = $service['defaults'][$key];
                }
            }
        }

        return $options;
    }

    private function getDefaultPublicationRules(): array {
        $rules = [];

        foreach (array_keys($this->getServices()) as $service) {
            $rules[$service] = config()->get('general.publication_rules.rule_defaults');
        }

        return $rules;
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

    private function canManageDebugging(): bool {
        if (is_multisite()) {
            return is_super_admin();
        }

        return current_user_can('manage_options');
    }
}
