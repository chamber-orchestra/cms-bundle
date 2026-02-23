<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Form\Dto;

use Doctrine\Common\Collections\ArrayCollection;

/**
 * @extends ArrayCollection<int|string, mixed>
 */
class DtoCollection extends ArrayCollection
{
    /**
     * @param array<int|string, mixed> $elements
     *
     * @phpstan-ignore method.childParameterType, parameter.notOptional
     */
    public function __construct(private readonly string $typeClass, array $elements = [])
    {
        parent::__construct($elements);
    }

    public function getTypeClass(): string
    {
        return $this->typeClass;
    }
}
