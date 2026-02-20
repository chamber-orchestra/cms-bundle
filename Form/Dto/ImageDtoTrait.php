<?php

declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\Form\Dto;

use Symfony\Component\HttpFoundation\File\File;

trait ImageDtoTrait
{
    public File|null $image = null;
}
