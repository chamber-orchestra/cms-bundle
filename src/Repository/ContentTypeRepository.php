<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Repository;

use ChamberOrchestra\CmsBundle\Entity\ContentType;
use ChamberOrchestra\DoctrineExtensionsBundle\Repository\ServiceEntityRepository;
use ChamberOrchestra\PaginationBundle\PaginationAwareTrait;
use Doctrine\Persistence\ManagerRegistry;

class ContentTypeRepository extends ServiceEntityRepository
{
    use PaginationAwareTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContentType::class);
    }

    public function findByName(string $name): ?ContentType
    {
        $result = $this->findOneBy(['name' => $name]);

        return $result instanceof ContentType ? $result : null;
    }
}
