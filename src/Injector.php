<?php

namespace Creative\IoC;

use Creative\IoC\Container\ContainerInterface;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use RuntimeException;

class Injector
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    protected function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @param ContainerInterface $container
     * @return static
     */
    public static function getInstance(ContainerInterface $container) : self
    {
        static $instance = null;
        return $instance ?: $instance = new self($container);
    }

    /**
     * Валидирует тип сервиса
     * @param object|string|callable $object_class_callable
     * @return void
     */
    protected function validateService($object_class_callable, string $methodName = null) : void
    {
        if (
            !is_string($object_class_callable) &&
            !is_object($object_class_callable) &&
            !is_callable($object_class_callable)
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    'Arg "object_class_callable" must be one of types: 
                    string, 
                    object,
                    callable, %s has given', gettype($object_class_callable)
                ));
        }

        if (is_string($object_class_callable) && empty($object_class_callable)) {
            throw new InvalidArgumentException('"object_class_callable" argument cant be empty');
        }

        if (!is_callable($object_class_callable) && is_object($object_class_callable) && !$methodName) {
            throw new InvalidArgumentException('"methodName" cant be empty when "object_class_callable"');
        }
    }

    /**
     * @param array $args
     * @param ReflectionParameter ...$parameters
     * @return array
     * @throws ReflectionException
     */
    private function mapDependencies(array $args = [], ReflectionParameter ...$parameters) : array
    {
        $arDependencies = [];

        foreach ($parameters as $arg) {
            if ($arg->isDefaultValueAvailable()) {
                $arDependencies[] = $arg->getDefaultValue();
                continue;
            }

            if ($dependencyClass = $arg->getClass()) {
                $arDependencies[] = $this->resolveClassDependencies($dependencyClass->getName());
                continue;
            }

            // примитивные типы не имеющие дефолтных значений
            if ($value = $args[$arg->getName()]) {
                $arDependencies[] = $value;
            }
        }

        return $arDependencies;
    }

    /**
     * @param object $service
     * @param string $methodName
     * @param array $additionalArgs
     * @return mixed
     * @throws ReflectionException
     */
    private function resolveMethodDependencies(object $service, string $methodName, array $additionalArgs = [])
    {
        if (!method_exists($service, $methodName)) {
            throw new \InvalidArgumentException(sprintf('Service: %s dont have %s method', get_class($service), $methodName));
        }

        if (!$methodName) {
            throw new \InvalidArgumentException('Method name can\'t be empty');
        }

        $methodArgs = (new \ReflectionMethod($service, $methodName))->getParameters();
        return $service->$methodName(...$this->mapDependencies($additionalArgs, ...$methodArgs));
    }

    /**
     * @param callable $func
     * @return mixed
     * @throws ReflectionException
     */
    private function resolveCallableDependencies(callable $func)
    {
        return $func(...$this->mapDependencies([], ...(new \ReflectionFunction($func))->getParameters()));
    }

    /**
     * @param string $className
     * @return object
     * @throws ReflectionException
     * @return object
     */
    protected function resolveClassDependencies(string $className) : object
    {
        $reflection = new ReflectionClass($className);
        $arDependencies = [];

        if (!$reflection->isInstantiable() && !$reflection->hasMethod('getInstance')) {
            throw new RuntimeException('Can\'t instance object of service');
        }

        if ($constructor = $reflection->getConstructor()) {
            foreach ($constructor->getParameters() as $dependency) {
                /** Если у зависимости есть нет переопредённого значения, взять дефолтное */
                if (!$this->container->get($className)->getArgs()[$dependency->getName()] && $dependency->isDefaultValueAvailable()) {
                    $arDependencies[] = $dependency->getDefaultValue();
                    continue;
                }

                /** Если зависимость не класс, а примитивный тип */
                if (!$dependency->getClass()) {
                    if (!$primitiveTypeValue = $this->container->get($className)->getArgs()[$dependency->getName()]) {
                        throw new
                        RuntimeException(
                            sprintf('Can\'t resolve primitive dependencies, arg "%s" have no value "%s" class',
                                $dependency->getName(),
                                $reflection->getName()
                            )
                        );
                    }

                    $arDependencies[] = $primitiveTypeValue;
                    continue;
                }

                /*** Если зависимость есть класс, попытаться найти зависимости зависимостей */
                $arDependencies[] = $this->resolveClassDependencies($dependency->getClass()->getName());
            }
        }

        return new $className(...$arDependencies);
    }

    /**
     * @param callable|object|string $object_class_callable
     * @param string|null $methodName
     * @param array|null $args
     * @return mixed|void
     * @throws ReflectionException
     */
    public function invoke($object_class_callable, ?string $methodName = null, array $args = [])
    {
        $this->validateService($object_class_callable, $methodName);

        if (is_callable($object_class_callable)) {
            return $this->resolveCallableDependencies($object_class_callable);
        }

        if (is_string($object_class_callable) && !$methodName) {
            return $this->resolveClassDependencies($object_class_callable);
        }

        if (is_string($object_class_callable) && $methodName) {
            $obj = $this->resolveClassDependencies($object_class_callable);
            return $this->resolveMethodDependencies($obj, $methodName, $args);
        }

        if (is_object($object_class_callable)) {
            return $this->resolveMethodDependencies($object_class_callable, $methodName, $args);
        }
    }
}