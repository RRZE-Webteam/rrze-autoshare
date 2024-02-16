<?php

namespace RRZE\Autoshare\Options\Fields;

defined('ABSPATH') || exit;

class Checkbox extends Field
{
    public $template = 'checkbox';

    public function getValueAttribute()
    {
        $value = get_option($this->section->tab->settings->optionName)[$this->getArg('name')] ?? false;
        if ($value === false) {
            $value = $this->getArg('default');
        }
        return $value;
    }

    public function isChecked()
    {
        return (bool) $this->getValueAttribute();
    }
}
