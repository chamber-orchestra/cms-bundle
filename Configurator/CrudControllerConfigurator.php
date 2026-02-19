<?php declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\Configurator;

use ChamberOrchestra\CmsBundle\Controller\AbstractCrudController;
use ChamberOrchestra\CmsBundle\Form\Dto\DtoInterface;
use ChamberOrchestra\CmsBundle\Processor\Instantiator;
use ChamberOrchestra\MenuBundle\Menu\MenuBuilderInterface;
use ChamberOrchestra\MenuBundle\Navigation\ClosureNavigation;
use ChamberOrchestra\MenuBundle\Navigation\NavigationInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Attribute\Route;

class CrudControllerConfigurator
{
    private OptionsResolver $resolver;
    private ServiceLocator $locator;
    private array $resolved = [];
    private EntityManagerInterface $em;

    public function __construct()
    {
        $resolver = $this->resolver = new OptionsResolver();

        $this->configureOptions($resolver);
        $this->configureForms($resolver);
        $this->configureView($resolver);
        $this->configureRoutes($resolver);
        $this->configureActions($resolver);
        $this->configureNav($resolver);
    }

    public function withControllersLocator(ServiceLocator $locator): void
    {
        $this->locator = $locator;
    }

    public function withEntityManager(EntityManagerInterface $em): void
    {
        $this->em = $em;
    }

    public function resolve(array $options): array
    {
        return $this->resolved[$this->cacheKey($options)] ??= $this->resolver->resolve($options);
    }

    private function cacheKey(array $options): string
    {
        // controller_class is always present (added by AbstractCrudController::resolve)
        // and is unique per controller; parent is an object whose identity matters.
        $key = $options['controller_class'] ?? '';
        if (isset($options['parent']) && \is_object($options['parent'])) {
            $key .= '#'.\spl_object_id($options['parent']);
        }

        // For the rare case extra ad-hoc scalar options are passed, append a cheap hash.
        $extra = \array_diff_key($options, ['controller_class' => true, 'parent' => true]);
        if ($extra !== []) {
            \array_walk_recursive($extra, static function (mixed &$v): void {
                if (\is_object($v)) {
                    $v = \get_class($v).'#'.\spl_object_id($v);
                } elseif (\is_callable($v) && !\is_string($v)) {
                    $v = 'fn#'.\spl_object_id(\Closure::fromCallable($v));
                }
            });
            $key .= '|'.\md5(\serialize($extra));
        }

        return $key;
    }

