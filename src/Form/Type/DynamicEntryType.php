<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Form\Type;

use ChamberOrchestra\CmsBundle\Form\Dto\ContentEntryDto;
use ChamberOrchestra\FileBundle\Model\File as FileModel;
use ChamberOrchestra\FileBundle\Storage\StorageResolver;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DynamicEntryType extends AbstractType
{
    use CollectionEntrySortTypeTrait;

    public function __construct(private readonly StorageResolver $storage)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['sort']) {
            // Data is a plain PHP array, so property_path must use bracket notation
            // for PropertyAccessor to correctly read/write the array key.
            $this->addSortOrderChild($builder, ['property_path' => '[sortOrder]']);
        }

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options): void {
            $form = $event->getForm();
            /** @var array<int, array<string, mixed>> $fields */
            $fields = \is_array($options['schema']) ? $options['schema'] : [];

            if (empty($fields)) {
                $parent = $form->getParent();
                if (null !== $parent) {
                    $dto = $parent->getData();
                    if ($dto instanceof ContentEntryDto) {
                        $fields = $dto->contentType->getSchema();
                    }
                }
            }

            // Current array being set on this compound form (dto->data copy)
            /** @var array<string, mixed> $data */
            $data = $event->getData() ?? [];

            foreach ($fields as $field) {
                $name = \is_string($field['name'] ?? null) ? $field['name'] : '';
                $type = \is_string($field['type'] ?? null) ? $field['type'] : 'text';
                $label = \is_string($field['label'] ?? null) ? $field['label'] : $name;
                $required = ($field['required'] ?? false) === true;

                // Symfony form child names must match [a-zA-Z0-9_][a-zA-Z0-9_\-:]*.
                // The original $name is preserved in property_path for array key access.
                $formName = (string) \preg_replace('/[^a-zA-Z0-9_\-:]/', '_', $name);
                if ('' === $formName || \preg_match('/^[^a-zA-Z0-9_]/', $formName)) {
                    $formName = '_'.$formName;
                }

                $fieldOptions = [
                    'label' => $label,
                    'required' => $required,
                    'property_path' => '['.$name.']',
                ];

                switch ($type) {
                    case 'wysiwyg':
                        $form->add($formName, WysiwygType::class, $fieldOptions);
                        break;

                    case 'textarea':
                        $form->add($formName, TextareaType::class, \array_merge($fieldOptions, [
                            'attr' => ['rows' => 5],
                        ]));
                        break;

                    case 'image':
                        if (isset($data[$name]) && \is_string($data[$name])) {
                            $uri = $data[$name];
                            $data[$name] = new FileModel($this->uriToFsPath($uri), $uri);
                        }

                        $form->add($formName, ImageType::class, \array_merge($fieldOptions, [
                            'required' => false,
                        ]));
                        break;

                    case 'file':
                        if (isset($data[$name]) && \is_string($data[$name])) {
                            $uri = $data[$name];
                            $data[$name] = new FileModel($this->uriToFsPath($uri), $uri);
                        }

                        $form->add($formName, FileType::class, \array_merge($fieldOptions, [
                            'required' => false,
                        ]));
                        break;

                    case 'collection':
                        // Ensure each existing item has a sortOrder, then sort for correct display.
                        if (!empty($data[$name]) && \is_array($data[$name])) {
                            /** @var array<int, array<string, mixed>> $collectionItems */
                            $collectionItems = $data[$name];
                            foreach ($collectionItems as $i => &$item) {
                                if (!\array_key_exists('sortOrder', $item)) {
                                    $item['sortOrder'] = $i + 1;
                                }
                            }
                            unset($item);
                            \usort($collectionItems, static function (array $a, array $b): int {
                                $aOrder = \is_int($a['sortOrder'] ?? null) ? $a['sortOrder'] : 0;
                                $bOrder = \is_int($b['sortOrder'] ?? null) ? $b['sortOrder'] : 0;

                                return $aOrder <=> $bOrder;
                            });
                            $data[$name] = $collectionItems;
                        }

                        /** @var array<int, array<string, mixed>> $subSchema */
                        $subSchema = \is_array($field['schema'] ?? null) ? $field['schema'] : [];

                        $form->add($formName, CollectionType::class, \array_merge($fieldOptions, [
                            'entry_type' => self::class,
                            'entry_options' => [
                                'schema' => $subSchema,
                                'sort' => true,
                            ],
                            'allow_add' => true,
                            'allow_delete' => true,
                            'by_reference' => false,
                        ]));
                        break;

                    default: // 'text'
                        $form->add($formName, TextType::class, $fieldOptions);
                        break;
                }
            }

            $event->setData($data);
        });
    }

    private function uriToFsPath(string $uri): string
    {
        $storage = $this->storage->get('default');
        $prefix = $storage->resolveUri('');
        if (null !== $prefix && \str_starts_with($uri, $prefix)) {
            return $storage->resolvePath(\substr($uri, \strlen($prefix)));
        }

        return $uri;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'schema' => [],
            'sort' => false,
            'data_class' => null,
        ]);
    }
}
