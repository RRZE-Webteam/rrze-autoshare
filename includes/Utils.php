<?php

namespace RRZE\Autoshare;

defined('ABSPATH') || exit;

class Utils {
    public static function log(string $level, string $message, array $context = []): void {
        $logHook = config()->get('log_hooks.' . $level);
        if (!$logHook) {
            return;
        }

        do_action($logHook, $message, $context);
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
