<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Configurator;

use ChamberOrchestra\CmsBundle\Controller\AbstractCrudController;
use ChamberOrchestra\CmsBundle\Form\Dto\DtoInterface;
use ChamberOrchestra\CmsBundle\Processor\Instantiator;
use ChamberOrchestra\MenuBundle\Menu\MenuBuilder;
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

    /** @var ServiceLocator<mixed> */
    private ServiceLocator $locator;

    /** @var array<string, array<string, mixed>> */
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

    /**
     * @param ServiceLocator<mixed> $locator
     */
    public function withControllersLocator(ServiceLocator $locator): void
    {
        $this->locator = $locator;
    }

    public function withEntityManager(EntityManagerInterface $em): void
    {
        $this->em = $em;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function resolve(array $options): array
    {
        /** @var array<string, mixed> $result */
        $result = $this->resolved[$this->cacheKey($options)] ??= $this->resolver->resolve($options); // @phpstan-ignore assign.propertyType

        return $result;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function cacheKey(array $options): string
    {
        // controller_class is always present (added by AbstractCrudController::resolve)
        // and is unique per controller; parent is an object whose identity matters.
        $key = \is_string($options['controller_class'] ?? '') ? $options['controller_class'] ?? '' : '';
        if (isset($options['parent']) && \is_object($options['parent'])) {
            $key .= '#'.\spl_object_id($options['parent']);
        }

        // For the rare case extra ad-hoc scalar options are passed, append a cheap hash.
        $extra = \array_diff_key($options, ['controller_class' => true, 'parent' => true]);
        if ([] !== $extra) {
            \array_walk_recursive($extra, static function (mixed &$v): void {
                if (\is_object($v)) {
                    $v = $v::class.'#'.\spl_object_id($v);
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
            ->allowedValues(static fn (string $v): bool => \class_exists($v));

        $resolver
            ->define('parent_controller_class')
            ->info('The parent controller class')
            ->allowedTypes('null', 'string')
            ->default(null)
            ->allowedValues(static fn (?string $v): bool => null === $v || \class_exists($v));

        $resolver
            ->define('parent_controller')
            ->info('The parent controller')
            ->allowedTypes('null', 'object')
            ->default(null)
            ->normalize(fn (Options $o) => $o['parent_controller_class'] ? $this->locator->get((string) $o['parent_controller_class']) : null); // @phpstan-ignore cast.string

        $resolver
            ->define('class')
            ->info('The class which is controlled')
            ->required()
            ->allowedTypes('string')
            ->allowedValues(static fn (string $v): bool => \class_exists($v));

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
            ->normalize(static function (Options $o, object|callable|string|null $v): object|callable|null {
                if ($o['parent']) {
                    /** @var object $parent */
                    $parent = $o['parent'];

                    return $parent;
                }

                if (\is_object($v)) {
                    return $v;
                }

                if (\is_string($v)) {
                    /** @var class-string $entityClass */
                    $entityClass = $o['class'];
                    $class = new \ReflectionClass($entityClass);
                    $property = $class->getProperty($v);

                    return static fn (object $entity) => $property->getValue($entity);
                }

                /** @var AbstractCrudController|null $controller */
                $controller = $o['parent_controller'];
                if ($controller) {
                    $parentClass = $controller->resolve()['class'];
                    /** @var class-string $childClass */
                    $childClass = $o['class'];
                    foreach (new \ReflectionClass($childClass)->getProperties() as $property) {
                        if (($type = $property->getType()) && $type instanceof \ReflectionNamedType) {
                            if ($type->getName() === $parentClass) {
                                return static fn (object $entity) => $property->getValue($entity);
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

                        return (string) $prop->getValue($entity); // @phpstan-ignore cast.string
                    } catch (\ReflectionException $e) {
                    }
                }

                return null;
            };

            /** @var string|null $label */
            $label = $func($entity);

            return $label ? \strip_tags($label) : null;
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
            ->normalize(static fn (Options $options, bool|callable|string|null $value): bool|callable|object|null => \is_string($value) ? new $value() : $value);

        $resolver
            ->define('breadcrumbs')
            ->default(null)
            ->allowedTypes('null', 'bool', 'string', 'callable')
            ->normalize(static function (Options $options, bool|callable|string|null $value): bool|callable|object|null {
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
            ->allowedValues(static function (mixed $value): bool {
                if (\is_string($value)) {
                    return \class_exists($value) && \in_array(DtoInterface::class, \class_implements($value) ?: [], true);
                }

                return true;
            })
            ->normalize(static function (Options $options, callable|string|null $value): ?callable {
                if (\is_string($value)) {
                    return static function (string $dataClass, array $options = []) use ($value) {
                        $defined = [
                            'entityClass' => $dataClass,
                            'parent' => $options['parent'],
                        ];

                        return new Instantiator()->instantiate($value, $defined);
                    };
                }

                return \is_callable($value) ? $value : null;
            });

        $resolver
            ->define('form_themes')
            ->default([
                '@ChamberOrchestraCms/form/horizontal.html.twig',
                '@ChamberOrchestraCms/form/file.html.twig',
            ])
            ->allowedTypes('array');
    }

    private function configureRoutes(OptionsResolver $resolver): void
    {
        $resolver
            ->define('route_prefix')
            ->default(null)
            ->allowedTypes('null', 'string')
            ->normalize(static function (Options $options, ?string $value): string {
                if (null !== $value) {
                    return \rtrim($value, '_');
                }

                /** @var class-string $controllerClass */
                $controllerClass = $options['controller_class'];
                if (null !== ($attr = \current(new \ReflectionClass($controllerClass)->getAttributes(Route::class)) ?: null)) {
                    return \rtrim((string) $attr->newInstance()->name, '_');
                }

                /** @var string $class */
                $class = $options['class'];
                $class = \substr($class, \strrpos($class, '\\') + 1);
                $class = \strtolower((string) \preg_replace('/(?<!^)[A-Z]/', '_$0', $class));

                return 'cms_'.$class;
            });

        $resolver
            ->define('parent_route_param')
            ->default('parent')
            ->allowedTypes('string');

        $resolver
            ->define('route_params')
            ->default([])
            ->allowedTypes('array')
            ->normalize(static function (Options $options, array $value): array {
                /** @var object|null $parent */
                $parent = $options['parent'];
                /** @var string $parentRouteParam */
                $parentRouteParam = $options['parent_route_param'];

                return \array_replace($value, $parent ? [$parentRouteParam => $parent->getId()] : []); // @phpstan-ignore method.notFound
            });
    }

    private function configureView(OptionsResolver $resolver): void
    {
        $resolver
            ->define('view_prefix')
            ->allowedTypes('null', 'string')
            ->default(null)
            ->normalize(static fn (Options $options, ?string $value): ?string => null !== $value ? \rtrim($value, '/') : null);

        $resolver
            ->define('view_params')
            ->default([])
            ->allowedTypes('array')
            ->normalize(static function (Options $options, array $value): array {
                /** @var array<string, mixed> $defined */
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
            ->normalize(static function (Options $options, ?string $value): string {
                if (null !== $value) {
                    return $value;
                }

                /** @var string $class */
                $class = $options['class'];

                return \substr($class, \strrpos($class, '\\') + 1);
            });
    }

    private function configureActions(OptionsResolver $resolver): void
    {
        // configure actions with view
        $createDefaultActionOptions = static function (string $prefix, Options $options): array {
            /** @var string|null $viewPrefix */
            $viewPrefix = $options['view_prefix'];
            /** @var string $routePrefix */
            $routePrefix = $options['route_prefix'];
            /** @var array<string, mixed> $viewParams */
            $viewParams = $options['view_params'];
            /** @var array<string, mixed> $routeParams */
            $routeParams = $options['route_params'];

            return [
                'title' => null,
                'view' => null === $viewPrefix
                    ? '@ChamberOrchestraCms/crud/'.$prefix.'.html.twig'
                    : \sprintf('%s/%s.html.twig', $viewPrefix, $prefix),
                'view_params' => $viewParams,
                'route' => \sprintf('%s_%s', $routePrefix, $prefix),
                'route_params' => $routeParams,
            ];
        };

        $resolver->setOptions('index', function (OptionsResolver $resolver, Options $parentOptions) use ($createDefaultActionOptions): void {
            $resolver->setDefaults($createDefaultActionOptions('index', $parentOptions));

            $resolver
                ->define('fields')
                ->default([])
                ->allowedTypes('null', 'array')
                ->normalize(static function (Options $options, ?array $value): ?array {
                    if (null === $value) {
                        return null;
                    }

                    /** @var \Closure(array<int|string, mixed>): array<string, mixed> $prepareFormats */
                    $prepareFormats = static function (array $items): array {
                        $formatted = [];
                        foreach ($items as $key => $format) {
                            if (!\is_numeric($key)) {
                                $formatted[$key] = $format;
                                continue;
                            }
                            // suppose its transform
                            if (\is_callable($format)) {
                                $formatted['transform'][] = $format; // @phpstan-ignore offsetAccess.nonOffsetAccessible
                                continue;
                            }
                            \assert(\is_string($format));
                            $formatted[$format] = [];
                        }

                        return $formatted;
                    };

                    $formatted = [];
                    foreach ($value as $key => $item) {
                        if (!\is_numeric($key)) {
                            /** @var string $key */
                            $formats = \is_array($item) ? $item : [$item];
                            $formats = $prepareFormats($formats);
                            $formatted[$key] = $formats;
                            continue;
                        }
                        \assert(\is_string($item));
                        $formatted[$item] = [];
                    }

                    return $formatted;
                });

            $resolver
                ->define('actions')
                ->default(null)
                ->allowedTypes('null', 'callable')
                ->normalize(static function (Options $options, ?callable $value): ?callable {
                    if (null === $value) {
                        return null;
                    }

                    return static function (object $entity, ?object $parent = null) use ($value): NavigationInterface {
                        return new ClosureNavigation(
                            static function (MenuBuilder $builder) use ($value, $entity, $parent): void {
                                \call_user_func($value, $builder, $entity, $parent);
                            }
                        );
                    };
                });

            $resolver->setAllowedTypes('view_params', []);
            $resolver->setNormalizer('view_params', static fn (Options $options, array $value): array => \array_merge([
                'fields' => $options['fields'],
                'actions' => $options['actions'],
                'alias' => $options['alias'],
            ], $value));

            $resolver
                ->define('filter')
                ->default(null)
                ->allowedValues(static function (mixed $value): bool {
                    if (null === $value) {
                        return true;
                    }

                    return \is_string($value) && \is_subclass_of($value, FormTypeInterface::class);
                });

            $resolver
                ->define('filter_mapping')
                ->default([])
                ->allowedTypes('array');

            $resolver
                ->define('query_builder')
                ->default(null)
                ->allowedTypes(QueryBuilder::class, \Closure::class, 'null')
                ->normalize(function (Options $options, QueryBuilder|\Closure|null $value) use ($parentOptions): QueryBuilder {
                    if ($value instanceof QueryBuilder) {
                        return $value;
                    }

                    /** @var class-string $entityClass */
                    $entityClass = $parentOptions['class'];
                    $er = $this->em->getRepository($entityClass);
                    if ($value instanceof \Closure) {
                        /** @var QueryBuilder $qb */
                        $qb = \call_user_func($value, $er);

                        return $qb;
                    }

                    return $er->createQueryBuilder('r');
                });

            $resolver
                ->define('order_by')
                ->default([])
                ->allowedTypes('array')
                ->normalize(static function (Options $options, array $value): array {
                    /** @var array<string, mixed> $orderBy */
                    $orderBy = $options['orderBy'];

                    return \array_replace($orderBy, $value);
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
                ->normalize(static function (Options $options, ?array $value): array {
                    if (null === $value) {
                        return [];
                    }

                    /** @var array<string, mixed> $fields */
                    $fields = $options['fields'];
                    $defaults = \array_keys($fields);
                    /** @var \Closure(array<int|string, mixed>): array<string, mixed> $normalise */
                    $normalise = static function (array $values): array {
                        $normalised = [];
                        foreach ($values as $k => $v) {
                            if (\is_numeric($k)) {
                                /** @var string $v */
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
                            \in_array($k, $skip, true)
                            || ('!' === $k[0] && $k = \substr($k, 1))
                            || false === $v
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
                ->normalize(static function (Options $options, ?callable $value): ?callable {
                    /** @var bool $bulkOperation */
                    $bulkOperation = $options['bulk_operation'];

                    return $bulkOperation ? $value : null;
                });
        });

        /**
         * Define common actions.
         */
        $actions = ['create', 'update', 'view', 'meta', 'export'];
        foreach ($actions as $action) {
            $resolver
                ->define($action)
                ->default([])
                ->allowedTypes('null', 'array')
                ->normalize(static fn (Options $options, ?array $value): ?array => null !== $value ? \array_replace_recursive($createDefaultActionOptions($action, $options), $value) : null);
        }

        /**
         * Define special actions.
         */
        $actions = ['move', 'delete', 'bulk_delete', 'toggle', 'copy'];

        // configure actions without view
        $createDefaultActionOptions = static function (string $prefix, Options $options): array {
            /** @var string $routePrefix */
            $routePrefix = $options['route_prefix'];
            /** @var array<string, mixed> $routeParams */
            $routeParams = $options['route_params'];

            return [
                'route' => \sprintf('%s_%s', $routePrefix, $prefix),
                'route_params' => $routeParams,
            ];
        };

        foreach ($actions as $action) {
            $resolver
                ->define($action)
                ->default([])
                ->allowedTypes('null', 'array')
                ->normalize(static function (Options $options, ?array $value) use ($action, $createDefaultActionOptions): ?array {
                    if ('bulk_delete' === $action) {
                        /** @var array<string, mixed> $index */
                        $index = $options['index'];
                        if (!$index['bulk_operation']) {
                            return null;
                        }
                    }

                    return null !== $value ? \array_replace_recursive($createDefaultActionOptions($action, $options), $value) : null;
                });
        }

        // entries is opt-in: null by default so controllers without SupportsContentEntriesOperation
        // don't try to generate a link to a non-existent route. Set to [] to enable.
        $resolver
            ->define('entries')
            ->default(null)
            ->allowedTypes('null', 'array')
            ->normalize(static function (Options $options, ?array $value): ?array {
                if (null === $value) {
                    return null;
                }

                /** @var string $routePrefix */
                $routePrefix = $options['route_prefix'];
                /** @var array<string, mixed> $routeParams */
                $routeParams = $options['route_params'];

                return \array_replace_recursive([
                    'route' => \sprintf('%s_entries_index', $routePrefix),
                    'route_params' => $routeParams,
                ], $value);
            });
    }
}
