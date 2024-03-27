<?php

namespace RRZE\Autoshare;

defined('ABSPATH') || exit;

use RRZE\Autoshare\Services\Bluesky\Main as Bluesky;
use RRZE\Autoshare\Services\Mastodon\Main as Mastodon;
use RRZE\Autoshare\Services\Twitter\Main as Twitter;

class Metabox
{
    public static function init()
    {
        add_action('add_meta_boxes', [__CLASS__, 'autoshareMetabox'], 10, 2);
        // add_action('wp_ajax_rrze_autoshare_update_metabox', [__CLASS__, 'updateMetabox'], 10, 2);
    }

    public static function autoshareMetabox($postType, $post)
    {
        if (
            !in_array($postType, settings()->getOption('bluesky_post_types'))
            && !in_array($postType, settings()->getOption('mastodon_post_types'))
            && !in_array($postType, settings()->getOption('twitter_post_types'))
        ) {
            return;
        }

        add_meta_box(
            'rrze_autoshare_metabox',
            __('Autoshare', 'rrze_autoshare'),
            [__CLASS__, 'renderSubmitbox'],
            null,
            'side',
            'high',
            [
                '__back_compat_meta_box' => true,
            ]
        );
    }

    public static function updateMetabox()
    {
        $postId = $_POST['postId'] ?? null;
        $post = get_post(absint($postId));
        $content = Metabox::renderSubmitbox($post);
        echo $content;
    }

    public static function renderSubmitbox($post)
    {
        echo '<ul id="rrze_autoshare_metabox__ul">';
        echo self::blueskyMarkup($post);
        echo self::mastodonMarkup($post);
        echo self::twitterMarkup($post);
        echo '</ul>';
    }

    private static function blueskyMarkup($post)
    {
        $metaKey = 'rrze_autoshare_bluesky_enabled';
        $blueskyEnableByDefault = (bool) settings()->getOption('bluesky_enable_default');
        $isEnabled = metadata_exists('post', $post->ID, $metaKey) ? Bluesky::isEnabled($post->ID) : $blueskyEnableByDefault;
        $isPublished = Bluesky::isPublished($post->ID);
        $isConnected = Bluesky::isConnected();
        $checked = $isConnected && $isEnabled && !$isPublished;
        $disabled = !$isConnected || $isPublished ? ' disabled' : '';
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

    private static function mastodonMarkup($post)
    {
        $metaKey = 'rrze_autoshare_mastodon_enabled';
        $mastodonEnableByDefault = (bool) settings()->getOption('mastodon_enable_default');
        $isEnabled = metadata_exists('post', $post->ID, $metaKey) ? Mastodon::isEnabled($post->ID) : $mastodonEnableByDefault;
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

    private static function twitterMarkup($post)
    {
        $metaKey = 'rrze_autoshare_twitter_enabled';
        $twitterEnableByDefault = (bool) settings()->getOption('bluesky_enable_default');
        $isEnabled = metadata_exists('post', $post->ID, $metaKey) ? Twitter::isEnabled($post->ID) : $twitterEnableByDefault;
        $isPublished = Twitter::isPublished($post->ID);
        $isConnected = Twitter::isConnected();
        $checked = $isConnected && $isEnabled && !$isPublished;
        $disabled = !$isConnected || $isPublished ? ' disabled' : '';
        $disabledClass = $disabled ? 'class = "rrze-autoshare-disabled_input__label" ' : '';
        $label = !$disabled ? __('Share on X (Twitter)', 'rrze-autoshare') : __('Share on X is disabled', 'rrze-autoshare');
        $label = $isPublished ? __('It is published on X', 'rrze-autoshare') : $label;
        ob_start();
    ?>
        <li>
            <input type="checkbox" id="rrze-autoshare-twitter-enabled" name="<?php echo esc_attr($metaKey); ?>" value="1" <?php checked($checked); ?><?php echo $disabled; ?>>
            <label <?php echo $disabledClass; ?>for="rrze-autoshare-twitter-enabled">
                <?php echo esc_html($label); ?>
            </label>
        </li>
<?php
        return ob_get_clean();
    }
}
