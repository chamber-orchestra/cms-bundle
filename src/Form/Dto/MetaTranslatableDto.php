<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Form\Dto;

use ChamberOrchestra\CmsBundle\Form\Type\MetaTranslatableType;
use ChamberOrchestra\TranslationBundle\Cms\Form\Dto\TranslatableDtoTrait;

class MetaTranslatableDto extends AbstractDto
{
    use TranslatableDtoTrait;

    public function __construct()
    {
        $this->translations = new DtoCollection(MetaDto::class);
        parent::__construct(MetaTranslatableType::class);
    }
}
