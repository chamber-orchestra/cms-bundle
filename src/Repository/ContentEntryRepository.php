<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Repository;

use ChamberOrchestra\CmsBundle\Entity\ContentEntry;
use ChamberOrchestra\DoctrineExtensionsBundle\Repository\ServiceEntityRepository;
use ChamberOrchestra\PaginationBundle\PaginationAwareTrait;
use Doctrine\Persistence\ManagerRegistry;

class ContentEntryRepository extends ServiceEntityRepository
{
    use PaginationAwareTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContentEntry::class);
    }

    /**
     * @param array<string, string> $orderBy
     *
     * @return list<ContentEntry>
     */
    public function findByOwner(object $owner, array $orderBy = ['sortOrder' => 'ASC']): array
    {
        // @phpstan-ignore-next-line
        $ownerId = (string) $owner->getId();

        /** @var list<ContentEntry> $result */
        $result = $this->createQueryBuilder('e')
            ->where('e.ownerClass = :ownerClass')
            ->andWhere('e.ownerId = :ownerId')
            ->setParameter('ownerClass', $owner::class)
            ->setParameter('ownerId', $ownerId)
            ->orderBy('e.'.(\array_key_first($orderBy) ?? 'sortOrder'), \reset($orderBy) ?: 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * @param array<string, string> $orderBy
     *
     * @return list<ContentEntry>
     */
    public function findByTypeName(string $typeName, array $orderBy = ['sortOrder' => 'ASC']): array
    {
        /** @var list<ContentEntry> $result */
        $result = $this->createQueryBuilder('e')
            ->innerJoin('e.contentType', 'ct')
            ->andWhere('ct.name = :name')
            ->setParameter('name', $typeName)
            ->orderBy('e.'.(\array_key_first($orderBy) ?? 'sortOrder'), \reset($orderBy) ?: 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * @param array<string, string> $orderBy
     *
     * @return list<ContentEntry>
     */
    public function findByOwnerAndTypeName(object $owner, string $typeName, array $orderBy = ['sortOrder' => 'ASC']): array
    {
        // @phpstan-ignore-next-line
        $ownerId = (string) $owner->getId();

        /** @var list<ContentEntry> $result */
        $result = $this->createQueryBuilder('e')
            ->innerJoin('e.contentType', 'ct')
            ->where('e.ownerClass = :ownerClass')
            ->andWhere('e.ownerId = :ownerId')
            ->andWhere('ct.name = :name')
            ->setParameter('ownerClass', $owner::class)
            ->setParameter('ownerId', $ownerId)
            ->setParameter('name', $typeName)
            ->orderBy('e.'.(\array_key_first($orderBy) ?? 'sortOrder'), \reset($orderBy) ?: 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * @param array<string, string> $orderBy
     *
     * @return list<ContentEntry>
     */
    public function findByOwnerId(string $ownerId, array $orderBy = ['sortOrder' => 'ASC']): array
    {
        /** @var list<ContentEntry> $result */
        $result = $this->createQueryBuilder('e')
            ->where('e.ownerId = :ownerId')
            ->setParameter('ownerId', $ownerId)
            ->orderBy('e.'.(\array_key_first($orderBy) ?? 'sortOrder'), \reset($orderBy) ?: 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * @param array<string, string> $orderBy
     *
     * @return list<ContentEntry>
     */
    public function findByOwnerClassAndTypeName(
        string $ownerClass,
        string $ownerId,
        string $typeName,
        array $orderBy = ['sortOrder' => 'ASC'],
    ): array {
        /** @var list<ContentEntry> $result */
        $result = $this->createQueryBuilder('e')
            ->innerJoin('e.contentType', 'ct')
            ->where('e.ownerClass = :ownerClass')
            ->andWhere('e.ownerId = :ownerId')
            ->andWhere('ct.name = :name')
            ->setParameter('ownerClass', $ownerClass)
            ->setParameter('ownerId', $ownerId)
            ->setParameter('name', $typeName)
            ->orderBy('e.'.(\array_key_first($orderBy) ?? 'sortOrder'), \reset($orderBy) ?: 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }
}
