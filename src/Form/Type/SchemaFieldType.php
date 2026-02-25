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
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class SchemaFieldType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'schema_field.field.name',
                'required' => true,
                'property_path' => '[name]',
                'attr' => ['maxlength' => 64],
                'constraints' => [new NotBlank(), new Length(max: 64)],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'schema_field.field.type',
                'required' => true,
                'property_path' => '[type]',
                'choices' => [
                    'schema_field.type.text' => 'text',
                    'schema_field.type.textarea' => 'textarea',
                    'schema_field.type.wysiwyg' => 'wysiwyg',
                    'schema_field.type.image' => 'image',
                    'schema_field.type.file' => 'file',
                    'schema_field.type.collection' => 'collection',
                ],
                'choice_translation_domain' => 'content',
                'attr' => ['data-schema-type' => ''],
            ])
            ->add('required', CheckboxType::class, [
                'label' => 'schema_field.field.required',
                'required' => false,
                'property_path' => '[required]',
            ])
            ->add('schema', CollectionType::class, [
                'label' => 'schema_field.field.schema',
                'translation_domain' => 'content',
                'required' => false,
                'property_path' => '[schema]',
                'entry_type' => SchemaItemFieldType::class,
                'entry_options' => ['label' => false, 'translation_domain' => 'content'],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype_name' => '__item__',
                'row_attr' => ['data-collection-only' => ''],
            ]);

        $builder->addEventListener(FormEvents::SUBMIT, static function (FormEvent $event): void {
            $data = $event->getData();
            if (\is_array($data) && ($data['type'] ?? '') !== 'collection') {
                unset($data['schema']);
            }
            $event->setData($data);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'content',
        ]);
    }
}
