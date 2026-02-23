<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Form\Type;

use ChamberOrchestra\CmsBundle\Form\Dto\MetaTranslatableDto;
use ChamberOrchestra\TranslationBundle\Cms\Form\Type\TranslationsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MetaTranslatableType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MetaTranslatableDto::class,
            'translation_domain' => 'cms',
            'label_format' => 'meta.field.%name%',
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('translations', TranslationsType::class, [ // @phpstan-ignore class.notFound, argument.type
            'entry_type' => MetaType::class,
        ]);
    }
}
