<?php

namespace BitrixRestApiSmartFilter;

class ConfigFilterRange
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