<?php

namespace RRZE\Autoshare;

defined('ABSPATH') || exit;

class Utils
{
    public static function getTheTags($postId)
    {
        $tags = [];
        if (!$taxonomies = self::getNonHierarchicalTaxonomies($postId)) {
            return $tags;
        }

        foreach ($taxonomies as $taxonomy) {
            $tags = array_merge($tags, self::getTerms($postId, $taxonomy->name));
        }
        return $tags;
    }

    public static function getNonHierarchicalTaxonomies($postId)
    {
        $taxonomies = get_object_taxonomies(get_post_type($postId), 'objects');

        return array_filter($taxonomies, function ($taxonomy) {
            return !$taxonomy->hierarchical;
        });
    }

    public static function getTerms($postId, $taxonomy)
    {
        $terms = get_the_terms($postId, $taxonomy);
        if ($terms && !is_wp_error($terms)) {
            return $terms;
        }

        return [];
    }
}
