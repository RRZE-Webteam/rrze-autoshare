<?php

namespace RRZE\Autoshare\Options\Fields;

defined('ABSPATH') || exit;

use RRZE\Autoshare\Options\Encryption;

class Password extends Field
{
    public $template = 'password';

    public function getValueAttribute()
    {
        $value = get_option($this->section->tab->settings->optionName)[$this->getArg('name')] ?? false;

        return $value ? Encryption::decrypt($value) : null;
    }

    public function sanitize($value)
    {
        return Encryption::encrypt($value);
    }
}