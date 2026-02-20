<?php

declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\Form\Dto;

trait SlugDtoTrait
{
    public string|null $name = null;
    public string $slug = '';
}
