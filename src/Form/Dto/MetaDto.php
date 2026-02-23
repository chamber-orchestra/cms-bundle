<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Form\Dto;

use Symfony\Component\HttpFoundation\File\File;

class MetaDto extends AbstractDto
{
    public ?string $title = null;
    /** @phpstan-ignore class.notFound */
    public ?\ChamberOrchestra\MetaBundle\Entity\Helper\RobotsBehaviour $robotsBehaviour = null;
    public ?string $metaTitle = null;
    public ?string $metaDescription = null;
    public ?string $metaKeywords = null;
    public ?File $metaImage = null;
}
