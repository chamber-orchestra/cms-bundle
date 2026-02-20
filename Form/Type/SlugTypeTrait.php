<?php

declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\Form\Type;

use ChamberOrchestra\CmsBundle\Form\Dto\AbstractDto;
use ChamberOrchestra\CmsBundle\Regex\UrlRegex;
use ChamberOrchestra\FormBundle\Validator\Constraints\UniqueField;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

trait SlugTypeTrait
{
    private function addSlugChildren(FormBuilderInterface $builder, array $options = []): void
    {
        $builder
            ->add('name', TextType::class, \array_replace_recursive([
                'label' => 'slug.field.name',
                'required' => true,
                'attr' => ['maxlength' => $max = 127],
                'constraints' => [
                    new NotBlank(),
                    new Length(max: $max),
                ],
            ], $options))
            ->add('slug', TextType::class);

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event): void {
            /** @var AbstractDto $data */
            $data = $event->getData();
            $form = $event->getForm();

            if (null === $data || null === $data->getId()) {
                $form->remove('slug');

                return;
            }

            $form
                ->add('slug', TextType::class, [
                    'label' => 'slug.field.slug',
                    'required' => true,
                    'attr' => ['maxlength' => $max = 255],
                    'constraints' => [
                        new NotBlank(),
                        new Length(max: $max),
                        new UniqueField(
                            fields: ['slug'],
                            entityClass: $data->getEntityClass(),
                            exclude: ['id' => $data->getId()],
                        ),
                        new Callback(
                            callback: function ($value, ExecutionContextInterface $context, $payload) {
                                if (null === $value) {
                                    return;
                                }

                                if (\preg_match('/'.UrlRegex::getInvalidPathPartChars().'/ui', $value)) {
                                    $context
                                        ->buildViolation('slug.contains_invalid_chars')
                                        ->addViolation();
                                }
                            },
                        ),
                    ],
                ]);
        });
    }
}
