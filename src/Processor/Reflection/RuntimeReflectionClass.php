<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Processor\Reflection;

/**
 * @template T of object
 *
 * @extends \ReflectionClass<T>
 */
class RuntimeReflectionClass extends \ReflectionClass
{
    /**
     * @return array<string, \ReflectionProperty>
     */
    public function getAccessibleProperties(): array
    {
        $properties = [];
        $class = $this;
        do {
            foreach ($class->getProperties() as $property) {
                if (!isset($properties[$name = $property->getName()])) {
                    $properties[$name] = $property;
                }
            }
        } while ($class = $class->getParentClass());

        return $properties;
    }

    public function getAccessibleProperty(string $name): \ReflectionProperty
    {
        return $this->getProperty($name);
    }
}
