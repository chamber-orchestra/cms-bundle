<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Form\Dto;

use ChamberOrchestra\Meta\Entity\Helper\RobotsBehaviour;
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
