<?php

namespace BitrixRestApiSmartFilter\Config;

class ConfigFilter implements \JsonSerializable
{
    protected $config = [];

    public function getConfig(): array
    {
        return $this->config;
    }

    public function addFilterItem(ConfigFilterItem $configFilterItem): void
    {
        $this->setFilterItem($configFilterItem);
    }

    public function setFilterItem(ConfigFilterItem $configFilterItem): void
    {
        $found = false;

        foreach ($this->config as $key => $item) {
            if ($configFilterItem->getCode() == $item->getCode()) {
                $found = true;
                $this->config[$key] = $configFilterItem;
            }
        }

        if (!$found) {
            $this->config[] = $configFilterItem;
        }
    }

    public function removeByCode(string $code): void
    {
        foreach ($this->config as $key => $item) {
            if ($code == $item->getCode()) {
                 unset($this->config[$key]);
            }
        }
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
