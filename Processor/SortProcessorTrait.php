<?php

declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\Processor;

use ChamberOrchestra\CmsBundle\Processor\Helper\MoveDirection;
use ChamberOrchestra\DoctrineExtensionsBundle\Contracts\Entity\SoftDeleteInterface;
use ChamberOrchestra\DoctrineSortBundle\Contracts\Entity\SortInterface;
use ChamberOrchestra\DoctrineSortBundle\Mapping\Configuration\SortConfiguration;
use Doctrine\Common\Util\ClassUtils;

/**
 * @mixin CrudProcessor
 */
trait SortProcessorTrait
{
    public function move(object $entity, string $direction): void
    {
        if (!$entity instanceof SortInterface) {
            throw new \LogicException(\sprintf('To support movements entity "%s" must implement "%s" interface.', \get_class($entity), SortInterface::class));
        }

        /** @var SortConfiguration $config */
        $config = $this->getConfiguration($this->em, $entity, SortConfiguration::class);

        $meta = $this->em->getClassMetadata(ClassUtils::getClass($entity));
        $sortOrder = (int) $meta->getFieldValue($entity, $field = $config->getSortField());

        $moveDirection = MoveDirection::tryFrom(\mb_strtolower($direction))
            ?? throw new \InvalidArgumentException(\sprintf('Direction "%s" is not supported.', $direction));

        $dir = match ($moveDirection) {
            MoveDirection::DOWN => 1,
            MoveDirection::UP => -1,
        };

        $meta->setFieldValue($entity, $field, $this->getNextSortOrder($dir, $sortOrder, $field, $entity));
        $this->em->persist($entity);
        $this->em->flush();
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

        $result = $qb->setMaxResults(1)->getQuery()->getOneOrNullResult();

        return $result !== null ? (int) $result[$sortField] : $sortOrder + $dir;
    }
}
