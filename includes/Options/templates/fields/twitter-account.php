<?php

namespace RRZE\Autoshare\Options;

defined('ABSPATH') || exit;
$connectUrl = wp_nonce_url(
    admin_url('admin-post.php?action=rrze_autoshare_twitter_authorize_action'),
    'rrze_autoshare_twitter_authorize_action',
    'rrze_autoshare_twitter_authorize_nonce'
);
?>
<tr valign="top">
    <th scope="row" class="rrze-wp-form-label">
        <label for="<?php echo $option->getIdAttribute(); ?>" class="<?php echo $option->getLabelClassAttribute(); ?>"><?php echo $option->getLabel(); ?></label>
    </th>
    <td class="rrze-wp-form rrze-wp-form-input">
        <a href="<?php echo esc_url($connectUrl); ?>" class="button button-secondary">
            <?php echo (!empty($option->getValueAttribute()) ? $option->getValueAttribute() : __('Connect Twitter account', 'rrze-autoshare')); ?>
        </a>
        <?php if ($error = $option->hasError()) { ?>
            <div class="rrze-autoshare-error"><?php echo $error; ?></div>
        <?php } ?>
    </td>
</tr>