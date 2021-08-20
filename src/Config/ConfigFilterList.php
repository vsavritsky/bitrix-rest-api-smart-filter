<?php

namespace BitrixRestApiSmartFilter\Config;

class ConfigFilterList extends ConfigFilterItem
{
    protected $values = [];

    public function setValues($values)
    {
        $this->values = $values;
    }

    public function getValues()
    {
        return $this->values;
    }
}