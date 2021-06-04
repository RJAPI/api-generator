<?php
namespace SoliDry\Helpers;


use SoliDry\Types\PhpInterface;

/**
 * Class MethodOptions
 * @package SoliDry\Helpers
 */
class MethodOptions
{
    /**
     * @var string
     */
    private string $modifier = PhpInterface::PHP_MODIFIER_PUBLIC;

    /**
     * @var string
     */
    private string $name = '';

    /**
     * @var string
     */
    private string $returnType = '';

    /**
     * @var bool
     */
    private bool $isStatic = false;

    /**
     * @var array
     */
    private array $params = [];

    /**
     * @return string
     */
    public function getModifier() : string
    {
        return $this->modifier;
    }

    /**
     * @param string $modifier
     */
    public function setModifier($modifier) : void
    {
        $this->modifier = $modifier;
    }

    /**
     * @return string
     */
    public function getName() : string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name) : void
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getReturnType() : string
    {
        return $this->returnType;
    }

    /**
     * @param string $returnType
     */
    public function setReturnType($returnType) : void
    {
        $this->returnType = $returnType;
    }

    /**
     * @return boolean
     */
    public function isStatic() : bool
    {
        return $this->isStatic;
    }

    /**
     * @param boolean $isStatic
     */
    public function setIsStatic($isStatic) : void
    {
        $this->isStatic = $isStatic;
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * @param array $params
     */
    public function setParams($params)
    {
        $this->params = $params;
    }
}