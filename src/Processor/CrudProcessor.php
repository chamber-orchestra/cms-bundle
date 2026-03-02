<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Processor;

use ChamberOrchestra\CmsBundle\Events\CreateEvent;
use ChamberOrchestra\CmsBundle\Events\DeleteEvent;
use ChamberOrchestra\CmsBundle\Events\PostSyncEvent;
use ChamberOrchestra\CmsBundle\Events\SyncEvent;
use ChamberOrchestra\CmsBundle\Events\UpdateEvent;
use ChamberOrchestra\CmsBundle\Form\Dto\BulkOperationDto;
use ChamberOrchestra\CmsBundle\Form\Dto\DeleteDto;
use ChamberOrchestra\CmsBundle\Form\Dto\DtoInterface;
use ChamberOrchestra\CmsBundle\Processor\Utils\CrudUtils;
use ChamberOrchestra\DoctrineExtensionsBundle\Contracts\Entity\SoftDeleteInterface;
use ChamberOrchestra\DoctrineExtensionsBundle\Contracts\Entity\ToggleInterface;
use ChamberOrchestra\DoctrineExtensionsBundle\Repository\EntityRepository;
use ChamberOrchestra\DoctrineSortBundle\Contracts\Entity\SortInterface;
use ChamberOrchestra\DoctrineSortBundle\Mapping\Configuration\SortConfiguration;
use ChamberOrchestra\FileBundle\Mapping\Attribute\UploadableProperty;
use ChamberOrchestra\MetadataBundle\EventSubscriber\MetadataConfigurationTrait;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\OneToOne;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class CrudProcessor implements CrudProcessorInterface
{
    use MetadataConfigurationTrait;
    use SortProcessorTrait;

    public function __construct(
        protected EntityManagerInterface $em,
        protected EventDispatcherInterface $dispatcher,
        protected CrudUtils $utils,
        protected Instantiator $instantiator
    ) {
    }

    public function create(DtoInterface $dto): object
    {
        $meta = $this->em->getClassMetadata($dto->getEntityClass());
        $this->em->beginTransaction();
        try {
            $entity = $this->newInstance($dto, $dto->getEntityClass());
            $this->dispatcher->dispatch(new CreateEvent($meta, $entity));
            $this->sync($dto, $entity);
            $this->dispatcher->dispatch(new SyncEvent($meta, $entity, $dto));
            $this->dispatcher->dispatch(new PostSyncEvent($meta, $entity));
            $this->em->persist($entity);
            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }

        return $entity;
    }

    public function update(DtoInterface $dto, object $entity): void
    {
        $meta = $this->em->getClassMetadata($dto->getEntityClass());
        $this->em->beginTransaction();
        try {
            $this->dispatcher->dispatch(new UpdateEvent($meta, $entity));
            $this->sync($dto, $entity);
            $this->dispatcher->dispatch(new SyncEvent($meta, $entity, $dto));
            $this->dispatcher->dispatch(new PostSyncEvent($meta, $entity));
            $this->em->persist($entity);
            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }
    }

    public function updateEmbeddable(DtoInterface $dto, object $entity, object $embeddable): void
    {
        $meta = $this->em->getClassMetadata(ClassUtils::getClass($entity));
        $this->em->beginTransaction();
        try {
            $this->dispatcher->dispatch(new UpdateEvent($meta, $entity));
            $this->sync($dto, $embeddable);
            $this->dispatcher->dispatch(new SyncEvent($meta, $embeddable, $dto));
            $this->dispatcher->dispatch(new PostSyncEvent($meta, $entity));
            $this->em->persist($entity);
            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }
    }

    public function delete(DtoInterface $dto, object $entity): void
    {
        $meta = $this->em->getClassMetadata($dto->getEntityClass());
        $this->em->beginTransaction();
        try {
            $this->deleteEntity($meta, $dto, $entity);
            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }
    }

    /**
     * @param class-string $class
     */
    public function bulkDelete(BulkOperationDto $dto, string $class): void
    {
        /** @var EntityRepository $er */
        $er = $this->em->getRepository($class);
        $meta = $this->em->getClassMetadata($class);
        $this->em->beginTransaction();
        try {
            $ids = $dto->all ? $er->indexBy() : \explode(',', (string) $dto->entities);
            $entities = $er->findBy(['id' => $ids]);
            foreach ($entities as $entity) {
                $delete = new DeleteDto($class);
                $delete->entity = $entity;
                $this->deleteEntity($meta, $delete, $entity);
            }
            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }
    }

    /**
     * @param ClassMetadata<object> $meta
     */
    private function deleteEntity(ClassMetadata $meta, DtoInterface $dto, object $entity): void
    {
        if ($entity instanceof SoftDeleteInterface) {
            $this->softDelete($meta, $entity);
        } else {
            $this->dispatcher->dispatch(new DeleteEvent($meta, $entity));
            // nothing to sync here, can be synced in listeners
            $this->dispatcher->dispatch(new SyncEvent($meta, $entity, $dto));
            $this->dispatcher->dispatch(new PostSyncEvent($meta, $entity));
            $this->em->remove($entity);
        }
    }

    public function toggle(ToggleInterface $entity): void
    {
        $this->em->beginTransaction();
        try {
            $entity->toggle();
            $this->em->persist($entity);
            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }
    }

    public function sync(object $source, object $target): void
    {
        $this->utils->sync($source, $target);
    }

    /**
     * @return ClassMetadata<object>
     */
    public function getClassMetadata(object $entity): ClassMetadata
    {
        return $this->em->getClassMetadata(ClassUtils::getClass($entity));
    }

    protected function getSnapshot(object $entity): object
    {
        return clone $entity;
    }

    protected function newInstance(DtoInterface $dto, string $classname): object
    {
        return $this->instantiator->instantiate($classname, $dto);
    }

    /**
     * @param ClassMetadata<object> $meta
     */
    private function softDelete(ClassMetadata $meta, SoftDeleteInterface $entity): void
    {
        $entity->delete();
        if ($entity instanceof SortInterface) {
            $config = $this->getConfiguration($this->em, $entity, SortConfiguration::class);
            if ($config instanceof SortConfiguration) {
                /** @var class-string $entityName */
                $entityName = $config->getEntityName();
                $er = $this->em->getRepository($entityName);
                $qb = $er->createQueryBuilder('n');
                $field = $config->getSortField();
                $qb->select(\sprintf('MAX(n.%s) + 1', $field));
                $order = (int) $qb->getQuery()->getSingleScalarResult();
                $meta->setFieldValue($entity, $field, $order);
            }
        }

        $this->em->persist($entity);
    }

    public function copy(object $entity): void
    {
        $this->em->beginTransaction();
        try {
            $copied = $this->copyEntity($entity);
            $this->em->persist($copied);
            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }
    }

    public function copyEntity(object $entity): object
    {
        $cloned = clone $entity;

        // Traverse the full class hierarchy to catch private properties from parents
        $refClass = new \ReflectionClass($cloned);
        $properties = [];
        $class = $refClass;
        do {
            foreach ($class->getProperties() as $property) {
                $properties[$property->getName()] ??= $property;
            }
        } while ($class = $class->getParentClass());

        foreach ($properties as $property) {
            $property->setAccessible(true);

            $name = $property->getName();
            if ('id' === $name || 'slug' === $name) {
                $property->setValue($cloned, null);
                continue;
            }

            if ('enabled' === $name) {
                $property->setValue($cloned, false);
                continue;
            }

            if ($this->hasOneToOneAttribute($property)) {
                $property->setValue($cloned, null);
                continue;
            }

            foreach ($property->getAttributes(UploadableProperty::class) as $attr) {
                /** @var UploadableProperty $uploadableAttr */
                $uploadableAttr = $attr->newInstance();
                $mappedBy = $uploadableAttr->mappedBy;
                if (!$refClass->hasProperty($mappedBy)) {
                    continue;
                }
                $mappedProp = $refClass->getProperty($mappedBy);
                $mappedProp->setAccessible(true);
                /** @var File|null $oldFile */
                $oldFile = $property->isInitialized($cloned) ? $property->getValue($cloned) : null;
                if (!$oldFile) {
                    continue;
                }

                $newFile = $this->utils->copyFile($oldFile);
                $property->setValue($cloned, $newFile);
                $mappedProp->setValue($cloned, null);
            }
        }

        return $cloned;
    }

    private function hasOneToOneAttribute(\ReflectionProperty $property): bool
    {
        foreach ($property->getAttributes() as $attr) {
            if (OneToOne::class === $attr->getName()) {
                return true;
            }
        }

        return false;
    }
}
