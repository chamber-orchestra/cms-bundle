<?php

declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\Processor;

use Doctrine\Instantiator\Exception\InvalidArgumentException;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyAccess\PropertyAccessorBuilder;

class Instantiator
{
    /**
     * Used to instantiate specific classes, indexed by class name.
     *
     * @var callable[]
     */
    private static array $cachedInstantiators = [];

    public function __construct(private readonly ?CacheItemPoolInterface $cache = null)
    {
    }

    public function instantiate(string $className, $data): object
    {
        if (isset(self::$cachedInstantiators[$className])) {
            $factory = self::$cachedInstantiators[$className];

            return $factory($data);
        }

        return $this->buildAndCacheFromFactory($className, $data);
    }

    private function buildAndCacheFromFactory(string $className, $data): object
    {
        return (self::$cachedInstantiators[$className] = $this->buildFactory($className))($data);
    }

    /**
     * @throws \ReflectionException
     */
    private function buildFactory(string $className): callable
    {
        $reflectionClass = $this->getReflectionClass($className);
        $accessor = $this->getAccessor();
        $constructor = $reflectionClass->getConstructor();
        $parameters = $constructor ? $constructor->getParameters() : [];

        return static function ($data) use ($reflectionClass, $accessor, $parameters) {
            $params = [];

            $dataByType = [];
            foreach ($data as $key => $value) {
                if (\is_object($value)) {
                    $dataByType[\get_class($value)][$key] = $value;
                }
            }

            foreach ($parameters as $parameter) {
                $name = $parameter->getName();
                $path = \is_object($data) ? $name : '['.$name.']';
                // accessor.isReadable() for array always return true
                if (!$accessor->isReadable($data, $path)) {
                    if ($parameter->isOptional()) {
                        $params[$name] = $parameter->getDefaultValue();
                        continue;
                    }

                    if ($parameter->allowsNull()) {
                        $params[$name] = null;
                        continue;
                    }

                    if ($parameter->getType()) {
                        $typeName = $parameter->getType()->getName();
                        if (isset($dataByType[$typeName]) && \count($dataByType[$typeName]) === 1) {
                            $params[$name] = \current($dataByType[$typeName]);
                            continue;
                        }
                    }

                    throw new \RuntimeException(\sprintf('To create new instance of "%s" from data, source property "%s" must exist.', $reflectionClass->getName(), $name));
                }

                $params[$name] = $accessor->getValue($data, $path);
            }

            return $reflectionClass->newInstanceArgs($params);
        };
    }

    private function getAccessor(): PropertyAccessor
    {
        static $propertyAccessor;
        if (null !== $propertyAccessor) {
            return $propertyAccessor;
        }

        $builder = new PropertyAccessorBuilder;
        $builder
            ->setCacheItemPool($this->cache)
            ->enableExceptionOnInvalidIndex();

        return $propertyAccessor = $builder->getPropertyAccessor();
    }

    /**
     * @throws \ReflectionException
     */
    private function getReflectionClass(string $className): \ReflectionClass
    {
        if (!\class_exists($className)) {
            throw InvalidArgumentException::fromNonExistingClass($className);
        }

        $reflection = new \ReflectionClass($className);

        if ($reflection->isAbstract()) {
            throw InvalidArgumentException::fromAbstractClass($reflection);
        }

        return $reflection;
    }
}
