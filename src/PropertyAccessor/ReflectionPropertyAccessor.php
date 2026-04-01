<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\PropertyAccessor;

use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyAccess\PropertyPathInterface;

class ReflectionPropertyAccessor implements PropertyAccessorInterface
{
    /** @var array<string, \ReflectionProperty|null> */
    private array $reflectionCache = [];

    public function __construct(private readonly PropertyAccessorInterface $decorated)
    {
    }

    /**
     * @param object|array<string, mixed> $objectOrArray
     *
     * @phpstan-ignore missingType.iterableValue
     */
    public function setValue(object|array &$objectOrArray, string|PropertyPathInterface $propertyPath, mixed $value): void
    {
        try {
            $this->decorated->setValue($objectOrArray, $propertyPath, $value); // @phpstan-ignore paramOut.type
        } catch (NoSuchPropertyException $exception) {
            if (!\is_object($objectOrArray)) {
                throw $exception;
            }

            $propertyPathStr = (string) $propertyPath;
            $propertyReflectionProperty = $this->getPropertyReflectionProperty($objectOrArray, $propertyPathStr);
            if (null === $propertyReflectionProperty) {
                throw $exception;
            }

            if ($propertyReflectionProperty->getDeclaringClass()->getName() !== $objectOrArray::class) {
                $propertyReflectionProperty->setValue($objectOrArray, $value);

                return;
            }

            /** @var \Closure $setPropertyClosure */
            $setPropertyClosure = \Closure::bind(
                static function (object $object) use ($propertyPathStr, $value): void {
                    $object->{$propertyPathStr} = $value;
                },
                $objectOrArray,
                $objectOrArray
            );

            $setPropertyClosure($objectOrArray);
        }
    }

    /**
     * @param object|array<string, mixed> $objectOrArray
     */
    public function getValue(object|array $objectOrArray, string|PropertyPathInterface $propertyPath): mixed
    {
        try {
            return $this->decorated->getValue($objectOrArray, $propertyPath);
        } catch (NoSuchPropertyException $exception) {
            if (!\is_object($objectOrArray)) {
                throw $exception;
            }

            $propertyPathStr = (string) $propertyPath;
            $propertyReflectionProperty = $this->getPropertyReflectionProperty($objectOrArray, $propertyPathStr);
            if (null === $propertyReflectionProperty) {
                throw $exception;
            }

            if ($propertyReflectionProperty->getDeclaringClass()->getName() !== $objectOrArray::class) {
                return $propertyReflectionProperty->getValue($objectOrArray);
            }

            /** @var \Closure $getPropertyClosure */
            $getPropertyClosure = \Closure::bind(
                static fn (object $object): mixed => $object->{$propertyPathStr},
                $objectOrArray,
                $objectOrArray
            );

            return $getPropertyClosure($objectOrArray);
        }
    }

    /**
     * @param object|array<string, mixed> $objectOrArray
     */
    public function isWritable(object|array $objectOrArray, string|PropertyPathInterface $propertyPath): bool
    {
        return $this->decorated->isWritable($objectOrArray, $propertyPath) || $this->propertyExists($objectOrArray, $propertyPath);
    }

    /**
     * @param object|array<string, mixed> $objectOrArray
     */
    public function isReadable(object|array $objectOrArray, string|PropertyPathInterface $propertyPath): bool
    {
        return $this->decorated->isReadable($objectOrArray, $propertyPath) || $this->propertyExists($objectOrArray, $propertyPath);
    }

    /**
     * @param object|array<string, mixed> $objectOrArray
     */
    private function propertyExists(object|array $objectOrArray, string|PropertyPathInterface $propertyPath): bool
    {
        return null !== $this->getPropertyReflectionProperty($objectOrArray, (string) $propertyPath);
    }

    /**
     * @param object|array<string, mixed> $objectOrArray
     */
    private function getPropertyReflectionProperty(object|array $objectOrArray, string $propertyPath): ?\ReflectionProperty
    {
        if (!\is_object($objectOrArray)) {
            return null;
        }

        $cacheKey = $objectOrArray::class.'::'.$propertyPath;
        if (\array_key_exists($cacheKey, $this->reflectionCache)) {
            return $this->reflectionCache[$cacheKey];
        }

        $reflectionClass = new \ReflectionClass($objectOrArray);
        while ($reflectionClass instanceof \ReflectionClass) {
            if ($reflectionClass->hasProperty($propertyPath)) {
                $property = $reflectionClass->getProperty($propertyPath);
                if (!$property->isStatic()) {
                    return $this->reflectionCache[$cacheKey] = $property;
                }
            }

            $reflectionClass = $reflectionClass->getParentClass();
        }

        return $this->reflectionCache[$cacheKey] = null;
    }
}
