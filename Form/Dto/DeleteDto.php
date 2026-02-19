<?php

declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\Form\Dto;

class DeleteDto extends AbstractDto
{
    public ?object $entity = null;
    public ?string $targetPath = null;
}
