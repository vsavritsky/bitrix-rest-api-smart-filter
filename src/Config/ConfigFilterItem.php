<?php

namespace BitrixRestApiSmartFilter\Config;

abstract class ConfigFilterItem implements \JsonSerializable
{
    protected $name;
    protected $iblockId;
    protected $code;
    protected $displayType; 
    protected $propertyType;
    protected $hint;

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

    /**
     * @return mixed
     */
    public function getPropertyType()
    {
        return $this->propertyType;
    }

    /**
     * @param mixed $propertyType
     */
    public function setPropertyType($propertyType): void
    {
        $this->propertyType = $propertyType;
    }

    /**
     * @return mixed
     */
    public function getHint()
    {
        return $this->hint;
    }

    /**
     * @param mixed $hint
     */
    public function setHint($hint): void
    {
        $this->hint = $hint;
    }

    /**
     * @return mixed
     */
    public function getIblockId()
    {
        return $this->iblockId;
    }

    /**
     * @param mixed $iblockId
     */
    public function setIblockId($iblockId): void
    {
        $this->iblockId = $iblockId;
    }

    public abstract function getValues();

    public function jsonSerialize()
    {
        return [
            'code' => $this->getCode(),
            'iblockId' => $this->getIblockId(),
            'name' => $this->getName(),
            'hint' => $this->getHint(),
            'displayType' => $this->getDisplayType(),
            'propertyType' => $this->getPropertyType(),
            'values' => $this->getValues(),
        ];
    }
}
