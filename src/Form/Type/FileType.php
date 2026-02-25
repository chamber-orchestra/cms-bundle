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
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\NotBlank;

class FileType extends AbstractType
{
    private const ATTR_HOLDER = 'file_holder';

    /** @var array<string, mixed> */
    private array $originalFile = [];

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'error_bubbling' => false,
            'required' => false,
            'multiple' => false,
            'mime_types' => [],
            'attr' => fn (Options $options): array => [
                'accept' => \implode(',', (array) $options['mime_types']),
            ],
            'constraints' => function (Options $options): array {
                /** @var array<string> $mimeTypes */
                $mimeTypes = $options['mime_types'];
                $constraints = [
                    new \Symfony\Component\Validator\Constraints\File(
                        mimeTypes: $mimeTypes,
                    ),
                ];

                if ($options['multiple']) {
                    $constraints = [new All($constraints)];
                }

                if ($options['required']) {
                    $constraints[] = new NotBlank();
                }

                return $constraints;
            },
        ]);

        $resolver->setOptions('delete_options', function (OptionsResolver $resolver): void {
            $resolver->setDefaults([
                'required' => false,
                'error_bubbling' => false,
                'label' => 'file.field.delete',
            ]);
        });

        $resolver->setOptions('entry_options', function (OptionsResolver $resolver, Options $parent): void {
            $keys = [
                'attr',
                'label',
                'translation_domain',
                'multiple',
            ];
            $map = [];
            foreach ($keys as $key) {
                $map[$key] = $parent[$key];
            }

            $resolver->setDefaults(\array_merge([
                'error_bubbling' => false,
                'required' => false,
            ], $map));
        });
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars = \array_replace_recursive($view->vars, [
            'originalFile' => $this->originalFile,
            'required' => $options['required'],
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->setAttribute(self::ATTR_HOLDER, \uniqid());
        /** @var array<string, mixed> $entryOptions */
        $entryOptions = $options['entry_options'];
        $builder
            ->add(
                'file',
                \Symfony\Component\Form\Extension\Core\Type\FileType::class,
                $entryOptions
            )
        ;

        $that = $this;
        $builder->addEventListener(
            FormEvents::POST_SET_DATA,
            function (FormEvent $event) use ($that): void {
                $form = $event->getForm();
                /** @var string $attr */
                $attr = $form->getConfig()->getAttribute(self::ATTR_HOLDER);
                $that->originalFile[$attr] = $form->getData();
            });

        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function (FormEvent $event): void {
                $form = $event->getForm();
                /** @var array<string, mixed> $data */
                $data = $event->getData();
                /** @var string $attr */
                $attr = $form->getConfig()->getAttribute(self::ATTR_HOLDER);
                $data['file'] = $data['file'] ?? $this->originalFile[$attr];

                $event->setData($data);
            });

        if (false === $options['required']) {
            $builder->addEventListener(
                FormEvents::POST_SET_DATA,
                function (FormEvent $event) use ($options): void {
                    $form = $event->getForm();
                    $file = $form->getData();

                    /** @var array<string, mixed> $deleteOptions */
                    $deleteOptions = $options['delete_options'];
                    $form->add('delete', CheckboxType::class, \array_replace_recursive([
                        'required' => false,
                        'disabled' => null === $file,
                    ], $deleteOptions));
                });

            $reverseCallback = function (array $data) {
                if (isset($data['delete']) && $data['delete']) {
                    return null;
                }

                return $data['file'];
            };
        } else {
            $reverseCallback = function (array $data) {
                return $data['file'];
            };
        }

        // empty form after submit
        $builder->addViewTransformer(new CallbackTransformer(
            function ($value = null): array {
                if (null === $value || $value instanceof UploadedFile || \is_array($value)) {
                    return ['file' => null];
                }

                /* @var $value ?\ChamberOrchestra\FileBundle\Model\File */
                return ['file' => $value];
            },
            $reverseCallback
        ));
    }

    public function getBlockPrefix(): string
    {
        return 'chamber_orchestra_file';
    }
}
