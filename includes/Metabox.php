<?php

namespace RRZE\Autoshare;

defined('ABSPATH') || exit;

use RRZE\Autoshare\Services\Bluesky\Main as Bluesky;
use RRZE\Autoshare\Services\Mastodon\Main as Mastodon;

class Metabox {
    public static function init() {
        add_action('add_meta_boxes', [__CLASS__, 'autoshareMetabox']);
        // add_action('wp_ajax_rrze_autoshare_update_metabox', [__CLASS__, 'updateMetabox'], 10, 2);
    }

    public static function autoshareMetabox($postType) {
        if (
            !self::isServiceAvailableForPostType('bluesky', $postType)
            && !self::isServiceAvailableForPostType('mastodon', $postType)
        ) {
            return;
        }

        add_meta_box(
            'rrze_autoshare_metabox',
            __('Autoshare', 'rrze-autoshare'),
            [__CLASS__, 'renderSubmitbox'],
            null,
            'side',
            'high',
            [
                '__back_compat_meta_box' => true,
            ]
        );
    }

    public static function updateMetabox() {
        $postId = $_POST['postId'] ?? null;
        $post = get_post(absint($postId));
        $content = Metabox::renderSubmitbox($post);
        echo $content;
    }

    public static function renderSubmitbox($post) {
        echo '<ul id="rrze_autoshare_metabox__ul">';
        if (settings()->isServiceActive('bluesky')) {
            echo self::blueskyMarkup($post);
        }
        if (settings()->isServiceActive('mastodon')) {
            echo self::mastodonMarkup($post);
        }
        echo '</ul>';
    }

    private static function blueskyMarkup($post) {
        $metaKey = config()->get('services.bluesky.meta.enabled');
        $isEnabled = metadata_exists('post', $post->ID, $metaKey) ? Bluesky::isEnabled($post->ID) : true;
        $isSent = Bluesky::isSent($post->ID);
        $isPublished = Bluesky::isPublished($post->ID);
        $isConnected = Bluesky::isConnected();
        $checked = $isConnected && $isEnabled && !$isPublished;
        $disabled = !$isConnected || $isSent || $isPublished ? ' disabled' : '';
        $disabledClass = $disabled ? 'class = "rrze-autoshare-disabled_input__label" ' : '';
        $label = !$disabled ? __('Share on Bluesky', 'rrze-autoshare') : __('Share on Bluesky is disabled', 'rrze-autoshare');
        $label = $isPublished ? __('It is published on Bluesky', 'rrze-autoshare') : $label;
        ob_start();
?>
        <li>
            <input type="checkbox" id="rrze-autoshare-bluesky-enabled" name="<?php echo esc_attr($metaKey); ?>" value="1" <?php checked($checked); ?><?php echo $disabled; ?>>
            <label <?php echo $disabledClass; ?>for="rrze-autoshare-bluesky-enabled">
                <?php echo esc_html($label); ?>
            </label>
        </li>
    <?php
        return ob_get_clean();
    }

    private static function mastodonMarkup($post) {
        $metaKey = config()->get('services.mastodon.meta.enabled');
        $isEnabled = metadata_exists('post', $post->ID, $metaKey) ? Mastodon::isEnabled($post->ID) : true;
        $isSent = Mastodon::isSent($post->ID);
        $isPublished = Mastodon::isPublished($post->ID);
        $isConnected = Mastodon::isConnected();
        $checked = $isConnected && $isEnabled && !$isPublished;
        $disabled = !$isConnected || $isPublished ? ' disabled' : '';
        $disabledClass = $disabled ? 'class = "rrze-autoshare-disabled_input__label" ' : '';
        $label = !$disabled ? __('Share on Mastodon', 'rrze-autoshare') : __('Share on Mastodon is disabled', 'rrze-autoshare');
        $label = $isPublished ? __('It is published on Mastodon', 'rrze-autoshare') : $label;
        ob_start();
    ?>
        <li>
            <input type="checkbox" id="rrze-autoshare-mastodon-enabled" name="<?php echo esc_attr($metaKey); ?>" value="1" <?php checked($checked); ?><?php echo $disabled; ?>>
            <label <?php echo $disabledClass; ?>for="rrze-autoshare-mastodon-enabled">
                <?php echo esc_html($label); ?>
            </label>
        </li>
    <?php
        return ob_get_clean();
    }

    private static function isServiceAvailableForPostType(string $service, string $postType): bool {
        return (
            settings()->isServiceActive($service)
            && in_array(
                $postType,
                settings()->getOption(config()->get('services.' . $service . '.settings.post_types')),
                true
            )
        );
    }
}
