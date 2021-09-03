<?php

namespace Creative\IoC\Container;

use Creative\IoC\ServiceResolver;
use InvalidArgumentException;
use RuntimeException;

class Container implements ContainerInterface
{
    /**
     * @var array
     */
    protected $services = [];

    /**
     * @param string $specificService
     * @param array $args
     * @param string|null $interfaceService
     */
    public function set(string $specificService, array $args = [], string $interfaceService = null)
    {
        if (empty($specificService)) {
            throw new InvalidArgumentException('SpecificService cant be empty');
        }

        if (isset($this->services[$specificService])) {
            throw new RuntimeException(sprintf('Trying rewrite service %s', $specificService));
        }

        $this->services[$interfaceService ?: $specificService] = new ServiceResolver($specificService, $args);
    }

    /**
     * @param string $id
     * @return ServiceResolver|null
     */
    public function get(string $id) : ?ServiceResolver
    {
        return $this->services[$id];
    }

    /**
     * @param string $id
     * @return bool
     */
    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }
}