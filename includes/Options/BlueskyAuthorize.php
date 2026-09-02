<?php

namespace RRZE\Autoshare\Options;

defined('ABSPATH') || exit;

use RRZE\Autoshare\Settings\Options\Type;
use RRZE\Autoshare\Services\Bluesky\API;
use function RRZE\Autoshare\config;

class BlueskyAuthorize extends Type {
    public function render() {
        $authorization = config()->get('services.bluesky.authorization');
        ?>
        <tr valign="top">
            <th scope="row" class="rrze-wp-form-label">
                <span><?php echo esc_html($this->getLabel()); ?></span>
            </th>
            <td class="rrze-wp-form rrze-wp-form-input">
                <?php if (API::isConnected()) { ?>
                    <a href="<?php echo esc_url(API::authorizeAccessUrl()); ?>" class="button button-secondary">
                        <?php echo esc_html(API::authorizeAccessText()); ?>
                    </a>
                    <p class="description"><?php echo esc_html(API::authorizeAccessDescription()); ?></p>
                <?php } else { ?>
                    <p>
                        <label for="rrze-autoshare-bluesky-identifier">
                            <?php esc_html_e('Username or email address', 'rrze-autoshare'); ?>
                        </label><br>
                        <input name="<?php echo esc_attr($authorization['identifier_field']); ?>" id="rrze-autoshare-bluesky-identifier" type="text" class="regular-text" autocomplete="username" required>
                    </p>
                    <p>
                        <label for="rrze-autoshare-bluesky-app-password">
                            <?php esc_html_e('Bluesky App Password', 'rrze-autoshare'); ?>
                        </label><br>
                        <input name="<?php echo esc_attr($authorization['password_field']); ?>" id="rrze-autoshare-bluesky-app-password" type="password" class="regular-text" autocomplete="current-password" required>
                    </p>
                    <p class="description">
                        <?php
                        echo wp_kses(
                            sprintf(
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
                    <p>
                        <button type="submit" class="button button-secondary" name="<?php echo esc_attr($authorization['action_field']); ?>" value="1">
                            <?php esc_html_e('Authorize Access', 'rrze-autoshare'); ?>
                        </button>
                    </p>
                <?php } ?>

                <?php $this->renderNotice(); ?>
            </td>
        </tr>
        <?php
    }

    private function renderNotice() {
        $notice = filter_input(INPUT_GET, config()->get('services.bluesky.authorization.notice_field'), FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        if ('success' === $notice) {
            printf('<p class="notice notice-success inline"><span>%s</span></p>', esc_html__('Bluesky access was authorized.', 'rrze-autoshare'));
        } elseif ('failed' === $notice) {
            printf('<p class="notice notice-error inline"><span>%s</span></p>', esc_html__('Bluesky access could not be authorized. Check the account identifier and App Password.', 'rrze-autoshare'));
        }
    }
}
