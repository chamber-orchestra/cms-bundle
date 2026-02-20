<?php

declare(strict_types=1);

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
        $builder->add('translations', TranslationsType::class, [
            'entry_type' => MetaType::class,
        ]);
    }
}
