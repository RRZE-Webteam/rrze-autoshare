<?php

namespace RRZE\Autoshare\Options;

defined('ABSPATH') || exit;
?>
<tr valign="top">
    <th scope="row">
        <label for="<?php echo $option->getIdAttribute(); ?>"><?php echo $option->getLabel(); ?></label>
    </th>
    <td>
        <label>
            <input name="<?php echo esc_attr($option->getNameAttribute()); ?>" id="<?php echo $option->getIdAttribute(); ?>" type="checkbox" value="1" <?php checked($option->isChecked()); ?>>
            <?php echo $option->getArg('description'); ?>
        </label>

        <input type="hidden" name="wp_settings_submitted[]" value="<?php echo esc_attr($option->getName()); ?>">

        <?php if ($error = $option->hasError()) { ?>
            <div class="rrze-autoshare-error"><?php echo $error; ?></div>
        <?php } ?>
    </td>
</tr>