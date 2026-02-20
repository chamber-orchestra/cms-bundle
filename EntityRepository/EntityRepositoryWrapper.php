<?php declare(strict_types=1);

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
    public function __construct(private EntityRepository $er)
    {
    }

    public function filterBy(QueryBuilder $qb, array $filter, array $filterMapping = [], array $orderBy = null, array $orderMapping = [], PaginationInterface|int|null $pagination = null): iterable
    {
        (new FilterHelper($qb))($filter, $filterMapping);
        (new SortHelper($qb))($orderBy, $orderMapping);

        if ($pagination instanceof PaginationInterface) {
            $class = new \ReflectionClass($this->er);
            if (!$class->hasProperty('paging')) {
                throw new RuntimeException(\sprintf('To support pagination %s should have access to $paging property', \get_class($this->er)));
            }

            $property = $class->getProperty('paging');
            /** @var PagingInterface $paging */
            $paging = $property->getValue($this->er);

            return $paging->paginate($qb, $pagination);
        }

        if (null !== $pagination) {
            $qb->setMaxResults($pagination);
        }

        return $qb->getQuery()->getResult();
    }
}