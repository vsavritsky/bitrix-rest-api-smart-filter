<?php

namespace BitrixRestApiSmartFilter\Config;

abstract class ConfigFilterItem implements \JsonSerializable
{
    protected $name;
    protected $code;
    protected $displayType;

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     */
    public function setName($name): void
    {
        $this->name = $name;
    }

    /**
     * @return mixed
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @param mixed $code
     */
    public function setCode($code): void
    {
        $this->code = $code;
    }

    /**
     * @return mixed
     */
    public function getDisplayType()
    {
        return $this->displayType;
    }

    /**
     * @param mixed $displayType
     */
    public function setDisplayType($displayType): void
    {
        $this->displayType = $displayType;
    }

    public abstract function getValues();

    public function jsonSerialize()
    {
        return [
            'code' => $this->getCode(),
            'name' => $this->getName(),
            'displayType' => $this->getDisplayType(),
            'values' => $this->getValues(),
        ];
    }
}