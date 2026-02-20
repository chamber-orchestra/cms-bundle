<?php

declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;

trait FileTypeTrait
{
    private function addFileChild(FormBuilderInterface $builder, array $options = []): void
    {
        $builder->add('file', FileType::class, \array_replace_recursive([
            'required' => false,
            'label' => 'file.field.file',
        ], $options));
    }
}
