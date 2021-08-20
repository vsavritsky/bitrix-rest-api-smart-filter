<?php

namespace BitrixRestApiSmartFilter\Config;

class ConfigFilterRange extends ConfigFilterItem
{
    protected $min = null;
    protected $max = null;

    /**
     * @return null
     */
    public function getMin()
    {
        return $this->min;
    }

    /**
     * @param null $min
     */
    public function setMin($min): void
    {
        $this->min = $min;
    }

    /**
     * @return null
     */
    public function getMax()
    {
        return $this->max;
    }

    /**
     * @param null $max
     */
    public function setMax($max): void
    {
        $this->max = $max;
    }

    public function getValues()
    {
        return [
            'min' => $this->min,
            'max' => $this->max
        ];
    }
}