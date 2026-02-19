<?php

declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\Form\Type;

use Symfony\Component\Clock\DatePoint;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;

trait TimestampCreateTypeTrait
{
    private function addTimestampCreateChildren(FormBuilderInterface $builder, array $options = []): void
    {
        $builder->add('createdDatetime', DateTimeType::class, \array_replace_recursive([
            'label' => 'timestamp.field.created_datetime',
            'required' => false,
            'html5' => true,
            'widget' => 'single_text',
            'input' => 'datetime_immutable',
            'empty_data' => (new DatePoint())->format('c'),
        ], $options));
    }
}
