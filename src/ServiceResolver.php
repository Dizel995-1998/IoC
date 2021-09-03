<?php

namespace Creative\IoC;

class ServiceResolver
{
    /**
     * @var string
     */
    protected $specificService;

    /**
     * @var array
     */
    protected $args = [];

    /**
     * @param string $specificService
     * @param array $args
     */
    public function __construct(string $specificService, array $args = [])
    {
        $this->specificService = $specificService;
        $this->args = $args;
    }

    /**
     * Возвращает название сервиса
     * @return string
     */
    public function getSpecificService() : string
    {
        return $this->specificService;
    }

    /**
     * Возвращает ассоциативный массив примитивных типов
     * @return array
     */
    public function getArgs() : array
    {
        return $this->args;
    }
}