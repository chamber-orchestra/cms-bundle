<?php
declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\PropertyAccessor;

use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

readonly class ReflectionPropertyAccessor implements PropertyAccessorInterface
{
    public function __construct(private PropertyAccessorInterface $decorated)
    {
    }

    public function setValue(&$objectOrArray, $propertyPath, $value): void
    {
        try {
            $this->decorated->setValue($objectOrArray, $propertyPath, $value);
        } catch (NoSuchPropertyException $exception) {
            $propertyReflectionProperty = $this->getPropertyReflectionProperty($objectOrArray, $propertyPath);
            if (null === $propertyReflectionProperty) {
                throw $exception;
            }

            if ($propertyReflectionProperty->getDeclaringClass()->getName() !== \get_class($objectOrArray)) {
                $propertyReflectionProperty->setValue($objectOrArray, $value);

                return;
            }

            $setPropertyClosure = \Closure::bind(
                function ($object) use ($propertyPath, $value): void {
                    $object->{$propertyPath} = $value;
                },
                $objectOrArray,
                $objectOrArray
            );

            $setPropertyClosure($objectOrArray);
        }
    }

    public function getValue($objectOrArray, $propertyPath): mixed
    {
        try {
            return $this->decorated->getValue($objectOrArray, $propertyPath);
        } catch (NoSuchPropertyException $exception) {
            $propertyReflectionProperty = $this->getPropertyReflectionProperty($objectOrArray, $propertyPath);
            if (null === $propertyReflectionProperty) {
                throw $exception;
            }

            if ($propertyReflectionProperty->getDeclaringClass()->getName() !== \get_class($objectOrArray)) {
                return $propertyReflectionProperty->getValue($objectOrArray);
            }

            $getPropertyClosure = \Closure::bind(
                function ($object) use ($propertyPath) {
                    return $object->{$propertyPath};
                },
                $objectOrArray,
                $objectOrArray
            );

            return $getPropertyClosure($objectOrArray);
        }
    }

    public function isWritable($objectOrArray, $propertyPath): bool
    {
        return $this->decorated->isWritable($objectOrArray, $propertyPath) || $this->propertyExists($objectOrArray, $propertyPath);
    }

    public function isReadable($objectOrArray, $propertyPath): bool
    {
        return $this->decorated->isReadable($objectOrArray, $propertyPath) || $this->propertyExists($objectOrArray, $propertyPath);
    }

    private function propertyExists($objectOrArray, $propertyPath): bool
    {
        return null !== $this->getPropertyReflectionProperty($objectOrArray, $propertyPath);
    }

    private function getPropertyReflectionProperty($objectOrArray, $propertyPath)
    {
        if (!\is_object($objectOrArray)) {
            return null;
        }

        $reflectionClass = new \ReflectionClass($objectOrArray);
        while ($reflectionClass instanceof \ReflectionClass) {
            if ($reflectionClass->hasProperty($propertyPath)) {
                $property = $reflectionClass->getProperty($propertyPath);
                if (!$property->isStatic()) {
                    return $property;
                }
            }

            $reflectionClass = $reflectionClass->getParentClass();
        }

        return null;
    }
}
