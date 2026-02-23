<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Range;

trait SortTypeTrait // @phpstan-ignore trait.unused
{
    private function addSortOrderChild(FormBuilderInterface $builder, array $options = []): void
    {
        $builder->add('sortOrder', IntegerType::class, \array_replace_recursive([
            'label' => 'sort.field.sort_order',
            'required' => false,
            'constraints' => [
                new Range(min: 0),
            ],
        ], $options));
    }
}
