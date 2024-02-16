<?php

namespace RRZE\Autoshare\Options;

defined('ABSPATH') || exit;
?>
<tr valign="top">
    <th scope="row">
        <label for="<?php echo $option->getIdAttribute(); ?>"><?php echo $option->getLabel(); ?></label>
    </th>
    <td>
        <select id="<?php echo $option->getIdAttribute(); ?>" name="<?php echo esc_attr($option->getNameAttribute()); ?>" multiple>
            <?php foreach ($option->getArg('options', []) as $key => $label) { ?>
                <option value="<?php echo $key; ?>" <?php echo in_array($key, $option->getValueAttribute() ?? []) ? 'selected' : null; ?>><?php echo $label; ?></option>
            <?php } ?>
        </select>
        <?php if ($description = $option->getArg('description')) { ?>
            <p class="description"><?php echo $description; ?></p>
        <?php } ?>

        <?php if ($error = $option->hasError()) { ?>
            <div class="rrze-autoshare-error"><?php echo $error; ?></div>
        <?php } ?>
    </td>
</tr>