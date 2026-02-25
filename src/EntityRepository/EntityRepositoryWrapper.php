<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\EntityRepository;

use ChamberOrchestra\CmsBundle\EntityRepository\Helper\FilterHelper;
use ChamberOrchestra\CmsBundle\EntityRepository\Helper\SortHelper;
use ChamberOrchestra\CmsBundle\Exception\RuntimeException;
use ChamberOrchestra\PaginationBundle\Pagination\PaginationInterface;
use ChamberOrchestra\PaginationBundle\PagingInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

readonly class EntityRepositoryWrapper
{
    /** @param EntityRepository<object> $er */
    public function __construct(private EntityRepository $er)
    {
    }

    /**
     * @param array<string, mixed>      $filter
     * @param array<string, mixed>      $filterMapping
     * @param array<string, mixed>|null $orderBy
     * @param array<string, mixed>      $orderMapping
     *
     * @return iterable<object>
     */
    public function filterBy(QueryBuilder $qb, array $filter, array $filterMapping = [], ?array $orderBy = null, array $orderMapping = [], PaginationInterface|int|null $pagination = null): iterable
    {
        (new FilterHelper($qb))($filter, $filterMapping);
        (new SortHelper($qb))($orderBy ?? [], $orderMapping);

        if ($pagination instanceof PaginationInterface) {
            $class = new \ReflectionClass($this->er);
            if (!$class->hasProperty('paging')) {
                throw new RuntimeException(\sprintf('To support pagination %s should have access to $paging property', \get_class($this->er)));
            }

            $property = $class->getProperty('paging');
            /** @var PagingInterface $paging */
            $paging = $property->getValue($this->er);

            /** @var iterable<object> $result */
            $result = $paging->paginate($qb, $pagination);

            return $result;
        }

        if (null !== $pagination) {
            $qb->setMaxResults($pagination);
        }

        /** @var list<object> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }
}
