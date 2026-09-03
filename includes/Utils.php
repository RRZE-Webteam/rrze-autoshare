<?php

namespace RRZE\Autoshare;

defined('ABSPATH') || exit;

class Utils {
    public static function remoteRequest(string $method, string $url, array $args, int $timeout) {
        $args['method'] = strtoupper($method);
        $args['user-agent'] = config()->getUserAgent();
        $args['timeout'] = $timeout;

        return wp_safe_remote_request(esc_url_raw($url), $args);
    }

    public static function getJsonResponseBody(
        $response,
        string $service,
        string $operation,
        array $context = []
    ): ?array {
        if (is_wp_error($response)) {
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (JSON_ERROR_NONE === json_last_error() && is_array($data)) {
            return $data;
        }

        if (wp_remote_retrieve_response_code($response) < 300) {
            self::logRemoteWarning(
                $service,
                $operation,
                'The API response did not contain valid JSON.',
                $context
            );
        }

        return null;
    }

    public static function log(string $level, string $message, array $context = []): void {
        if (
            'info' === $level
            && !settings()->getOption(config()->get('debug.informative_logging.setting'))
        ) {
            return;
        }

        $logHook = config()->get('log_hooks.' . $level);
        if (!$logHook) {
            return;
        }

        do_action(
            $logHook,
            $message,
            array_merge(
                $context,
                ['plugin' => 'rrze-autoshare']
            )
        );
    }

    public static function logRemoteError(string $service, string $operation, $response, array $context = []): void {
        self::log(
            'error',
            sprintf('%s API request failed during %s.', $service, $operation),
            array_merge(
                [
                    'service' => $service,
                    'operation' => $operation,
                ],
                self::getRemoteResponseContext($response),
                $context
            )
        );
    }

    public static function logRemoteWarning(string $service, string $operation, string $message, array $context = []): void {
        self::log(
            'warning',
            $message,
            array_merge(
                [
                    'service' => $service,
                    'operation' => $operation,
                ],
                $context
            )
        );
    }

    public static function getLoggablePayload(array $payload): array {
        $loggablePayload = [];
        $excludedKeys = config()->get('logging.payload_excluded_keys', []);

        foreach ($payload as $key => $value) {
            $normalizedKey = sanitize_key((string) $key);
            if (in_array($normalizedKey, $excludedKeys, true)) {
                continue;
            }

            if (is_array($value)) {
                $loggablePayload[$key] = self::getLoggablePayload($value);
            } elseif (is_scalar($value) || null === $value) {
                $loggablePayload[$key] = is_string($value)
                    ? sanitize_text_field($value)
                    : $value;
            }
        }

        return $loggablePayload;
    }

    public static function isAuthorizationFailure($response, array $statusCodes): bool {
        if (is_wp_error($response)) {
            return false;
        }

        return in_array(
            wp_remote_retrieve_response_code($response),
            $statusCodes,
            true
        );
    }

    public static function deferPublication(string $service, $response, string $operation, array $context = []): bool {
        if (is_wp_error($response)) {
            return false;
        }

        $responseCode = wp_remote_retrieve_response_code($response);
        if (!in_array($responseCode, config()->get('publication_backoff.retryable_status_codes'), true)) {
            return false;
        }

        $retryAt = self::getPublicationRetryAt($response, $responseCode);
        $delay = max(1, $retryAt - time());
        set_transient(
            self::getPublicationBackoffKey($service),
            [
                'retry_at' => $retryAt,
                'response_code' => $responseCode,
            ],
            $delay
        );

        self::logRemoteWarning(
            ucfirst($service),
            $operation,
            'Publication is temporarily paused after a remote API rate limit or server error.',
            array_merge(
                [
                    'response_code' => $responseCode,
                    'retry_at' => gmdate('c', $retryAt),
                    'retry_delay' => $delay,
                ],
                $context
            )
        );

        return true;
    }

    public static function isPublicationDeferred(string $service): bool {
        return false !== get_transient(self::getPublicationBackoffKey($service));
    }

    private static function getPublicationRetryAt($response, int $responseCode): int {
        $defaultDelay = 429 === $responseCode
            ? config()->get('publication_backoff.rate_limit_delay')
            : config()->get('publication_backoff.server_error_delay');
        $retryAt = time() + $defaultDelay;

        if (429 !== $responseCode) {
            return $retryAt;
        }

        $resetHeader = wp_remote_retrieve_header($response, 'x-ratelimit-reset');
        $resetAt = is_string($resetHeader) ? strtotime($resetHeader) : false;
        if (false !== $resetAt && $resetAt > time()) {
            $retryAt = $resetAt;
        }

        return min($retryAt, time() + config()->get('publication_backoff.maximum_delay'));
    }

    private static function getPublicationBackoffKey(string $service): string {
        return config()->get('publication_backoff.transient_prefix') . sanitize_key($service);
    }

    private static function getRemoteResponseContext($response): array {
        if (is_wp_error($response)) {
            return [
                'wp_error_code' => $response->get_error_code(),
                'wp_error_message' => $response->get_error_message(),
            ];
        }

        return [
            'response_code' => wp_remote_retrieve_response_code($response),
            'response_message' => wp_remote_retrieve_response_message($response),
        ];
    }

    public static function getTheTags($postId) {
        $tags = [];
        if (!$taxonomies = self::getNonHierarchicalTaxonomies($postId)) {
            return $tags;
        }

        foreach ($taxonomies as $taxonomy) {
            $tags = array_merge($tags, self::getTerms($postId, $taxonomy->name));
        }
        return $tags;
    }

    public static function getPostHashtags(int $postId): array {
        $hashtags = [];

        foreach (self::getTheTags($postId) as $tag) {
            if (empty($tag->name)) {
                continue;
            }

            $tagName = sanitize_text_field(wp_strip_all_tags($tag->name));
            $tagName = preg_replace('/[\s-]+/u', '', $tagName);
            $tagName = preg_replace('/[^\p{L}\p{N}_]/u', '', $tagName);

            if ($tagName !== '') {
                $hashtags[] = '#' . $tagName;
            }
        }

        return array_values(array_unique($hashtags));
    }

    public static function getNonHierarchicalTaxonomies($postId) {
        $taxonomies = get_object_taxonomies(get_post_type($postId), 'objects');

        return array_filter($taxonomies, [__CLASS__, 'isNonHierarchicalTaxonomy']);
    }

    public static function isNonHierarchicalTaxonomy($taxonomy): bool {
        return !$taxonomy->hierarchical;
    }

    public static function getTerms($postId, $taxonomy) {
        $terms = get_the_terms($postId, $taxonomy);
        if ($terms && !is_wp_error($terms)) {
            return $terms;
        }

        return [];
    }

    public static function getImages(\WP_Post $post, string $service): array {
        $featuredImageEnabled = has_post_thumbnail($post->ID) && settings()->getOption(
            config()->get('services.' . $service . '.settings.featured_image')
        );
        if (!$featuredImageEnabled) {
            return [];
        }

        $featuredImageId = absint(get_post_thumbnail_id($post->ID));
        if (!$featuredImageId) {
            return [];
        }

        return self::addAltText([$featuredImageId]);
    }

    public static function addAltText(array $imageIds): array {
        $images = [];

        foreach (array_unique(array_map('absint', $imageIds)) as $imageId) {
            if (!$imageId) {
                continue;
            }

            $alt = get_post_meta($imageId, '_wp_attachment_image_alt', true);
            if ($alt === '') {
                $alt = wp_get_attachment_caption($imageId);
            }

            $images[$imageId] = is_string($alt)
                ? html_entity_decode($alt, ENT_QUOTES | ENT_HTML5, get_bloginfo('charset'))
                : '';
        }

        return $images;
    }

    public static function getServiceMetadata(\WP_Post $post, string $service): array {
        $metadata = [];

        foreach (config()->get('services.' . $service . '.metadata', []) as $mapping) {
            $callback = $mapping['callback'] ?? null;
            $field = $mapping['field'] ?? '';
            if (!is_callable($callback) || !is_string($field) || $field === '') {
                continue;
            }

            $value = call_user_func($callback, $post);
            if ($value !== '' && $value !== [] && $value !== null) {
                $metadata[$field] = $value;
            }
        }

        return $metadata;
    }

    public static function getServiceExternalEmbed(\WP_Post $post, string $service): array {
        $embed = config()->get('services.' . $service . '.external_embed', []);
        $field = $embed['field'] ?? '';
        $wrapperField = $embed['wrapper_field'] ?? '';
        $type = $embed['type'] ?? '';
        if (!is_string($field) || !is_string($wrapperField) || !is_string($type)) {
            return [];
        }

        $external = [];
        foreach ($embed['mappings'] ?? [] as $name => $mapping) {
            $callback = $mapping['callback'] ?? null;
            if (!is_callable($callback)) {
                continue;
            }

            $value = call_user_func($callback, $post);
            if ($value !== '' && $value !== null) {
                $external[$name] = $value;
            }
        }

        if (empty($external['uri']) || empty($external['title'])) {
            return [];
        }

        if (!array_key_exists('description', $external)) {
            $external['description'] = '';
        }

        return [
            $field => [
                '$type' => $type,
                $wrapperField => $external,
            ],
        ];
    }

    public static function getPostLanguage(\WP_Post $post): string {
        return strtolower(substr(get_locale(), 0, 2));
    }

    public static function getPostLanguages(\WP_Post $post): array {
        $language = self::getPostLanguage($post);

        return $language === '' ? [] : [$language];
    }

    public static function getPostTagNames(\WP_Post $post): array {
        return array_slice(
            array_map(
                [__CLASS__, 'removeHashtagPrefix'],
                self::getPostHashtags($post->ID)
            ),
            0,
            8
        );
    }

    public static function removeHashtagPrefix(string $tag): string {
        return ltrim($tag, '#');
    }

    public static function getPublicationTimestamp(\WP_Post $post): string {
        return gmdate('c');
    }

    public static function getPostPermalink(\WP_Post $post): string {
        return esc_url_raw(get_permalink($post));
    }

    public static function getPostTitle(\WP_Post $post): string {
        return sanitize_text_field($post->post_title);
    }

    public static function getPostContent(\WP_Post $post, string $service): string {
        $permalink = esc_url_raw(get_the_permalink($post->ID));
        $title = apply_filters(
            config()->get('services.' . $service . '.filters.title'),
            $post->post_title
        );
        $title = sanitize_text_field($title);
        $excerpt = apply_filters(
            config()->get('services.' . $service . '.filters.excerpt'),
            self::getPostExcerpt($post)
        );
        $excerpt = sanitize_textarea_field($excerpt);
        $tags = apply_filters(
            config()->get('services.' . $service . '.filters.hashtags'),
            self::getPostHashtags($post->ID)
        );
        $tags = is_array($tags) ? $tags : [];
        $tags = array_filter(array_map('sanitize_text_field', $tags));

        return self::formatPostContent(
            settings()->getOption(config()->get('services.' . $service . '.settings.format')),
            [
                '{title}' => $title,
                '{excerpt}' => $excerpt,
                '{url}' => $permalink,
                '{tags}' => !empty($tags) ? implode(' ', $tags) : '',
            ],
            config()->get('services.' . $service . '.content.max_length')
        );
    }

    public static function getPostExcerpt(\WP_Post $post): string {
        $excerpt = $post->post_excerpt;
        if ('' === trim(wp_strip_all_tags($excerpt))) {
            $excerpt = $post->post_content;
        }

        $excerpt = strip_shortcodes($excerpt);
        $excerpt = preg_replace('~$excerptMore$~', '', $excerpt);
        $excerpt = wp_strip_all_tags($excerpt, true);
        $excerpt = preg_replace('/\s+/u', ' ', $excerpt);
        $excerpt = is_string($excerpt) ? trim($excerpt) : '';

        return html_entity_decode(
            sanitize_textarea_field($excerpt),
            ENT_QUOTES | ENT_HTML5,
            get_bloginfo('charset')
        );
    }

    public static function formatPostContent(string $format, array $placeholders, int $maxLength): string {
        $truncatablePlaceholders = ['{excerpt}', '{title}', '{tags}'];
        $content = self::renderPostFormat($format, $placeholders);
        $placeholderIndex = 0;

        while (
            self::getPostContentLength($content) > $maxLength
            && $placeholderIndex < count($truncatablePlaceholders)
        ) {
            $placeholder = $truncatablePlaceholders[$placeholderIndex];
            $value = $placeholders[$placeholder] ?? '';

            if ($value === '') {
                $placeholderIndex++;
                continue;
            }

            $placeholders[$placeholder] = self::shortenPostContentSegment($value);
            $content = self::renderPostFormat($format, $placeholders);
        }

        if (self::getPostContentLength($content) > $maxLength) {
            return self::getPostContentSubstring($content, $maxLength);
        }

        return $content;
    }

    private static function renderPostFormat(string $format, array $placeholders): string {
        $lines = preg_split('/\R/', strtr($format, $placeholders));
        $content = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $content[] = $line;
            }
        }

        return html_entity_decode(
            implode(PHP_EOL, $content),
            ENT_QUOTES | ENT_HTML5,
            get_bloginfo('charset')
        );
    }

    private static function shortenPostContentSegment(string $value): string {
        $value = trim($value);
        $position = mb_strrpos($value, ' ');

        if ($position !== false) {
            return rtrim(mb_substr($value, 0, $position));
        }

        return self::getPostContentSubstring($value, self::getPostContentLength($value) - 1);
    }

    private static function getPostContentLength(string $value): int {
        if (function_exists('grapheme_strlen')) {
            return grapheme_strlen($value);
        }

        return mb_strlen($value);
    }

    private static function getPostContentSubstring(string $value, int $length): string {
        if ($length < 1) {
            return '';
        }

        if (function_exists('grapheme_substr')) {
            return grapheme_substr($value, 0, $length);
        }

        return mb_substr($value, 0, $length);
    }
}
