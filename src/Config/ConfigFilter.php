<?php

namespace BitrixRestApiSmartFilter;

class ConfigFilter
{
    protected $config = [];

    public function addList()
    {
        $this->config[$key] = $values;
    }

    public function addRange($key, $min, $max)
    {
        $this->config[$key] = [$min, $max];
    }
}