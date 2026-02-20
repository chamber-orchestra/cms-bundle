<?php

declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\Form\Dto;

use Symfony\Component\Uid\Uuid;

interface DtoInterface
{
    public function getId(): ?Uuid;

    public function getEntityClass(): string;
}