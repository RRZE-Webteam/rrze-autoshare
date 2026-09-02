<?php

namespace RRZE\Autoshare;

defined('ABSPATH') || exit;

class Config {
    private array $config = [
        'slug' => 'rrze-autoshare',
        'option_name' => 'rrze_autoshare',
        'text_domain' => 'rrze-autoshare',
        'admin_page_slug' => 'rrze_autoshare',
        'admin_parent_slug' => 'options-general.php',
        'admin_menu_position' => 99,
        'general' => [
            'active_services' => [
                'setting' => 'active_services',
                'default' => [],
            ],
        ],
        'migrations' => [
            'bluesky_credentials' => 'rrze_autoshare_bluesky_credentials_migrated',
            'bluesky_tokens' => 'rrze_autoshare_bluesky_tokens_migrated',
            'encrypted_service_options' => 'rrze_autoshare_encrypted_service_options_migrated',
            'bluesky_legacy_credential_settings' => [
                'bluesky_identifier',
                'bluesky_password',
            ],
        ],
        'default_post_types' => ['post', 'page'],
        'excluded_post_types' => ['attachment', 'revision', 'nav_menu_item'],
        'assets' => [
            'admin_style_handle' => 'rrze-autoshare-admin',
            'admin_style_file' => 'build/css/rrze-autoshare-admin.css',
            'admin_script_handle' => 'rrze-autoshare-admin',
            'admin_script_file' => 'build/js/rrze-autoshare-admin.js',
            'admin_script_dependencies' => [
                'wp-components',
                'wp-data',
                'wp-edit-post',
                'wp-element',
                'wp-plugins',
            ],
            'admin_script_object_name' => 'autoshareObject',
        ],
        'user_agent' => [
            'org' => 'RRZE',
            'bot' => 'Autoshare',
            'info_url' => 'https://github.com/RRZE-Webteam/rrze-autoshare',
            'contact' => 'webmaster@fau.de',
            'format' => 'FAU-%1$s-%2$s/%3$s (+%4$s;mailto:%5$s)',
        ],
        'log_hooks' => [
            'error' => 'rrze.log.error',
            'warning' => 'rrze.log.warning',
            'notice' => 'rrze.log.notice',
            'info' => 'rrze.log.info',
        ],
        'publication_backoff' => [
            'transient_prefix' => 'rrze_autoshare_publication_backoff_',
            'retryable_status_codes' => [429, 500, 502, 503, 504],
            'rate_limit_delay' => 900,
            'server_error_delay' => 300,
            'maximum_delay' => DAY_IN_SECONDS,
        ],
        'encryption' => [
            'cipher_method' => 'aes-256-gcm',
            'legacy_cipher_method' => 'aes-256-cbc',
            'version_prefix' => 'v2:',
        ],
        'services' => [
            'bluesky' => [
                'label' => 'Bluesky',
                'settings' => [
                    'domain' => 'bluesky_domain',
                    'post_types' => 'bluesky_post_types',
                    'featured_image' => 'bluesky_featured_image',
                    'format' => 'bluesky_format',
                ],
                'options' => [
                    'access_jwt' => 'rrze_autoshare_bluesky_access_jwt',
                    'refresh_jwt' => 'rrze_autoshare_bluesky_refresh_jwt',
                    'did' => 'rrze_autoshare_bluesky_did',
                ],
                'meta' => [
                    'enabled' => 'rrze_autoshare_bluesky_enabled',
                    'sent' => 'rrze_autoshare_bluesky_sent',
                    'published' => 'rrze_autoshare_bluesky_published',
                    'error' => 'rrze_autoshare_bluesky_error',
                    'unknown' => 'rrze_autoshare_bluesky_unknown',
                ],
                'filters' => [
                    'title' => 'rrze_autoshare_bluesky_title',
                    'excerpt' => 'rrze_autoshare_bluesky_excerpt',
                    'hashtags' => 'rrze_autoshare_bluesky_hashtags',
                ],
                'hooks' => [
                    'publish_post' => 'rrze_autoshare_bluesky_publish_post',
                    'refresh_token' => 'rrze_autoshare_bluesky_refresh_token',
                ],
                'endpoints' => [
                    'create_session' => 'xrpc/com.atproto.server.createSession',
                    'refresh_session' => 'xrpc/com.atproto.server.refreshSession',
                    'create_record' => 'xrpc/com.atproto.repo.createRecord',
                    'upload_blob' => 'xrpc/com.atproto.repo.uploadBlob',
                ],
                'app_password' => [
                    'settings_url' => 'https://bsky.app/settings/app-passwords',
                    'info_url' => 'https://bsky.social/about/blog/5-19-2023-user-faq',
                    'pattern' => '/^[A-Za-z0-9]{4}(?:-[A-Za-z0-9]{4}){3}$/',
                ],
                'authorization' => [
                    'authorize_action' => 'rrze_autoshare_bluesky_authorize',
                    'revoke_action' => 'rrze_autoshare_bluesky_revoke',
                    'identifier_field' => 'rrze_autoshare_bluesky_identifier',
                    'password_field' => 'rrze_autoshare_bluesky_app_password',
                    'nonce_action' => 'rrze-autoshare-bluesky-authorize',
                    'nonce_field' => 'rrze_autoshare_bluesky_authorize_nonce',
                    'notice_field' => 'bluesky_authorization',
                ],
                'authentication' => [
                    'direct_token_input' => false,
                    'connection_callback' => ['RRZE\Autoshare\Services\Bluesky\API', 'isConnected'],
                    'invalid_status_codes' => [401, 403],
                ],
                'defaults' => [
                    'domain' => 'https://bsky.social',
                    'post_types' => ['post'],
                    'featured_image' => true,
                    'format' => "{title}\n{tags}\n{excerpt}\n{url}",
                ],
                'content' => [
                    'max_length' => 300,
                    'type' => 'text',
                    'length_is_instance_specific' => false,
                ],
                'limits' => [
                    'media_count' => 1,
                    'lang_code_length' => 2,
                    'timeout' => 15,
                ],
                'record' => [
                    'type' => 'app.bsky.feed.post',
                    'collection' => 'app.bsky.feed.post',
                    'embed_images_type' => 'app.bsky.embed.images',
                    'facet_link_type' => 'app.bsky.richtext.facet#link',
                ],
            ],
            'mastodon' => [
                'label' => 'Mastodon',
                'settings' => [
                    'domain' => 'mastodon_domain',
                    'username' => 'mastodon_username',
                    'post_types' => 'mastodon_post_types',
                    'featured_image' => 'mastodon_featured_image',
                    'format' => 'mastodon_format',
                    'authorize_access_url' => 'mastodon_authorize_access_url',
                ],
                'options' => [
                    'client_id' => 'rrze_autoshare_mastodon_client_id',
                    'client_secret' => 'rrze_autoshare_mastodon_client_secret',
                    'access_token' => 'rrze_autoshare_mastodon_access_token',
                ],
                'meta' => [
                    'enabled' => 'rrze_autoshare_mastodon_enabled',
                    'sent' => 'rrze_autoshare_mastodon_sent',
                    'published' => 'rrze_autoshare_mastodon_published',
                    'error' => 'rrze_autoshare_mastodon_error',
                    'unknown' => 'rrze_autoshare_mastodon_unknown',
                ],
                'filters' => [
                    'title' => 'rrze_autoshare_mastodon_title',
                    'excerpt' => 'rrze_autoshare_mastodon_excerpt',
                    'hashtags' => 'rrze_autoshare_mastodon_tags',
                ],
                'hooks' => [
                    'publish_post' => 'rrze_autoshare_mastodon_publish_post',
                ],
                'endpoints' => [
                    'apps' => '/api/v1/apps',
                    'token' => '/oauth/token',
                    'revoke' => '/oauth/revoke',
                    'verify_credentials' => '/api/v1/accounts/verify_credentials',
                    'statuses' => '/api/v1/statuses',
                    'media' => '/api/v1/media',
                    'authorize' => '/oauth/authorize',
                ],
                'defaults' => [
                    'domain' => 'https://mastodon.social',
                    'post_types' => ['post'],
                    'featured_image' => true,
                    'format' => "{title}\n{tags}\n{excerpt}\n{url}",
                ],
                'content' => [
                    'max_length' => 500,
                    'type' => 'text',
                    'length_is_instance_specific' => true,
                ],
                'limits' => [
                    'media_count' => 1,
                    'timeout' => 15,
                ],
                'oauth' => [
                    'client_name' => 'RRZE-Autoshare',
                    'scope' => 'write:media write:statuses read:accounts read:statuses',
                    'state_transient_prefix' => 'rrze_autoshare_mastodon_oauth_state_',
                    'state_lifetime' => 600,
                ],
                'authentication' => [
                    'direct_token_input' => false,
                    'connection_callback' => ['RRZE\Autoshare\Services\Mastodon\API', 'isConnected'],
                    'invalid_status_codes' => [401, 403],
                ],
            ],
        ],
    ];

