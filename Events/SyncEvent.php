<?php declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\Events;

use Doctrine\ORM\Mapping\ClassMetadata;

final class SyncEvent extends AbstractEvent
{
    private object $dto;

    public function __construct(ClassMetadata $classMetadata, object $entity, object $dto)
    {
        $this->dto = $dto;
        parent::__construct($classMetadata, $entity);
    }

    public function getDto(): object
    {
        return $this->dto;
    }
}