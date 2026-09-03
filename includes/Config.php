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
            'publication_rules' => [
                'mode_setting' => 'publication_rule_mode',
                'rules_setting' => 'publication_rules',
                'targets_key' => 'targets',
                'default_mode' => 'default',
                'advanced_mode' => 'advanced',
                'all_terms' => 'all',
                'selected_terms' => 'selected',
                'rule_defaults' => [
                    'status' => 'publish',
                    'category_mode' => 'all',
                    'category_ids' => [],
                    'tag_mode' => 'all',
                    'tag_ids' => [],
                ],
            ],
        ],
        'debug' => [
            'informative_logging' => [
                'setting' => 'informative_logging',
                'default' => false,
            ],
        ],
        'transmission_test' => [
            'action' => 'rrze_autoshare_transmission_test',
            'nonce_action' => 'rrze-autoshare-transmission-test',
            'nonce_field' => 'rrze_autoshare_transmission_test_nonce',
            'post_id_field' => 'rrze_autoshare_transmission_test_post_id',
            'post_search_id' => 'rrze-autoshare-transmission-test-post-search',
            'post_id_input_id' => 'rrze-autoshare-transmission-test-post-id',
            'post_search_results_id' => 'rrze-autoshare-transmission-test-post-results',
            'post_search_status_id' => 'rrze-autoshare-transmission-test-post-status',
            'services_field' => 'rrze_autoshare_transmission_test_services',
            'result_transient_prefix' => 'rrze_autoshare_transmission_test_result_',
        ],
        'migrations' => [
            'bluesky_credentials' => 'rrze_autoshare_bluesky_credentials_migrated',
            'bluesky_tokens' => 'rrze_autoshare_bluesky_tokens_migrated',
            'encrypted_service_options' => 'rrze_autoshare_encrypted_service_options_migrated',
            'mastodon_legacy_service_options' => [
                'rrze_autoshare_mastodon_client_id',
                'rrze_autoshare_mastodon_client_secret',
            ],
            'bluesky_legacy_credential_settings' => [
                'bluesky_identifier',
                'bluesky_password',
            ],
        ],
        'default_post_types' => ['post'],
        'post_meta' => [
            'enabled' => 'rrze_autoshare_enabled',
        ],
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
            'settings_script_handle' => 'rrze-autoshare-settings',
            'settings_script_file' => 'build/js/rrze-autoshare-settings.js',
            'settings_script_dependencies' => ['wp-api-fetch'],
            'settings_script_object_name' => 'autoshareSettingsObject',
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
        'logging' => [
            'payload_excluded_keys' => [
                'access_jwt',
                'access_token',
                'authorization',
                'blob',
                'body',
                'client_secret',
                'content',
                'html',
                'image',
                'password',
                'refresh_jwt',
                'status',
                'text',
                'thumb',
            ],
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
                'publication_statuses' => ['publish'],
                'settings' => [
                    'domain' => 'bluesky_domain',
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
                    'featured_image' => true,
                    'format' => "{title}\n{excerpt}\n{url}",
                ],
                'content' => [
                    'max_length' => 300,
                    'type' => 'text',
                    'length_is_instance_specific' => false,
                ],
                'allowed_formats' => ['{title}', '{excerpt}', '{url}', '{tags}', '{content}'],
                'limits' => [
                    'media_count' => 1,
                    'timeout' => 15,
                ],
                'record' => [
                    'type' => 'app.bsky.feed.post',
                    'collection' => 'app.bsky.feed.post',
                    'embed_images_type' => 'app.bsky.embed.images',
                    'embed_external_type' => 'app.bsky.embed.external',
                    'facet_link_type' => 'app.bsky.richtext.facet#link',
                ],
                'urls' => [
                    'post' => 'https://bsky.app/profile/%1$s/post/%2$s',
                ],
                'metadata' => [
                    'languages' => [
                        'field' => 'langs',
                        'callback' => ['RRZE\Autoshare\Utils', 'getPostLanguages'],
                    ],
                    'tags' => [
                        'field' => 'tags',
                        'callback' => ['RRZE\Autoshare\Utils', 'getPostTagNames'],
                    ],
                    'created_at' => [
                        'field' => 'createdAt',
                        'callback' => ['RRZE\Autoshare\Utils', 'getPublicationTimestamp'],
                    ],
                ],
                'external_embed' => [
                    'field' => 'embed',
                    'wrapper_field' => 'external',
                    'type' => 'app.bsky.embed.external',
                    'mappings' => [
                        'uri' => ['callback' => ['RRZE\Autoshare\Utils', 'getPostPermalink']],
                        'title' => ['callback' => ['RRZE\Autoshare\Utils', 'getPostTitle']],
                        'description' => ['callback' => ['RRZE\Autoshare\Utils', 'getPostExcerpt']],
                    ],
                ],
            ],
            'mastodon' => [
                'label' => 'Mastodon',
                'publication_statuses' => ['publish'],
                'settings' => [
                    'domain' => 'mastodon_domain',
                    'featured_image' => 'mastodon_featured_image',
                    'format' => 'mastodon_format',
                ],
                'options' => [
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
                    'verify_credentials' => '/api/v1/accounts/verify_credentials',
                    'statuses' => '/api/v1/statuses',
                    'media' => '/api/v1/media',
                ],
                'defaults' => [
                    'domain' => 'https://mastodon.social',
                    'featured_image' => true,
                    'format' => "{title}\n{tags}\n{excerpt}\n{url}",
                ],
                'content' => [
                    'max_length' => 500,
                    'type' => 'text',
                    'length_is_instance_specific' => true,
                ],
                'allowed_formats' => ['{title}', '{excerpt}', '{url}', '{tags}', '{content}'],
                'metadata' => [
                    'language' => [
                        'field' => 'language',
                        'callback' => ['RRZE\Autoshare\Utils', 'getPostLanguage'],
                    ],
                ],
                'limits' => [
                    'media_count' => 1,
                    'timeout' => 15,
                ],
                'authorization' => [
                    'authorize_action' => 'rrze_autoshare_mastodon_authorize',
                    'revoke_action' => 'rrze_autoshare_mastodon_revoke',
                    'token_field' => 'rrze_autoshare_mastodon_access_token',
                    'nonce_action' => 'rrze-autoshare-mastodon-authorize',
                    'nonce_field' => 'rrze_autoshare_mastodon_authorize_nonce',
                    'notice_field' => 'mastodon_authorization',
                    'application_settings_path' => '/settings/applications',
                    'info_url' => 'https://docs.joinmastodon.org/client/authorized/',
                ],
                'authentication' => [
                    'direct_token_input' => true,
                    'connection_callback' => ['RRZE\Autoshare\Services\Mastodon\API', 'isConnected'],
                    'invalid_status_codes' => [401, 403],
                ],
            ],
            'matrix' => [
                'label' => 'Matrix',
                'publication_statuses' => ['publish', 'pending'],
                'settings' => [
                    'domain' => 'matrix_domain',
                    'rooms' => 'matrix_rooms',
                    'featured_image' => 'matrix_featured_image',
                    'format' => 'matrix_format',
                ],
                'targets' => [
                    'setting' => 'matrix_rooms',
                    'validation_callback' => ['RRZE\\Autoshare\\Settings', 'isValidMatrixRoomId'],
                ],
                'rules' => [
                    'multiple' => true,
                ],
                'options' => [
                    'access_token' => 'rrze_autoshare_matrix_access_token',
                ],
                'meta' => [
                    'sent_prefix' => 'rrze_autoshare_matrix_sent_',
                    'published_prefix' => 'rrze_autoshare_matrix_published_',
                ],
                'filters' => [
                    'title' => 'rrze_autoshare_matrix_title',
                    'excerpt' => 'rrze_autoshare_matrix_excerpt',
                    'hashtags' => 'rrze_autoshare_matrix_tags',
                ],
                'hooks' => [
                    'publish_post' => 'rrze_autoshare_matrix_publish_post',
                ],
                'endpoints' => [
                    'whoami' => '/_matrix/client/v3/account/whoami',
                    'send_message' => '/_matrix/client/v3/rooms/%1$s/send/m.room.message/%2$s',
                    'upload_media' => '/_matrix/media/v3/upload',
                ],
                'defaults' => [
                    'domain' => 'https://matrix.fau.de',
                    'rooms' => '',
                    'featured_image' => true,
                    'format' => "{title}\n{content_html}\n\n{url}",
                ],
                'content' => [
                    'max_length' => 60000,
                    'type' => 'rich_text',
                    'length_is_instance_specific' => true,
                ],
                'allowed_formats' => ['{title}', '{excerpt}', '{url}', '{tags}', '{content}', '{content_html}'],
                'limits' => [
                    'media_count' => 1,
                    'timeout' => 15,
                ],
                'authorization' => [
                    'authorize_action' => 'rrze_autoshare_matrix_authorize',
                    'revoke_action' => 'rrze_autoshare_matrix_revoke',
                    'token_field' => 'rrze_autoshare_matrix_authorization_token',
                    'nonce_action' => 'rrze-autoshare-matrix-authorize',
                    'nonce_field' => 'rrze_autoshare_matrix_authorize_nonce',
                    'notice_field' => 'matrix_authorization',
                    'info_url' => 'https://spec.matrix.org/latest/client-server-api/#using-access-tokens',
                ],
                'authentication' => [
                    'direct_token_input' => true,
                    'connection_callback' => ['RRZE\\Autoshare\\Services\\Matrix\\API', 'isConnected'],
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
