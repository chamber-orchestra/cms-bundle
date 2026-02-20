<?php

declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\Form\Dto;

class BulkOperationDto
{
    public ?string $entities = null;
    public ?bool $all = false;
}
