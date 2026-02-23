<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;

trait TimestampUpdateTypeTrait // @phpstan-ignore trait.unused
{
    private function addTimestampUpdateChildren(FormBuilderInterface $builder, array $options = []): void
    {
        $builder->add('updated_datetime', DateTimeType::class, [
            'label' => 'timestamp.field.updated_datetime',
            'required' => false,
            'html5' => true,
            'widget' => 'single_text',
            'input' => 'datetime_immutable',
        ]);
    }
}
