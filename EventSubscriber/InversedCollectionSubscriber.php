<?php

declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\EventSubscriber;

use ChamberOrchestra\CmsBundle\Events\CreateEvent;
use ChamberOrchestra\CmsBundle\Events\DeleteEvent;
use ChamberOrchestra\CmsBundle\Events\PostSyncEvent;
use ChamberOrchestra\CmsBundle\Events\SyncEvent;
use ChamberOrchestra\CmsBundle\Events\UpdateEvent;
use ChamberOrchestra\CmsBundle\Exception\LogicException;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\PersistentCollection;
use Doctrine\Persistence\Proxy;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class InversedCollectionSubscriber implements EventSubscriberInterface
{
    private array $pendingInsertions = [];
    private array $pendingDeletions = [];
    private array $pendingUpdates = [];

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CreateEvent::class => 'onCreate',
            DeleteEvent::class => 'onDelete',
            UpdateEvent::class => 'onUpdate',
            SyncEvent::class => 'onSync',
            PostSyncEvent::class => 'onPostSync',
        ];
    }

    public function onCreate(CreateEvent $event): void
    {
        $this->pendingInsertions[\spl_object_hash($event->getEntity())] = $this->snapshot(
            $event->getClassMetadata(),
            null
        );
    }

    public function onDelete(DeleteEvent $event): void
    {
        $this->pendingDeletions[\spl_object_hash($event->getEntity())] = $this->snapshot(
            $event->getClassMetadata(),
            $event->getEntity()
        );
    }

    public function onUpdate(UpdateEvent $event): void
    {
        $this->pendingUpdates[\spl_object_hash($event->getEntity())] = $this->snapshot(
            $event->getClassMetadata(),
            $event->getEntity()
        );
    }

    public function onSync(SyncEvent $event): void
    {
        $changeSet = $this->buildChangeSet(
            $meta = $event->getClassMetadata(),
            $entity = $event->getEntity()
        );

        foreach ($changeSet as $field => [$old, $new]) {
            if ($old === $new) {
                continue;
            }

            $ownerField = $this->getOwnerField($meta, $field);

            if (null !== $old) {
                $this->removeFromCollection($old, $ownerField, $entity);
            }

            if (null !== $new) {
                $this->addToCollection($new, $ownerField, $entity);
            }
        }
    }

    public function onPostSync(PostSyncEvent $event): void
    {
        $hash = \spl_object_hash($event->getEntity());
        unset($this->pendingUpdates[$hash]);
        unset($this->pendingInsertions[$hash]);
        unset($this->pendingDeletions[$hash]);
    }

    private function updateCollection(object $object, string $field, \Closure $callable): void
    {
        if ($object instanceof Collection) {
            // to support many-to-many relations
            foreach ($object as $item) {
                $this->updateCollection($item, $field, $callable);
            }
            return;
        }

        if ($object instanceof Proxy && !$object->__isInitialized()) {
            $object->__load();
        }

        $meta = $this->em->getClassMetadata(ClassUtils::getClass($object));
        /** @var Collection $collection */
        $collection = $meta->getFieldValue($object, $field);

        \call_user_func($callable, $collection);

        $meta->setFieldValue($object, $field, $collection);
        $this->em->persist($object);
    }

    private function getFieldNames(ClassMetadata $meta): array
    {
        $collections = $meta->getAssociationMappings();

        $fields = [];
        foreach ($collections as $mapping) {
            if ($mapping['isOwningSide'] && $mapping['inversedBy']) {
                $fields[$mapping['fieldName']] = $mapping['fieldName'];
            }
        }

        return $fields;
    }

    private function snapshot(ClassMetadata $metadata, ?object $entity): array
    {
        $fields = $this->getFieldNames($metadata);
        $snapshot = [];
        foreach ($fields as $field) {
            $snapshot[$field] = null !== $entity ? $metadata->getFieldValue($entity, $field) : null;
        }

        return $snapshot;
    }

    private function addToCollection(object $object, string $field, object $value): void
    {
        $this->updateCollection($object, $field, function (Collection $collection) use ($value) {
            if (!$collection->contains($value)) {
                $collection->add($value);
            }
        });
    }

    private function removeFromCollection(object $object, string $field, object $value): void
    {
        $this->updateCollection($object, $field, function (Collection $collection) use ($value) {
            if ($collection->contains($value)) {
                $collection->removeElement($value);
            }
        });
    }

    private function getOwnerField(ClassMetadata $meta, string $field): string
    {
        $mapping = $meta->getAssociationMapping($field);

        if (!isset($mapping['inversedBy'])) {
            throw new LogicException(\sprintf('To support CRUD auto insertion/deletion to/from parent collection please specify `inversedBy` annotation properties.'));
        }

        return $mapping['inversedBy'];
    }

    private function buildChangeSet(ClassMetadata $meta, object $entity): array
    {
        $hash = \spl_object_hash($entity);

        if (!isset($this->pendingInsertions[$hash])
            && !isset($this->pendingUpdates[$hash])
            && !isset($this->pendingDeletions[$hash])) {
            return [];
        }

        if (isset($this->pendingDeletions[$hash])) {
            $data = $this->pendingDeletions[$hash];
            $snapshot = $this->snapshot($meta, null);
        } elseif (isset($this->pendingUpdates[$hash])) {
            $data = $this->pendingUpdates[$hash];
            $snapshot = $this->snapshot($meta, $entity);
        } else {
            $data = $this->pendingInsertions[$hash];
            $snapshot = $this->snapshot($meta, $entity);
        }

        $fields = \array_keys($data);
        $changeset = [];
        foreach ($fields as $field) {
            $changeset[$field] = [
                $data[$field],
                $snapshot[$field],
            ];
        }

        return $changeset;
    }
}
