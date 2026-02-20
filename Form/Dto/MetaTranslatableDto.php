<?php

declare(strict_types=1);

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
