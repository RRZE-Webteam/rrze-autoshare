<?php

namespace RRZE\Autoshare\Options;

defined('ABSPATH') || exit;

use RRZE\Autoshare\Settings\Options\Type;

class ButtonLink extends Type
{
    public $template = 'button-link';

    public function render()
    {
        ?>
        <tr valign="top">
            <th scope="row" class="rrze-wp-form-label">
                <label for="<?php echo $this->getIdAttribute(); ?>" <?php echo $this->getLabelClassAttribute(); ?>><?php echo $this->getLabel(); ?></label>
            </th>
            <td>
                <input name="<?php echo esc_attr($this->getNameAttribute()); ?>" type="hidden" value="">
                <a href="<?php echo esc_url($this->getArg('href')); ?>" <?php echo $this->getInputClassAttribute(); ?>>
                    <?php echo esc_html($this->getArg('text')); ?>
                </a>
                <?php if ($description = $this->getArg('description')) { ?>
                    <p class="description"><?php echo $description; ?></p>
                <?php } ?>
                <?php if ($error = $this->hasError()) { ?>
                    <div class="rrze-autoshare-error"><?php echo $error; ?></div>
                <?php } ?>
            </td>
        </tr>
        <?php
    }
}
