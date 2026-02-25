<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\EventSubscriber;

use ChamberOrchestra\CmsBundle\Entity\ContentEntry;
use ChamberOrchestra\FileBundle\Model\File as FileModel;
use ChamberOrchestra\FileBundle\NamingStrategy\HashingNamingStrategy;
use ChamberOrchestra\FileBundle\Storage\StorageInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
final class ContentEntryUploadSubscriber
{
    public function __construct(
        private readonly StorageInterface $storage,
        private readonly HashingNamingStrategy $strategy,
    ) {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $this->processUploads($args->getObject());
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof ContentEntry) {
            return;
        }

        $this->processUploads($entity);

        $args->getObjectManager()
            ->getUnitOfWork()
            ->recomputeSingleEntityChangeSet(
                $args->getObjectManager()->getClassMetadata(ContentEntry::class),
                $entity,
            );
    }

    private function processUploads(object $entity): void
    {
        if (!$entity instanceof ContentEntry) {
            return;
        }

        $data = $entity->getData();
        $changed = false;

        $this->processArray($data, $changed);
        $this->sortCollections($data, $entity->getContentType()->getSchema(), $changed);

        if ($changed) {
            $entity->setData($data);
        }
    }

    /**
     * @param array<string, mixed>             $data
     * @param array<int, array<string, mixed>> $schema
     */
    private function sortCollections(array &$data, array $schema, bool &$changed): void
    {
        foreach ($schema as $field) {
            if (($field['type'] ?? '') !== 'collection') {
                continue;
            }

            $name = \is_string($field['name'] ?? null) ? $field['name'] : '';

            if (!isset($data[$name]) || !\is_array($data[$name])) {
                continue;
            }

            /** @var array<int, array<string, mixed>> $items */
            $items = $data[$name];

            foreach ($items as &$item) {
                $raw = $item['sortOrder'] ?? null;
                if (null !== $raw && !\is_int($raw)) {
                    $item['sortOrder'] = \is_numeric($raw) ? (int) $raw : 0;
                    $changed = true;
                }
            }
            unset($item);

            $sorted = $items;
            \usort($sorted, static function (array $a, array $b): int {
                $aOrder = \is_int($a['sortOrder'] ?? null) ? $a['sortOrder'] : 0;
                $bOrder = \is_int($b['sortOrder'] ?? null) ? $b['sortOrder'] : 0;

                return $aOrder <=> $bOrder;
            });

            if ($sorted !== $items) {
                $changed = true;
            }

            $data[$name] = $sorted;
        }
    }

    /** @param array<string, mixed> $data */
    private function processArray(array &$data, bool &$changed): void
    {
        foreach ($data as $key => $value) {
            if ($value instanceof UploadedFile) {
                $data[$key] = $this->storage->resolveUri(
                    $this->storage->upload($value, $this->strategy),
                );
                $changed = true;
            } elseif ($value instanceof FileModel) {
                $data[$key] = $value->getUri();
                $changed = true;
            } elseif (\is_array($value)) {
                /** @var array<string, mixed> $nested */
                $nested = &$data[$key];
                $this->processArray($nested, $changed);
            }
        }
    }
}
