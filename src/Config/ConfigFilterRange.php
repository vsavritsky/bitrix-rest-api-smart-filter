<?php

namespace BitrixRestApiSmartFilter\Config;

class ConfigFilterRange extends ConfigFilterItem
{
    protected $min = null;
    protected $max = null;

    protected $valueMin = null;
    protected $valueMax = null;

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

    public function getValueMin(): null
    {
        return $this->valueMin;
    }

    public function setValueMin($valueMin): void
    {
        $this->valueMin = (float)$valueMin;
    }

    public function getValueMax(): null
    {
        return $this->valueMax;
    }

    public function setValueMax($valueMax): void
    {
        $this->valueMax = (float)$valueMax;
    }

    public function getValues()
    {
        return [
            'min' => $this->min,
            'max' => $this->max,
            'valueMin' => $this->valueMin,
            'valueMax' => $this->valueMax
        ];
    }
}