<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Processor;

use ChamberOrchestra\CmsBundle\Processor\Helper\MoveDirection;
use ChamberOrchestra\DoctrineExtensionsBundle\Contracts\Entity\SoftDeleteInterface;
use ChamberOrchestra\DoctrineSortBundle\Contracts\Entity\SortInterface;
use ChamberOrchestra\DoctrineSortBundle\Mapping\Configuration\SortConfiguration;
use Doctrine\Common\Util\ClassUtils;

/**
 * @phpstan-require-extends CrudProcessor
 */
trait SortProcessorTrait
{
    public function move(object $entity, string $direction): void
    {
        if (!$entity instanceof SortInterface) {
            throw new \LogicException(\sprintf('To support movements entity "%s" must implement "%s" interface.', $entity::class, SortInterface::class));
        }

        $config = $this->getConfiguration($this->em, $entity, SortConfiguration::class);
        if (!$config instanceof SortConfiguration) {
            throw new \LogicException(\sprintf('Entity "%s" has no SortConfiguration.', $entity::class));
        }

        $meta = $this->em->getClassMetadata(ClassUtils::getClass($entity));
        /** @var int|string|null $rawSortOrder */
        $rawSortOrder = $meta->getFieldValue($entity, $field = $config->getSortField());
        $sortOrder = (int) $rawSortOrder;

        $moveDirection = MoveDirection::tryFrom(\mb_strtolower($direction))
            ?? throw new \InvalidArgumentException(\sprintf('Direction "%s" is not supported.', $direction));

        $dir = match ($moveDirection) {
            MoveDirection::DOWN => 1,
            MoveDirection::UP => -1,
        };

        $this->em->getConnection()->beginTransaction();

        try {
            $meta->setFieldValue($entity, $field, $this->getNextSortOrder($dir, $sortOrder, $field, $entity));
            $this->em->persist($entity);
            $this->em->flush();
            $this->em->getConnection()->commit();
        } catch (\Throwable $e) {
            $this->em->getConnection()->rollBack();

            throw $e;
        }
    }

    private function getNextSortOrder(int $dir, int $sortOrder, string $sortField, object $entity): int
    {
        if (!$entity instanceof SoftDeleteInterface) {
            return $sortOrder + $dir;
        }

        $class = ClassUtils::getClass($entity);
        $er = $this->em->getRepository($class);
        $alias = 'n';

        // Single query: find the nearest non-deleted sibling in the given direction
        $qb = $er->createQueryBuilder($alias)
            ->select($alias.'.'.$sortField)
            ->andWhere($alias.'.deletedDatetime IS NULL');

        if ($dir > 0) {
            $qb->andWhere($alias.'.'.$sortField.' > :current')
                ->setParameter('current', $sortOrder)
                ->orderBy($alias.'.'.$sortField, 'ASC');
        } else {
            $qb->andWhere($alias.'.'.$sortField.' < :current')
                ->setParameter('current', $sortOrder)
                ->orderBy($alias.'.'.$sortField, 'DESC');
        }

        /** @var array<string, int|string|null>|null $result */
        $result = $qb->setMaxResults(1)->getQuery()->getOneOrNullResult();

        return null !== $result ? (int) $result[$sortField] : $sortOrder + $dir;
    }
}