    public function get(string $key, mixed $default = null): mixed {
        if (array_key_exists($key, $this->config)) {
            return $this->config[$key];
        }

        $value = $this->config;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function set(string $key, mixed $value): void {
        $this->config[$key] = $value;
    }

    public function getUserAgent(): string {
        return sprintf(
            $this->get('user_agent.format'),
            $this->get('user_agent.org'),
            $this->get('user_agent.bot'),
            plugin()->getVersion(),
            $this->get('user_agent.info_url'),
            $this->get('user_agent.contact')
        );
    }

    public static function validateUrl(mixed $value): bool {
        return (bool) filter_var($value, FILTER_VALIDATE_URL);
    }

    public static function validateBlueskyServiceUrl(mixed $value): bool {
        if (!is_string($value)) {
            return false;
        }

        return untrailingslashit($value) === untrailingslashit(
            config()->get('services.bluesky.defaults.domain')
        );
    }

    public static function validateBlueskyAppPassword(mixed $value): bool {
        if (!is_string($value) || $value === '') {
            return true;
        }

        if (str_contains($value, '•••')) {
            return true;
        }

        return preg_match(config()->get('services.bluesky.app_password.pattern'), $value) === 1;
    }

    public static function validatePostFormat(mixed $value): bool {
        return is_string($value) && $value !== '';
    }
}
