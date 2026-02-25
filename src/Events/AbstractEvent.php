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
use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractEvent extends Event
{
    /** @param ClassMetadata<object> $classMetadata */
    public function __construct(
        /** @var ClassMetadata<object> */
        private readonly ClassMetadata $classMetadata,
        private readonly object $entity,
    ) {
    }

    public function getEntity(): object
    {
        return $this->entity;
    }

    /** @return ClassMetadata<object> */
    public function getClassMetadata(): ClassMetadata
    {
        return $this->classMetadata;
    }
}
