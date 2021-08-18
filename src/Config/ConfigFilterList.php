<?php

namespace BitrixRestApiSmartFilter;

class ConfigFilterList
{
    protected $values = [];

    public function addList()
    {
        $this->config[$key] = $values;
    }

    public function addRange($key, $min, $max)
    {
        $this->config[$key] = [$min, $max];
    }
}