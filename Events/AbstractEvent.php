<?php declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\Events;

use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractEvent extends Event
{
    private ClassMetadata $classMetadata;
    private object $entity;

    public function __construct(ClassMetadata $classMetadata, object $entity)
    {
        $this->entity = $entity;
        $this->classMetadata = $classMetadata;
    }

    public function getEntity(): object
    {
        return $this->entity;
    }

    public function getClassMetadata(): ClassMetadata
    {
        return $this->classMetadata;
    }
}