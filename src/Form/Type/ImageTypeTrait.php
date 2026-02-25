<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Image;

trait ImageTypeTrait // @phpstan-ignore trait.unused
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
