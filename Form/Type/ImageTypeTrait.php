<?php

declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Image;

trait ImageTypeTrait
{
    private function addImageChild(FormBuilderInterface $builder, array $options = []): void
    {
        $builder->add('image', ImageType::class, \array_replace_recursive([
            'label' => 'file.field.image',
            'required' => false,
            'constraints' => [
                new Image(detectCorrupted: true),
            ],
        ], $options));
    }
}
