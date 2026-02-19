<?php declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\Form\Dto;

use Doctrine\Common\Collections\ArrayCollection;

class DtoCollection extends ArrayCollection
{
    public function __construct(private readonly string $typeClass, array $elements = [])
    {
        parent::__construct($elements);
    }

    public function getTypeClass(): string
    {
        return $this->typeClass;
    }
}