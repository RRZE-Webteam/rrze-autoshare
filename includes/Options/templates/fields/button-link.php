<?php

namespace RRZE\Autoshare\Options;

defined('ABSPATH') || exit;
?>
<tr valign="top">
    <th scope="row">
        <label for="<?php echo $option->getIdAttribute(); ?>"><?php echo $option->getLabel(); ?></label>
    </th>
    <td>
        <input name="<?php echo esc_attr($option->getNameAttribute()); ?>" type="hidden" value="">
        <a href="<?php echo esc_url($option->getArg('href')); ?>" class="button button-secondary">
            <?php echo esc_html($option->getArg('text')); ?>
        </a>
        <?php if ($description = $option->getArg('description')) { ?>
            <p class="description"><?php echo $description; ?></p>
        <?php } ?>
        <?php if ($error = $option->hasError()) { ?>
            <div class="rrze-autoshare-error"><?php echo $error; ?></div>
        <?php } ?>
    </td>
</tr>