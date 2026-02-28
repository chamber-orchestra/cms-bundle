<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Processor;

use Doctrine\Instantiator\Exception\InvalidArgumentException;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorBuilder;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class Instantiator
{
    /**
     * Used to instantiate specific classes, indexed by class name.
     *
     * @var array<string, \Closure(array<string, mixed>|object): object>
     */
    private static array $cachedInstantiators = [];

    public function __construct(private readonly ?CacheItemPoolInterface $cache = null)
    {
    }

    /**
     * @param array<string, mixed>|object $data
     */
    public function instantiate(string $className, array|object $data): object
    {
        if (isset(self::$cachedInstantiators[$className])) {
            $factory = self::$cachedInstantiators[$className];

            return $factory($data);
        }

        return $this->buildAndCacheFromFactory($className, $data);
    }

    /**
     * @param array<string, mixed>|object $data
     */
    private function buildAndCacheFromFactory(string $className, array|object $data): object
    {
        return (self::$cachedInstantiators[$className] = $this->buildFactory($className))($data);
    }

    /**
     * @return \Closure(array<string, mixed>|object): object
     *
     * @throws \ReflectionException
     */
    private function buildFactory(string $className): \Closure
    {
        $reflectionClass = $this->getReflectionClass($className);
        $accessor = $this->getAccessor();
        $constructor = $reflectionClass->getConstructor();
        $parameters = $constructor ? $constructor->getParameters() : [];

        return static function (array|object $data) use ($reflectionClass, $accessor, $parameters): object {
            $params = [];

            /** @var array<string, array<string, object>> $dataByType */
            $dataByType = [];
            if (\is_array($data)) {
                foreach ($data as $key => $value) {
                    if (\is_object($value)) {
                        $class = $value::class;
                        do {
                            $dataByType[$class][(string) $key] = $value;
                        } while ($class = \get_parent_class($class));
                    }
                }
            } else {
                /** @var object $data */
                $refl = new \ReflectionClass($data);
                foreach ($refl->getProperties() as $prop) {
                    if ($prop->isInitialized($data)) {
                        $value = $prop->getValue($data);
                        if (\is_object($value)) {
                            $class = $value::class;
                            do {
                                $dataByType[$class][$prop->getName()] = $value;
                            } while ($class = \get_parent_class($class));
                        }
                    }
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

                    $type = $parameter->getType();
                    if ($type instanceof \ReflectionNamedType) {
                        $typeName = $type->getName();
                        if (isset($dataByType[$typeName]) && 1 === \count($dataByType[$typeName])) {
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

    private ?PropertyAccessorInterface $propertyAccessor = null;

    private function getAccessor(): PropertyAccessorInterface
    {
        if (null !== $this->propertyAccessor) {
            return $this->propertyAccessor;
        }

        $builder = new PropertyAccessorBuilder();
        $builder
            ->setCacheItemPool($this->cache)
            ->enableExceptionOnInvalidIndex();

        return $this->propertyAccessor = $builder->getPropertyAccessor();
    }

    /**
     * @return \ReflectionClass<object>
     *
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
