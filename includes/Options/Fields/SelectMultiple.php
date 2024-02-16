<?php

namespace RRZE\Autoshare\Options\Fields;

defined('ABSPATH') || exit;

class SelectMultiple extends Field
{
    public $template = 'select-multiple';

    public function getNameAttribute()
    {
        $name = parent::getNameAttribute();

        return "{$name}[]";
    }

    public function getValueAttribute()
    {
        $value = get_option($this->section->tab->settings->optionName)[$this->getArg('name')] ?? false;
        if ($value === false) {
            $value = [$this->getArg('default')];
        }
        return $value;
    }

    public function sanitize($value)
    {
        return (array) $value;
    }
}
