<?php declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\Processor\Reflection;

class RuntimeReflectionClass extends \ReflectionClass
{
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