<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ContentTypeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'content_type.field.name',
                'translation_domain' => 'content',
                'required' => true,
                'attr' => ['maxlength' => 255],
                'constraints' => [new NotBlank(), new Length(max: 255)],
            ])
            ->add('schema', CollectionType::class, [
                'label' => 'content_type.field.schema',
                'translation_domain' => 'content',
                'required' => false,
                'entry_type' => SchemaFieldType::class,
                'entry_options' => ['label' => false, 'translation_domain' => 'content'],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => 'content',
        ]);
    }
}
