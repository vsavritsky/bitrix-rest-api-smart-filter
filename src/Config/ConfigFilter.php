<?php

namespace BitrixRestApiSmartFilter\Config;

class ConfigFilter implements \JsonSerializable
{
    protected $config = [];

    public function addFilterItem(ConfigFilterItem $configFilterItem)
    {
        $this->config[] = $configFilterItem;
    }

    public function getConfigFilterItemByCode($code): ?ConfigFilterItem
    {
        foreach ($this->config as $item) {
            if ($code == $item->getCode()) {
                return $item;
            }
        }

        return null;
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