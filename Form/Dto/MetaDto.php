<?php

declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\Form\Dto;

use ChamberOrchestra\MetaBundle\Entity\Helper\RobotsBehaviour;
use Symfony\Component\HttpFoundation\File\File;

class MetaDto extends AbstractDto
{
    public ?string $title = null;
    public ?RobotsBehaviour $robotsBehaviour = null;
    public ?string $metaTitle = null;
    public ?string $metaDescription = null;
    public ?string $metaKeywords = null;
    public ?File $metaImage = null;
}
