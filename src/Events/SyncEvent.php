<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Events;

use Doctrine\ORM\Mapping\ClassMetadata;

final class SyncEvent extends AbstractEvent
{
    /** @param ClassMetadata<object> $classMetadata */
    public function __construct(ClassMetadata $classMetadata, object $entity, private readonly object $dto)
    {
        parent::__construct($classMetadata, $entity);
    }

    public function getDto(): object
    {
        return $this->dto;
    }
}
