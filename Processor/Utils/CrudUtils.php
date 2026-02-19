<?php

declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\Processor\Utils;

use ChamberOrchestra\CmsBundle\Exception\CrudException;
use ChamberOrchestra\CmsBundle\Form\Dto\DtoCollection;
use ChamberOrchestra\CmsBundle\Form\Dto\DtoInterface;
use ChamberOrchestra\CmsBundle\Processor\Instantiator;
use ChamberOrchestra\CmsBundle\Processor\Reflector;
use Doctrine\Common\Util\ClassUtils;
use ChamberOrchestra\MetaBundle\Entity\MetaInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\String\UnicodeString;

readonly class CrudUtils
{
    private Reflector $reflector;

    public function __construct(private EntityManagerInterface $em, private Instantiator $instantiator)
    {
        $this->reflector = new Reflector();
    }

    /**
     * @throws \ReflectionException
     */
    public function sync(object $source, object $target): void
    {
        $properties = $this->getIntersectedProperties($source, $target);

        foreach ($properties as $name) {
            $sourceValue = $this->getPropertyValue($source, $name);
            $targetValue = $this->getPropertyValue($target, $name);

            if ($sourceValue === $targetValue) {
                continue;
            }

            // crud collections can be synced
            // todo move to separate method, sync collections
            if ($this->isCollectionProperty($source, $name) && $this->isCollectionProperty($target, $name)) {
                $targetClass = $this->getCollectionClass($target, $name);
                $collection = $this->createCollectionProperty($target, $name);
                $synced = new ArrayCollection();

                foreach ($sourceValue as $key => $sourceItem) {
                    $targetItem = $collection->get($key);
                    if (null === $targetItem) {
                        $targetItem = $this->createNewInstance($targetClass, $sourceItem, $target);
                        if ($collection instanceof PersistentCollection) {
                            // from dto to entity
                            $collection->hydrateSet($key, $targetItem);
                            $collection->setDirty(true);
                        } else {
                            // from entity to dto
                            $collection->set($key, $targetItem);
                        }
                    }

                    $this->sync($sourceItem, $targetItem);
                    $synced->add($targetItem);
                }

                foreach ($collection as $key => $item) {
                    if (!$synced->contains($item)) {
                        $collection->remove($key);
                    }
                }

                $this->setPropertyValue($target, $name, $collection);
                continue;
            }

            // other collections
            if ($sourceValue instanceof Collection || $targetValue instanceof Collection) {
                $targetCollection = $this->prepareCollection($targetValue);
                $sourceCollection = $this->prepareCollection($sourceValue);

                foreach ($targetCollection as $key => $value) {
                    if (!$sourceCollection->contains($value)) {
                        $targetCollection->remove($key);
                    }
                }

                foreach ($sourceValue as $key => $value) {
                    if (!$targetCollection->contains($value)) {
                        $targetCollection->set($key, $value);
                    }
                }

                $this->setPropertyValue($target, $name, $targetCollection);
                continue;
            }

            // set value
            if ((null !== $sourceValue && $this->isPropertyInstanceOf($source, $name, DtoInterface::class))
                || $this->isPropertyInstanceOf($target, $name, DtoInterface::class)) {
                if (null === $sourceValue) {
                    $this->setPropertyValue($target, $name, null);
                    continue;
                }

                if (null === $targetValue) {
                    $targetValue = $this->createNewInstanceOfProperty($target, $name, $sourceValue);
                }

                $this->sync($sourceValue, $targetValue);

                continue;
            }

            $this->setPropertyValue($target, $name, $sourceValue);
        }

        if ($target instanceof MetaInterface) {
            $refl = $this->reflector->create($target);
            $props = $refl->getAccessibleProperties();

            $getFirstNonEmptyValue = function (array $candidates) use ($target, $props) {
                foreach ($props as $prop) {
                    foreach ($candidates as $keyword) {
                        if (\str_contains(\mb_strtolower($prop->getName()), $keyword)) {
                            $value = $prop->getValue($target);
                            if (!empty($value)) {
                                return $value;
                            }
                        }
                    }
                }
                return null;
            };

            if (!$target->getTitle()) {
                $value = $getFirstNonEmptyValue(['name']);
                if ($value) {
                    $metaTitleProp = $refl->getAccessibleProperty('title');
                    $metaTitleProp->setValue($target, $value);
                }
            }

            if (!$target->getMetaTitle()) {
                $value = $getFirstNonEmptyValue(['title', 'name']);
                if ($value) {
                    $metaTitleProp = $refl->getAccessibleProperty('metaTitle');
                    $metaTitleProp->setValue($target, $value);
                }
            }

            if (!$target->getMetaDescription()) {
                $value = $getFirstNonEmptyValue(['description']);
                if ($value) {
                    $value = (string) (new UnicodeString(\strip_tags((string) $value)))->truncate(255);
                    $metaDescProp = $refl->getAccessibleProperty('metaDescription');
                    $metaDescProp->setValue($target, $value);
                }
            }

            if (!$target->getMetaImage()) {
                foreach ($props as $prop) {
                    if (!\str_contains(\mb_strtolower($prop->getName()), 'image')) {
                        continue;
                    }

                    if (!$prop->isInitialized($target)){
                        continue;
                    }

                    $image = $prop->getValue($target);
                    if ($image instanceof File) {
                        $newFile = $this->copyFile($image);
                        $metaImageProp = $refl->getAccessibleProperty('metaImage');
                        $metaImageProp->setValue($target, $newFile);
                        break;
                    }
                }
            }
        }
    }

    /**
     * @throws \ReflectionException
     */
    public function setPropertyValue(object $target, string $name, $value): void
    {
        $refl = $this->reflector->create($target);

        if ($refl->hasProperty($name)) {
            $property = $refl->getAccessibleProperty($name);
            $property->setValue($target, $value);
        }
    }

    /**
     * @return mixed|null
     *
     * @throws \ReflectionException
     */
    public function getPropertyValue(object $object, string $name)
    {
        $refl = $this->reflector->create($object);

        if ($refl->hasProperty($name)) {
            $property = $refl->getAccessibleProperty($name);

            return $property->isInitialized($object) ? $property->getValue($object) : null;
        }

        return null;
    }

    /**
     * @throws \ReflectionException
     */
    public function getPropertyClass(object $source, string $name): ?string
    {
        $refl = $this->reflector->create($source);

        if (!$refl->hasProperty($name)) {
            return null;
        }

        $property = $refl->getProperty($name);
        $type = $property->getType();

        if (null === $type || !$type instanceof \ReflectionNamedType) {
            return null;
        }

        $class = $type->getName();

        return \class_exists($class) ? $class : null;
    }

    private function prepareCollection(?iterable $value): Collection
    {
        return $value instanceof Collection ? clone $value : new ArrayCollection($value ?? []);
    }

    private function isCollectionProperty(object $object, string $field): bool
    {
        $value = $this->getPropertyValue($object, $field);
        if ($value instanceof DtoCollection) {
            return true;
        }

        if ($this->em->getMetadataFactory()->isTransient($class = ClassUtils::getClass($object))) {
            return false;
        }

        $meta = $this->em->getClassMetadata($class);

        return $meta->hasAssociation($field);
    }

    private function getCollectionClass(object $object, string $field): string
    {
        $value = $this->getPropertyValue($object, $field);
        if ($value instanceof DtoCollection) {
            return $value->getTypeClass();
        }

        $meta = $this->em->getClassMetadata(ClassUtils::getClass($object));

        return $meta->getAssociationTargetClass($field);
    }

    private function createCollectionProperty(object $object, string $field): Collection
    {
        $value = $this->getPropertyValue($object, $field);

        if (!\is_array($value) && !$value instanceof Collection) {
            throw CrudException::createNotValidType($object, $field, $value, ['array', Collection::class]);
        }

        if ($value instanceof DtoCollection || $value instanceof PersistentCollection) {
            return $value;
        }

        $meta = $this->em->getClassMetadata(ClassUtils::getClass($object));
        $assoc = $meta->getAssociationMapping($field);

        if (!$value instanceof Collection) {
            $value = new ArrayCollection($value);
        }

        $collection = new PersistentCollection(
            $this->em,
            $this->em->getClassMetadata($assoc['targetEntity']),
            $value
        );

        $collection->setOwner($object, $assoc);
        $collection->setDirty(!$collection->isEmpty());

        return $collection;
    }

    /**
     * @throws \ReflectionException
     */
    private function createNewInstance(string $classname, object $source, ?object $owner = null): object
    {
        $class = $this->reflector->create($source);
        $data = [
            '_source' => $source,
            'parent' => $owner,
            'typeClass' => $class->getName(),
        ];
        foreach ($class->getAccessibleProperties() as $property) {
            $data[$property->getName()] = $property->getValue($source);
        }

        return $this->instantiator->instantiate($classname, $data);
    }

    /**
     * @throws \ReflectionException
     */
    private function createNewInstanceOfProperty(object $object, string $name, object $data): object
    {
        // try to instantiate class if target value is not an object
        $class = $this->getPropertyClass($object, $name);
        if (null === $class) {
            throw new CrudException(\sprintf('To create new instance at property "%s" of class "%s", class type PHP7.4 must be specified.', $name, \get_class($object)));
        }

        $value = $this->createNewInstance($class, $data);
        $this->setPropertyValue($object, $name, $value);

        return $value;
    }

    private function isPropertyInstanceOf(object $object, string $name, string $class): bool
    {
        $propertyClass = $this->getPropertyClass($object, $name);

        return null !== $propertyClass && \in_array($class, \class_implements($propertyClass));
    }

    private function getIntersectedProperties(object $source, object $target): array
    {
        $targetRefl = $this->reflector->create($target);
        $sourceRefl = $this->reflector->create($source);

        $targetProperties = \array_map(
            fn(\ReflectionProperty $property): string => $property->getName(),
            $targetRefl->getAccessibleProperties()
        );
        $sourceProperties = \array_map(
            fn(\ReflectionProperty $property): string => $property->getName(),
            $sourceRefl->getAccessibleProperties()
        );

        return \array_intersect($sourceProperties, $targetProperties);
    }

    public function copyFile(File $file): File
    {
        $originalPath = $file->getRealPath() ?: $file->getPathname();

        if (!$originalPath || !\file_exists($originalPath)) {
            throw new \RuntimeException(\sprintf('Original file does not exist at: %s', $originalPath));
        }

        $pathInfo = \pathinfo($originalPath);
        $extension = $pathInfo['extension'] ?? 'bin';
        $basename = $pathInfo['filename'] ?? \uniqid('file_');
        $newFilename = $basename.'_copy_'.\uniqid().'.'.$extension;
        $newPath = $pathInfo['dirname'].\DIRECTORY_SEPARATOR.$newFilename;

        if (!\copy($originalPath, $newPath)) {
            throw new \RuntimeException(\sprintf('Failed to copy file to: %s', $newPath));
        }

        return new File($newPath);
    }
}
