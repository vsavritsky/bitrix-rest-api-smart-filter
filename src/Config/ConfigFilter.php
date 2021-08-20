<?php

namespace BitrixRestApiSmartFilter\Config;

class ConfigFilter implements \JsonSerializable
{
    protected $config = [];

    public function addFilterItem(ConfigFilterItem $configFilterItem)
    {
        $this->config[] = $configFilterItem;
    }

    public function jsonSerialize()
    {
        $result = [];
        foreach ($this->config as $configFilterItem) {
            $result[$configFilterItem->getCode()] = $configFilterItem;
        }

        return $result;
    }
}