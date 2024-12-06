<?php

namespace RRZE\Autoshare\Options;

defined('ABSPATH') || exit;

use RRZE\Autoshare\Settings\Encryption;
use RRZE\Autoshare\Settings\Options\Type;

class TextSecure extends Type
{
    public $template = 'text-secure';

    public function getValueAttribute()
    {
        $value = get_option($this->section->tab->settings->optionName)[$this->getArg('name')] ?? false;

        return $value ? Encryption::decrypt($value) : null;
    }

    public function sanitize($value)
    {
        if (false !== mb_stripos($value, '•••')) {
            $value = $this->getValueAttribute();
        }
        $value = sanitize_text_field($value);
        return Encryption::encrypt($value);
    }

    public function render()
    {
        $value = $this->maskSecureValues($this->getValueAttribute());
?>
        <tr valign="top">
            <th scope="row" class="rrze-wp-form-label">
                <label for="<?php echo $this->getIdAttribute(); ?>" <?php echo $this->getLabelClassAttribute(); ?>><?php echo $this->getLabel(); ?></label>
            </th>
            <td class="rrze-wp-form rrze-wp-form-input">
                <input name="<?php echo esc_attr($this->getNameAttribute()); ?>" id="<?php echo $this->getIdAttribute(); ?>" type="text" value="<?php echo $value; ?>" placeholder="<?php echo $this->getPlaceholderAttribute(); ?>" <?php echo $this->getInputClassAttribute(); ?>>
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

    /**
     * Mask secure values.
     *
     * @param string $value Original value.
     * @param string $hint  Number of characters to show.
     * 
     * @return string
     */
    public function maskSecureValues($value, $hint = 6)
    {
        $length = mb_strlen($value);
        if ($length > 0 && $length <= $hint) {
            $value = $this->mbStrPad($value, $length, '•', STR_PAD_LEFT);
        } elseif ($length > $hint) {
            $substr = substr($value, -$hint);
            $value = $this->mbStrPad($substr, $length, '•', STR_PAD_LEFT);
        }
        return $value;
    }

    /**
     * Multibyte String Pad
     *
     * Replaces the mb_str_pad() function that is included in PHP 8 >= PHP 8.3.0
     *
     * @param string $input The string to be padded.
     * @param int $length The length of the resultant padded string.
     * @param string $padding The string to use as padding. Defaults to space.
     * @param int $padType The type of padding. Defaults to STR_PAD_RIGHT.
     * @param string $encoding The encoding to use, defaults to UTF-8.
     *
     * @return string A padded multibyte string.
     */
    public function mbStrPad($input, $length, $padding = ' ', $padType = STR_PAD_RIGHT, $encoding = 'UTF-8')
    {
        $result = $input;
        if (($paddingRequired = $length - mb_strlen($input, $encoding)) > 0) {
            switch ($padType) {
                case STR_PAD_LEFT:
                    $result =
                        mb_substr(str_repeat($padding, $paddingRequired), 0, $paddingRequired, $encoding) .
                        $input;
                    break;
                case STR_PAD_RIGHT:
                    $result =
                        $input .
                        mb_substr(str_repeat($padding, $paddingRequired), 0, $paddingRequired, $encoding);
                    break;
                case STR_PAD_BOTH:
                    $leftPaddingLength = floor($paddingRequired / 2);
                    $rightPaddingLength = $paddingRequired - $leftPaddingLength;
                    $result =
                        mb_substr(str_repeat($padding, $leftPaddingLength), 0, $leftPaddingLength, $encoding) .
                        $input .
                        mb_substr(str_repeat($padding, $rightPaddingLength), 0, $rightPaddingLength, $encoding);
                    break;
            }
        }

        return $result;
    }
}
