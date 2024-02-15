<?php

namespace RRZE\Autoshare\Options;

defined('ABSPATH') || exit;

class Utils
{
    /**
     * Mask secure values.
     *
     * @param string $value Original value.
     * @param string $hint  Number of characters to show.
     * 
     * @return string
     */
    public static function maskSecureValues($value, $hint = 6)
    {
        $length = mb_strlen($value);
        if ($length > 0 && $length <= $hint) {
            $value = self::mbStrPad($value, $length, '•', STR_PAD_LEFT);
        } elseif ($length > $hint) {
            $substr = substr($value, -$hint);
            $value = self::mbStrPad($substr, $length, '•', STR_PAD_LEFT);
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
    public static function mbStrPad($input, $length, $padding = ' ', $padType = STR_PAD_RIGHT, $encoding = 'UTF-8')
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
