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
    }

    public static function autoshareMetabox($postType, $post)
    {
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

    public static function renderSubmitbox($post)
    {
        echo '<ul>';
        echo self::blueskyMarkup($post);
        echo self::mastodonMarkup($post);
        // echo self::twitterMarkup($post);
        echo '</ul>';
    }

    private static function blueskyMarkup($post)
    {
        $inputName = 'rrze_autoshare_bluesky_enabled';
        $isEnabled = Bluesky::isConnected();
        $isSyndicated = Bluesky::isSyndicated($post->post_type, $post->ID);
        $disabled = !$isEnabled || $isSyndicated ? ' disabled' : '';
        $disabledClass = $disabled ? 'class = "rrze-autoshare-disabled_input__label" ' : '';
        $checked = !$disabled ? (bool) get_metadata($post->post_type, $post->ID, $inputName, true) : false;
        $label = !$disabled ? __('Share on Bluesky', 'rrze-autoshare') : __('Share on Bluesky is disabled', 'rrze-autoshare');
        $label = $isSyndicated ? __('Share on Bluesky is syndicated', 'rrze-autoshare') : $label;
        ob_start();
        ?>
        <li>
            <input type="checkbox" id="rrze-autoshare-bluesky-enabled" name="<?php echo esc_attr($inputName); ?>" value="1" <?php checked($checked); ?><?php echo $disabled; ?>>
            <label <?php echo $disabledClass; ?>for="rrze-autoshare-bluesky-enabled">
                <?php echo esc_html($label); ?>
            </label>
        </li>
        <?php
        return ob_get_clean();
    }

    private static function mastodonMarkup($post)
    {
        $inputName = 'rrze_autoshare_mastodon_enabled';
        $isEnabled = Mastodon::isConnected();
        $isSyndicated = Mastodon::isSyndicated($post->post_type, $post->ID);
        $disabled = !$isEnabled || $isSyndicated ? ' disabled' : '';
        $disabledClass = $disabled ? 'class = "rrze-autoshare-disabled_input__label" ' : '';
        $checked = !$disabled ? (bool) get_metadata($post->post_type, $post->ID, $inputName, true) : false;
        $label = !$disabled ? __('Share on Mastodon', 'rrze-autoshare') : __('Share on Mastodon is disabled', 'rrze-autoshare');
        $label = $isSyndicated ? __('Share on Mastodon is syndicated', 'rrze-autoshare') : $label;
        ob_start();
        ?>
        <li>
            <input type="checkbox" id="rrze-autoshare-mastodon-enabled" name="<?php echo esc_attr($inputName); ?>" value="1" <?php checked($checked); ?><?php echo $disabled; ?>>
            <label <?php echo $disabledClass; ?>for="rrze-autoshare-mastodon-enabled">
                <?php echo esc_html($label); ?>
            </label>
        </li>
        <?php
        return ob_get_clean();
    }

    private static function twitterMarkup($post)
    {
        $inputName = 'rrze_autoshare_twitter_enabled';
        $isEnabled = Twitter::isConnected();
        $isSyndicated = Twitter::isSyndicated($post->post_type, $post->ID);
        $disabled = !$isEnabled || $isSyndicated ? ' disabled' : '';
        $disabledClass = $disabled ? 'class = "rrze-autoshare-disabled_input__label" ' : '';
        $checked = !$disabled ? (bool) get_metadata($post->post_type, $post->ID, $inputName, true) : false;
        $label = !$disabled ? __('Share on Twitter', 'rrze-autoshare') : __('Share on Twitter is disabled', 'rrze-autoshare');
        $label = $isSyndicated ? __('Share on Twitter is syndicated', 'rrze-autoshare') : $label;
        ob_start();
        ?>
        <li>
            <input type="checkbox" id="rrze-autoshare-twitter-enabled" name="<?php echo esc_attr($inputName); ?>" value="1" <?php checked($checked); ?><?php echo $disabled; ?>>
            <label <?php echo $disabledClass; ?>for="rrze-autoshare-twitter-enabled">
                <?php echo esc_html($label); ?>
            </label>
        </li>
        <?php
        return ob_get_clean();
    }
}
