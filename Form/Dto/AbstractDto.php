<?php

declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\Form\Dto;

use ChamberOrchestra\CmsBundle\Exception\LogicException;
use Symfony\Component\Uid\Uuid;

abstract class AbstractDto implements DtoInterface
{
    protected ?Uuid $id = null;
    protected string $entityClass;

    public function __construct(?string $entityClass = null)
    {
        if (null === $entityClass) {
            $entityClass = \preg_replace('/^Cms\\\\/', '', static::class);
            $entityClass = \preg_replace('/Form\\\\(?:Dto|Data)\\\\/', 'Entity\\\\', $entityClass);
            $entityClass = \preg_replace('/Dto$/', '', $entityClass);
        }

        if (!\class_exists($entityClass)) {
            throw new LogicException(\sprintf('Passed entityClass %s does not exists.', $entityClass));
        }

        $this->entityClass = $entityClass;
    }

    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }
}
