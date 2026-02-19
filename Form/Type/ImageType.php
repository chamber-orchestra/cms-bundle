<?php

declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\Form\Type;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

class ImageType extends FileType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'required' => false,
            'attr' => ['accept' => 'image/*'],
            'constraints' => [new Image()],
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'dev_image';
    }
}
