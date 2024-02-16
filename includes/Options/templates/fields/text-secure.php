<?php

namespace RRZE\Autoshare\Options;

defined('ABSPATH') || exit;

$value = Utils::maskSecureValues($option->getValueAttribute());
?>
<tr valign="top">
    <th scope="row">
        <label for="<?php echo $option->getIdAttribute(); ?>"><?php echo $option->getLabel(); ?></label>
    </th>
    <td>
        <input name="<?php echo esc_attr($option->getNameAttribute()); ?>" id="<?php echo $option->getIdAttribute(); ?>" type="text" value="<?php echo $value; ?>" placeholder="<?php echo $option->getPlaceholderAttribute() ?: ''; ?>" class="regular-text">
        <?php if ($description = $option->getArg('description')) { ?>
            <p class="description"><?php echo $description; ?></p>
        <?php } ?>
        <?php if ($error = $option->hasError()) { ?>
            <div class="rrze-autoshare-error"><?php echo $error; ?></div>
        <?php } ?>
    </td>
</tr>