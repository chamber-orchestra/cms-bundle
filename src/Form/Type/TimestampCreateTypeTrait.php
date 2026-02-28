<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Form\Type;

use Symfony\Component\Clock\DatePoint;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;

trait TimestampCreateTypeTrait // @phpstan-ignore trait.unused
{
    private function addTimestampCreateChildren(FormBuilderInterface $builder, array $options = []): void
    {
        $builder->add('createdDatetime', DateTimeType::class, \array_replace_recursive([
            'label' => 'timestamp.field.created_datetime',
            'required' => false,
            'html5' => true,
            'widget' => 'single_text',
            'input' => 'datetime_immutable',
            'empty_data' => new DatePoint()->format('c'),
        ], $options));
    }
}
