<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Range;

trait CollectionEntrySortTypeTrait // @phpstan-ignore trait.unused
{
    private function addSortOrderChild(FormBuilderInterface $builder, array $options = []): void
    {
        $builder->add('sortOrder', HiddenType::class, \array_replace_recursive([
            'priority' => 1000,
            'label' => false,
            'required' => true,
            'empty_data' => 0,
            'constraints' => [
                new Range(min: 0),
            ],
        ], $options));
    }
}