    private function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => 'cms',
        ]);

        $resolver
            ->define('controller_class')
            ->info('The controller class')
            ->required()
            ->allowedTypes('string')
            ->allowedValues(fn(string $v): bool => \class_exists($v));

        $resolver
            ->define('parent_controller_class')
            ->info('The parent controller class')
            ->allowedTypes('null', 'string')
            ->default(null)
            ->allowedValues(fn(?string $v): bool => null === $v || \class_exists($v));

        $resolver
            ->define('parent_controller')
            ->info('The parent controller')
            ->allowedTypes('null', 'object')
            ->default(null)
            ->normalize(fn(Options $o) => $o['parent_controller_class'] ? $this->locator->get($o['parent_controller_class']) : null);

        $resolver
            ->define('class')
            ->info('The class which is controlled')
            ->required()
            ->allowedTypes('string')
            ->allowedValues(fn(string $v): bool => \class_exists($v));

        $resolver
            ->define('parent')
            ->info('The parent object of the controlled class')
            ->allowedTypes('null', 'object')
            ->default(null);

        $resolver
            ->define('_parent')
            ->info('The parent object of the controlled class')
            ->allowedTypes('null', 'object', 'callable', 'string')
            ->default(null)
            ->normalize(function (Options $o, object|callable|string|null $v): object|callable|null {
                if ($o['parent']) {
                    return $o['parent'];
                }

                if (\is_object($v)) {
                    return $v;
                }

                if (\is_string($v)) {
                    $class = new \ReflectionClass($o['class']);
                    $property = $class->getProperty($v);
                    $property->setAccessible(true);

                    return fn(object $entity) => $property->getValue($entity);
                }

                /** @var AbstractCrudController $controller */
                if ($controller = $o['parent_controller']) {
                    $parentClass = $controller->resolve()['class'];
                    foreach ((new \ReflectionClass($o['class']))->getProperties() as $property) {
                        if (($type = $property->getType()) && $type instanceof \ReflectionNamedType) {
                            if ($type->getName() === $parentClass) {
                                $property->setAccessible(true);

                                return fn(object $entity) => $property->getValue($entity);
                            }
                        }
                    }
                }

                return null;
            });

        $label = static function (object $entity): ?string {
            $func = static function (object $entity) {
                if (\method_exists($entity, '__toString')) {
                    return $entity->__toString();
                }

                $class = new \ReflectionClass($entity);
                foreach (['name', 'title', 'id'] as $prop) {
                    try {
                        $prop = $class->getProperty($prop);
                        $prop->setAccessible(true);

                        return (string) $prop->getValue($entity);
                    } catch (\ReflectionException $e) {
                    }
                }

                return null;
            };

            return ($label = $func($entity)) ? \strip_tags($label) : null;
        };

        $resolver
            ->define('entity_label')
            ->allowedTypes('null', 'callable')
            ->default($label);

        $resolver
            ->define('label_format')
            ->allowedTypes('null', 'string')
            ->default(null);
    }

    private function configureNav(OptionsResolver $resolver): void
    {
        $resolver
            ->define('nav')
            ->default(null)
            ->allowedTypes('null', 'bool', 'callable', 'string')
            ->normalize(fn(Options $options, $value) => \is_string($value) ? new $value() : $value);

        $resolver
            ->define('breadcrumbs')
            ->default(null)
            ->allowedTypes('null', 'bool', 'string', 'callable')
            ->normalize(function (Options $options, $value) {
                if (null === $value) {
                    return null;
                }

                return \is_string($value) ? new $value() : $value;
            });
    }

    private function configureForms(OptionsResolver $resolver): void
    {
        $resolver
            ->define('form_class')
            ->info('Form Type class.')
            ->default('')
            ->allowedTypes('null', 'string');

        $resolver
            ->define('data_class')
            ->info('Form Data class.')
            ->default(null)
            ->allowedTypes('callable', 'string', 'null')
            ->allowedValues(function ($value): bool {
                if (\is_string($value)) {
                    return \class_exists($value) && \in_array(DtoInterface::class, \class_implements($value));
                }

                return true;
            })
            ->normalize(function (Options $options, $value) {
                if (\is_string($value)) {
                    return function (string $dataClass, array $options = []) use ($value) {
                        $defined = [
                            'entityClass' => $dataClass,
                            'parent' => $options['parent'],
                        ];

                        return (new Instantiator())->instantiate($value, $defined);
                    };
                }

                return $value;
            });

        $resolver
            ->define('form_themes')
            ->default([
                '@ChamberOrchestraCms/form/horizontal.html.twig',
                '@ChamberOrchestraFile/cms/form/horizontal.html.twig',
            ])
            ->allowedTypes('array');
    }

    private function configureRoutes(OptionsResolver $resolver): void
    {
        $resolver
            ->define('route_prefix')
            ->default(null)
            ->allowedTypes('null', 'string')
            ->normalize(function (Options $options, ?string $value) {
                if (null !== $value) {
                    return \rtrim($value, '_');
                }

                if (null !== ($attr = \current((new \ReflectionClass($options['controller_class']))->getAttributes(Route::class)) ?: null)) {
                    return \rtrim($attr->newInstance()->getName(), '_');
                }

                $class = $options['class'];
                $class = \substr($class, \strrpos($class, '\\') + 1);
                $class = \strtolower(\preg_replace('/(?<!^)[A-Z]/', '_$0', $class));

                return 'cms_'.$class;
            });

        $resolver
            ->define('route_params')
            ->default([])
            ->allowedTypes('array')
            ->normalize(function (Options $options, array $value): array {
                return \array_replace($value, $options['parent'] ? ['parent' => $options['parent']->getId()] : []);
            });
    }

    private function configureView(OptionsResolver $resolver): void
    {
        $resolver
            ->define('view_prefix')
            ->allowedTypes('null', 'string')
            ->default(null)
            ->normalize(function (Options $options, ?string $value) {
                return null !== $value ? \rtrim($value, '/') : null;
            });

        $resolver
            ->define('view_params')
            ->default([])
            ->allowedTypes('array')
            ->normalize(function (Options $options, array $value) {
                $defined = [];
                $default = [
                    'class',
                    'parent',
                    'title',
                    'label_format',
                    'translation_domain',
                    'form_themes',
                ];

                foreach ($default as $key) {
                    $defined[$key] = $options[$key];
                }

                return \array_replace($value, $defined);
            });

        $resolver
            ->define('title')
            ->default(null)
            ->allowedTypes('null', 'string')
            ->normalize(function (Options $options, ?string $value): string {
                if (null !== $value) {
                    return $value;
                }

                return \substr($options['class'], \strrpos($options['class'], '\\') + 1);
            });
    }

    private function configureActions(OptionsResolver $resolver): void
    {
        //configure actions with view
        $createDefaultActionOptions = static function (string $prefix, Options $options): array {
            return [
                'title' => null,
                'view' => null === $options['view_prefix']
                    ? '@ChamberOrchestraCms/crud/'.$prefix.'.html.twig'
                    : \sprintf('%s/%s.html.twig', $options['view_prefix'], $prefix),
                'view_params' => $options['view_params'],
                'route' => \sprintf('%s_%s', $options['route_prefix'], $prefix),
                'route_params' => $options['route_params'],
            ];
        };

        $resolver->setOptions('index', function (OptionsResolver $resolver, Options $parentOptions) use ($createDefaultActionOptions): void {
                $resolver->setDefaults($createDefaultActionOptions('index', $parentOptions));

                $resolver
                    ->define('fields')
                    ->default([])
                    ->allowedTypes('null', 'array')
                    ->normalize(function (Options $options, ?array $value): ?array {
                        if (null === $value) {
                            return null;
                        }

                        $prepareFormats = static function (array $items): array {
                            $formatted = [];
                            foreach ($items as $key => $format) {
                                if (!\is_numeric($key)) {
                                    $formatted[$key] = $format;
                                    continue;
                                }
                                //suppose its transform
                                if (\is_callable($format)) {
                                    $formatted['transform'][] = $format;
                                    continue;
                                }
                                $formatted[$format] = [];
                            }

                            return $formatted;
                        };

                        $formatted = [];
                        foreach ($value as $key => $item) {
                            if (!\is_numeric($key)) {
                                $formats = \is_array($item) ? $item : [$item];
                                $formats = $prepareFormats($formats);
                                $formatted[$key] = $formats;
                                continue;
                            }
                            $formatted[$item] = [];
                        }

                        return $formatted;
                    });

                $resolver
                    ->define('actions')
                    ->default(null)
                    ->allowedTypes('null', 'callable')
                    ->normalize(function (Options $options, ?callable $value): ?callable {
                        if (null === $value) {
                            return null;
                        }

                        return function (object $entity, ?object $parent = null) use ($value): NavigationInterface {
                            return new ClosureNavigation(
                                function (MenuBuilderInterface $builder) use ($value, $entity, $parent): void {
                                    \call_user_func($value, $builder, $entity, $parent);
                                });
                        };
                    });

                $resolver->setAllowedTypes('view_params', []);
                $resolver->setNormalizer('view_params', function (Options $options, array $value) use ($parentOptions): array {
                    return \array_merge([
                        'fields' => $options['fields'],
                        'actions' => $options['actions'],
                        'alias' => $options['alias'],
                    ], $value);
                });

                $resolver
                    ->define('filter')
                    ->default(null)
                    ->allowedValues(function ($value): bool {
                        return null === $value || \is_subclass_of($value, FormTypeInterface::class);
                    });

                $resolver
                    ->define('filter_mapping')
                    ->default([])
                    ->allowedTypes('array');

                $resolver
                    ->define('query_builder')
                    ->default(null)
                    ->allowedTypes(QueryBuilder::class, \Closure::class, 'null')
                    ->normalize(function (Options $options, QueryBuilder|\Closure|null $value) use ($parentOptions) {
                        if ($value instanceof QueryBuilder) {
                            return $value;
                        }

                        $er = $this->em->getRepository($parentOptions['class']);
                        if ($value instanceof \Closure) {
                            return \call_user_func($value, $er);
                        }

                        return $er->createQueryBuilder('r');
                    });

                $resolver
                    ->define('order_by')
                    ->default([])
                    ->allowedTypes('array')
                    ->normalize(function (Options $options, array $value) {
                        return \array_replace($options['orderBy'], $value);
                    });

                $resolver
                    ->define('orderBy')
                    ->deprecated('chamber-orchestra/cms-bundle', '6.0.14')
                    ->default([])
                    ->allowedTypes('array');

                $resolver
                    ->define('order_by_mapping')
                    ->default([])
                    ->allowedTypes('array', 'null')
                    ->normalize(function (Options $options, array|null $value) {
                        if (null === $value) {
                            return [];
                        }

                        $defaults = \array_keys($options['fields']);
                        $normalise = function (array $values) {
                            $normalised = [];
                            foreach ($values as $k => $v) {
                                if (\is_numeric($k)) {
                                    $k = $v;
                                }
                                $normalised[$k] = $v;
                            }
                            return $normalised;
                        };

                        $normalised = [];
                        $skipped = [];
                        $skip = [
                            'image',
                            'video',
                            'preview',
                        ];
                        foreach (\array_replace($normalise($defaults), $normalise($value)) as $k => $v) {
                            if (
                                \in_array($k, $skip)
                                || ('!' === $k[0] && $k = \substr($k, 1))
                                || $v === false
                                || (\str_contains($k, '.') && !\is_callable($v))
                            ) {
                                $skipped[$k] = $k;
                                continue;
                            }
                            $normalised[$k] = $v;
                        }

                        foreach ($skipped as $v) {
                            if (isset($normalised[$v])) {
                                unset($normalised[$v]);
                            }
                        }

                        return $normalised;
                    });

                $resolver
                    ->define('export')
                    ->default([])
                    ->allowedTypes('array', 'null');

                $resolver
                    ->define('alias')
                    ->default([])
                    ->allowedTypes('array', 'null');

                $resolver
                    ->define('bulk_operation')
                    ->default(true)
                    ->allowedTypes('bool');

                $resolver
                    ->define('bulk_nav')
                    ->default(null)
                    ->allowedTypes('null', 'callable')
                    ->normalize(fn(Options $options, $value): ?callable => $options['bulk_operation'] ? $value : null);
            });

        /**
         * Define common actions
         */
        $actions = ['create', 'update', 'view', 'meta', 'export'];
        foreach ($actions as $action) {
            $resolver
                ->define($action)
                ->default([])
                ->allowedTypes('null', 'array')
                ->normalize(function (Options $options, ?array $value) use ($action, $createDefaultActionOptions): ?array {
                    return $value !== null ? \array_replace_recursive($createDefaultActionOptions($action, $options), $value) : null;
                });
        }

        /**
         * Define special actions
         */
        $actions = ['move', 'delete', 'bulk_delete', 'toggle', 'copy'];

        //configure actions without view
        $createDefaultActionOptions = function (string $prefix, Options $options): array {
            return [
                'route' => \sprintf('%s_%s', $options['route_prefix'], $prefix),
                'route_params' => $options['route_params'],
            ];
        };

        foreach ($actions as $action) {
            $resolver
                ->define($action)
                ->default([])
                ->allowedTypes('null', 'array')
                ->normalize(function (Options $options, ?array $value) use ($action, $createDefaultActionOptions): ?array {
                    if ($action === 'bulk_delete' && !$options['index']['bulk_operation']) {
                        return null;
                    }

                    return null !== $value ? \array_replace_recursive($createDefaultActionOptions($action, $options), $value) : null;
                });
        }
    }
}