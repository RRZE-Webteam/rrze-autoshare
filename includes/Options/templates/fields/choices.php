<?php

namespace RRZE\Autoshare\Options;

defined('ABSPATH') || exit;
?>
<tr valign="top">
    <th scope="row">
        <label for="<?php echo $option->getIdAttribute(); ?>"><?php echo $option->getLabel(); ?></label>
    </th>
    <td>
        <?php foreach ($option->getArg('options', []) as $key => $label) { ?>
            <div>
                <label>
                    <input name="<?php echo esc_attr($option->getNameAttribute()); ?>" id="<?php echo $option->getIdAttribute(); ?>" type="radio" value="<?php echo $key; ?>" <?php checked($key, $option->getValueAttribute()); ?>>
                    <?php echo $label; ?>
                </label>
            </div>
        <?php } ?>
        <?php if ($description = $option->getArg('description')) { ?>
            <p class="description"><?php echo $description; ?></p>
        <?php } ?>
        <?php if ($error = $option->hasError()) { ?>
            <div class="rrze-autoshare-error"><?php echo $error; ?></div>
        <?php } ?>
    </td>
</tr>