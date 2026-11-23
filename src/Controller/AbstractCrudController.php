<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Controller;

use ChamberOrchestra\Breadcrumbs\Breadcrumbs;
use ChamberOrchestra\CmsBundle\Configurator\CrudControllerConfigurator;
use ChamberOrchestra\CmsBundle\Configurator\CrudOptions;
use ChamberOrchestra\CmsBundle\EntityRepository\EntityRepositoryWrapper;
use ChamberOrchestra\CmsBundle\EntityRepository\Helper\FilterHelper;
use ChamberOrchestra\CmsBundle\Form\Dto\BulkOperationDto;
use ChamberOrchestra\CmsBundle\Form\Dto\BulkUpdateDto;
use ChamberOrchestra\CmsBundle\Form\Dto\DeleteDto;
use ChamberOrchestra\CmsBundle\Form\Dto\DtoInterface;
use ChamberOrchestra\CmsBundle\Form\Dto\MetaDto;
use ChamberOrchestra\CmsBundle\Form\Dto\MetaTranslatableDto;
use ChamberOrchestra\CmsBundle\Form\Type\BulkOperationForm;
use ChamberOrchestra\CmsBundle\Form\Type\BulkUpdateForm;
use ChamberOrchestra\CmsBundle\Form\Type\DeleteType;
use ChamberOrchestra\CmsBundle\Form\Type\MetaTranslatableType;
use ChamberOrchestra\CmsBundle\Form\Type\MetaType;
use ChamberOrchestra\CmsBundle\Generator\CsvGeneratorInterface;
use ChamberOrchestra\CmsBundle\Processor\CrudProcessor;
use ChamberOrchestra\DoctrineExtensionsBundle\Contracts\Entity\ToggleInterface;
use ChamberOrchestra\DoctrineExtensionsBundle\Filter\SoftDeleteFilter;
use ChamberOrchestra\MenuBundle\Menu\MenuBuilder;
use ChamberOrchestra\MenuBundle\Navigation\ClosureNavigation;
use ChamberOrchestra\MenuBundle\Navigation\NavigationInterface;
use ChamberOrchestra\PaginationBundle\Pagination\PaginationInterface;
use ChamberOrchestra\ViewBundle\View\ViewInterface;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Types\GuidType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\TextType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @phpstan-import-type Options from CrudOptions
 */
#[Autoconfigure(tags: [AbstractCrudController::CRUD_CONTROLLER_TAG], lazy: true, public: false)]
abstract class AbstractCrudController extends AbstractController implements CrudControllerInterface
{
    use OriginPathTrait;
    /** The icons of the items of the section's menu (the label of an item with an icon is the icon and the text). */
    private const array NAV_ICONS = ['index' => 'fa-list', 'create' => 'fa-plus', 'update' => 'fa-pen', 'meta' => 'fa-tags', 'export' => 'fa-download'];

    public const CRUD_CONTROLLER_TAG = 'cms.crud_controller';

    /** @var array<string, mixed> */
    protected array $options = [];
    protected TranslatorInterface $translator;
    protected CsvGeneratorInterface $csvGenerator;
    protected EntityManagerInterface $em;
    protected CrudControllerConfigurator $configurator;

    /** @var ServiceLocator<mixed> */
    protected ServiceLocator $crudControllersLocator;

    #[Required]
    public function withEntityManager(EntityManagerInterface $em): void
    {
        $this->em = $em;
        $this->configurator->withEntityManager($em);
    }

    #[Required]
    public function withTranslator(TranslatorInterface $translator): void
    {
        $this->translator = $translator;
    }

    /**
     * @param ServiceLocator<mixed> $controllersLocator
     */
    #[Required]
    public function withCmsControllers(#[AutowireLocator(self::CRUD_CONTROLLER_TAG)] ServiceLocator $controllersLocator): void
    {
        $this->crudControllersLocator = $controllersLocator;
        $this->configurator->withControllersLocator($controllersLocator);
    }

    #[Required]
    public function withCsvGenerator(CsvGeneratorInterface $csvGenerator): void
    {
        $this->csvGenerator = $csvGenerator;
    }

    /**
     * @param Options $options
     */
    public function __construct(protected readonly CrudProcessor $processor, array $options = [])
    {
        $this->options = $options;
        $this->configurator = new CrudControllerConfigurator();
    }

    private function getRequest(): Request
    {
        /** @var RequestStack $requestStack */
        $requestStack = $this->container->get('request_stack');
        $request = $requestStack->getMainRequest();

        if (!$request instanceof Request) {
            throw new \LogicException('A Request must be available to use the CrudController.');
        }

        return $request;
    }

    /**
     * @param iterable<object>     $entities
     * @param array<string, mixed> $options
     * @param array<string, mixed> $parameters
     */
    protected function doIndex(iterable $entities, ?PaginationInterface $pagination = null, array $options = [], array $parameters = []): Response
    {
        $options = $this->resolve($options);

        /** @var array<string, mixed> $index */
        $index = $options['index'];
        /** @var array<string, mixed>|null $delete */
        $delete = $options['delete'];

        $deleteForm = null;
        if (null !== $delete) {
            $deleteForm = $this->createDeleteForm($options);
        }

        // Rows can be selected only when there is something to do with them (a bulk delete, or the actions of `index.bulk_nav`): no checkboxes, no bulk bar otherwise.
        $hasBulkAction = null !== ($options['bulk_delete'] ?? null) || null !== ($options['bulk_update'] ?? null) || \is_callable($index['bulk_nav'] ?? null);

        $bulkOperationForm = null;
        $bulkUpdateForm = null;
        if (($index['bulk_operation'] ?? null) && $hasBulkAction) {
            $bulkOperationForm = $this->createBulkOperationForm($options);
            if (null !== ($options['bulk_update'] ?? null)) {
                $bulkUpdateForm = $this->createBulkUpdateForm($options);
            }
        }

        if (\class_exists(SoftDeleteFilter::class)) {
            $this->em->getFilters()->enable(SoftDeleteFilter::class);
        }

        /** @var string $indexView */
        $indexView = $index['view'];
        /** @var array<string, mixed> $indexViewParams */
        $indexViewParams = $index['view_params'];
        /** @var array<string, mixed> $orderByMapping */
        $orderByMapping = $index['order_by_mapping'];

        return $this->render($indexView, \array_merge_recursive([
            'parent_entity_label' => $this->getParentEntityLabel(null, $options),
            'parent_entity_title' => $this->getParentEntityTitle($options),
            'entities' => $entities,
            'pagination' => $pagination?->createView(),
            'nav' => $this->createNavigation(null, $options),
            'bulk_nav' => $hasBulkAction ? $this->createBulkNavigation($options) : null,
            'breadcrumbs' => $this->createBreadcrumbs('index', null, $options),
            'delete_form' => $deleteForm?->createView(),
            'toggle' => $this->getRouteOptions($options, 'toggle'),
            'copy' => $this->getRouteOptions($options, 'copy'),
            'update' => $this->getRouteOptions($options, 'update'),
            'move' => $this->getRouteOptions($options, 'move'),
            'export' => $this->getRouteOptions($options, 'export'),
            'entries' => $this->getRouteOptions($options, 'entries'),
            'bulk_operation_form' => $bulkOperationForm?->createView(),
            'bulk_update_form' => $bulkUpdateForm?->createView(),
            'bulk_update' => $this->getRouteOptions($options, 'bulk_update'),
            'bulk_delete' => $this->getRouteOptions($options, 'bulk_delete'),
            'primary' => $this->primaryLink($options),
            'order_by' => \array_keys($orderByMapping),
        ], $indexViewParams, $parameters));
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $parameters
     */
    protected function list(array $options = [], array $parameters = []): Response
    {
        $resolved = $this->resolve($options);
        if (\class_exists(SoftDeleteFilter::class)) {
            $this->em->getFilters()->enable(SoftDeleteFilter::class);
        }

        /** @var array<string, mixed> $index */
        $index = $resolved['index'];

        $request = $this->getRequest();
        /** @var array<string, mixed> $filter */
        $filter = $resolved['parent'] ? ['parent' => $resolved['parent']] : [];
        $form = null;
        if ($index['filter']) {
            /** @var string $filterClass */
            $filterClass = $index['filter'];
            $form = $this->createForm($filterClass, null, [
                'translation_domain' => $resolved['translation_domain'],
                'label_format' => $resolved['label_format'],
            ]);
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                /** @var array<string, mixed> $formData */
                $formData = $form->getData();
                $filter += $formData;
            }
        }

        /** @var array<string, mixed> $orderBy */
        $orderBy = $index['order_by'];
        if ($sortBy = $request->query->get('sort')) {
            $direction = \strtoupper($request->query->getString('order', 'DESC'));
            $orderBy = [$sortBy => \in_array($direction, ['ASC', 'DESC'], true) ? $direction : 'DESC'];
        }

        $baseQuery = $index['query_builder'];
        \assert($baseQuery instanceof QueryBuilder);
        $queryBuilder = clone $baseQuery; // the options are resolved once per controller instance: a request must not add its order and its filters to the query of the next one
        /** @var array<string, mixed> $filterMapping */
        $filterMapping = $index['filter_mapping'];
        /** @var array<string, mixed> $orderByMapping */
        $orderByMapping = $index['order_by_mapping'];
        /** @var class-string $entityClass */
        $entityClass = $resolved['class'];

        // Quick search of a column (the magnifier in its heading): `?col[field]=text`, only for the columns of the list that are text or whole numbers.
        $columns = $this->searchableColumns($entityClass, (array) $index['fields']);
        $rawCol = $request->query->all()['col'] ?? [];
        $searched = $this->applyColumnSearch($queryBuilder, \is_array($rawCol) ? $rawCol : [], $columns);
        $alias = $queryBuilder->getRootAliases()[0] ?? 'r';
        // "Show selected" of the bulk plate: the browser POSTs the ids (`ids`, comma separated, 5000 at most) and goes to `?selected=1`, which shows only those rows; they wait in the session
        $selectedKey = 'cms_selected_'.\md5($entityClass);
        if ($request->isMethod('POST')) {
            $ids = $this->cleanIds($request->request->getString('ids'));
            $request->getSession()->set($selectedKey, $ids);

            return $this->redirect($request->getPathInfo().([] === $ids ? '' : '?selected=1'), 303);
        }
        if ($request->query->has('selected')) {
            $stored = $request->getSession()->get($selectedKey, []);
            $onlyIds = \is_array($stored) ? \array_values(\array_filter($stored, \is_string(...))) : [];
            if ([] !== $onlyIds) {
                $queryBuilder->andWhere(\sprintf('%s.id IN (:only_ids)', $alias))->setParameter('only_ids', $onlyIds);
            }
        }
        // `?format=ids`: the ids of every row the list shows over all its pages (the third state of the checkbox of the heading: "select all that is filtered"), 5000 at most
        $canSelect = null !== ($resolved['bulk_delete'] ?? null) || null !== ($resolved['bulk_update'] ?? null) || \is_callable($index['bulk_nav'] ?? null);
        if ($canSelect && 'ids' === $request->query->getString('format')) {
            $idsQuery = clone $queryBuilder;
            new FilterHelper($idsQuery)($filter, $filterMapping);
            $idsQuery->select(\sprintf('%s.id AS id', $alias))->resetDQLPart('orderBy')->setMaxResults(5000);
            $found = \array_map(static fn (mixed $id): string => \is_scalar($id) ? (string) $id : '', \array_column($idsQuery->getQuery()->getScalarResult(), 'id'));

            return new JsonResponse(['ids' => $found, 'truncated' => \count($found) >= 5000]);
        }
        $rawFilter = $request->query->all()['filter'] ?? [];
        $filtersOn = \count(\array_filter(\is_array($rawFilter) ? $rawFilter : [], static fn (mixed $v): bool => (\is_string($v) || \is_array($v)) && '' !== $v && [] !== $v)) + \count($searched);

        $entities = new EntityRepositoryWrapper($this->em->getRepository($entityClass))->filterBy(
            $queryBuilder,
            $filter,
            $filterMapping,
            $orderBy,
            $orderByMapping,
            $pagination = $this->getPagination(['limit' => $this->pageSize($request)]),
        );

        return $this->doIndex(
            $entities,
            $pagination,
            $options,
            $parameters + [
                'filter_form' => $form?->createView(),
                'searchable' => \array_keys($columns),
                'col' => $searched,
                'filters_on' => $filtersOn,
            ]
        );
    }

    /**
     * @return list<string>
     */
    private function cleanIds(string $raw): array
    {
        return \array_slice(\array_values(\array_filter(\array_map('trim', \explode(',', $raw)), static fn (string $id): bool => 1 === \preg_match('/^[0-9A-Za-z-]{1,40}$/', $id))), 0, 5000);
    }

    /**
     * The query of a list with its filter and its column search applied (no order, no pagination): the address query of the list (`filter[...]`, `col[...]`) is what the
     * browser sends when it chose everything and what the export of a filtered list carries.
     *
     * @param array<string, mixed> $resolved
     */
    protected function filteredQuery(array $resolved, ?string $query): QueryBuilder
    {
        if (\class_exists(SoftDeleteFilter::class)) {
            $this->em->getFilters()->enable(SoftDeleteFilter::class);
        }
        /** @var array<string, mixed> $index */
        $index = $resolved['index'];
        \parse_str((string) $query, $params);
        /** @var array<string, mixed> $filter */
        $filter = $resolved['parent'] ? ['parent' => $resolved['parent']] : [];
        if ($index['filter'] && \is_array($params['filter'] ?? null)) {
            /** @var string $filterClass */
            $filterClass = $index['filter'];
            $form = $this->createForm($filterClass, null, [
                'translation_domain' => $resolved['translation_domain'],
                'label_format' => $resolved['label_format'],
            ]);
            $form->submit($params['filter']);
            if ($form->isValid()) {
                /** @var array<string, mixed> $formData */
                $formData = $form->getData();
                $filter += $formData;
            }
        }
        $baseQuery = $index['query_builder'];
        \assert($baseQuery instanceof QueryBuilder);
        $queryBuilder = clone $baseQuery;
        /** @var class-string $entityClass */
        $entityClass = $resolved['class'];
        $this->applyColumnSearch($queryBuilder, \is_array($params['col'] ?? null) ? $params['col'] : [], $this->searchableColumns($entityClass, (array) $index['fields']));
        /** @var array<string, mixed> $filterMapping */
        $filterMapping = $index['filter_mapping'];
        new FilterHelper($queryBuilder)($filter, $filterMapping);

        return $queryBuilder;
    }

    /**
     * The ids of every row of a list's filter (over all its pages).
     *
     * @param array<string, mixed> $resolved
     *
     * @return list<string>
     */
    private function idsOfFilter(array $resolved, ?string $query): array
    {
        $queryBuilder = $this->filteredQuery($resolved, $query);
        $alias = $queryBuilder->getRootAliases()[0] ?? 'r';
        $queryBuilder->select(\sprintf('%s.id AS id', $alias))->resetDQLPart('orderBy')->setMaxResults(100000);

        return \array_map(static fn (mixed $id): string => \is_scalar($id) ? (string) $id : '', \array_column($queryBuilder->getQuery()->getScalarResult(), 'id'));
    }

    /**
     * "All" is the rows of the list's filter (the browser sends its query), never the whole table: the ids are found here and the operation goes on as a chosen set.
     *
     * @param array<string, mixed> $options
     */
    protected function resolveAll(BulkOperationDto|BulkUpdateDto $dto, array $options): void
    {
        if (!$dto->all) {
            return;
        }
        $dto->entities = \implode(',', $this->idsOfFilter($options, $dto->query));
        $dto->all = false;
    }

    /**
     * The columns of the list a quick search may be put on: a text column, a whole-number column, or such a column of a to-one association (`user.email`).
     *
     * @param class-string            $entityClass
     * @param array<array-key, mixed> $fields      the list's `index.fields` (a field with `'search' => false` gets no magnifier)
     *
     * @return array<string, array{join: ?string, field: string, numeric: bool}>
     */
    private function searchableColumns(string $entityClass, array $fields): array
    {
        $meta = $this->em->getClassMetadata($entityClass);
        $columns = [];
        foreach ($fields as $key => $formats) {
            $name = \is_string($key) ? $key : (\is_string($formats) ? $formats : '');
            if ('' === $name || (\is_array($formats) && false === ($formats['search'] ?? true))) {
                continue;
            }
            $join = null;
            $field = $name;
            $target = $meta;
            if (\str_contains($name, '.')) {
                [$join, $field] = \explode('.', $name, 2);
                if (!$meta->hasAssociation($join) || !$meta->isSingleValuedAssociation($join) || \str_contains($field, '.')) {
                    continue;
                }
                $target = $this->em->getClassMetadata($meta->getAssociationTargetClass($join));
            }
            if (!$target->hasField($field)) {
                continue;
            }
            $mapping = $target->getFieldMapping($field);
            if (null !== ($mapping->enumType ?? null)) {
                continue;
            }
            $type = Type::getType($mapping->type);
            if (\in_array($mapping->type, [Types::SMALLINT, Types::INTEGER, Types::BIGINT], true)) {
                $columns[$name] = ['join' => $join, 'field' => $field, 'numeric' => true];
            } elseif (($type instanceof StringType || $type instanceof TextType) && !$type instanceof GuidType) {
                $columns[$name] = ['join' => $join, 'field' => $field, 'numeric' => false];
            }
        }

        return $columns;
    }

    /**
     * @param array<array-key, mixed>                                           $values  the request's `col`
     * @param array<string, array{join: ?string, field: string, numeric: bool}> $columns
     *
     * @return array<string, string> what is searched, by column (for the page to show it)
     */
    private function applyColumnSearch(QueryBuilder $qb, array $values, array $columns): array
    {
        $applied = [];
        $root = \current($qb->getRootAliases()) ?: 'r';
        $i = 0;
        foreach ($values as $name => $value) {
            if (!\is_string($name) || !\is_string($value) || !isset($columns[$name]) || '' === ($value = \trim($value))) {
                continue;
            }
            $column = $columns[$name];
            $alias = $root;
            if (null !== $column['join']) {
                $alias = 'col_'.$i;
                $qb->leftJoin($root.'.'.$column['join'], $alias);
            }
            $param = 'col_value_'.$i++;
            if ($column['numeric']) {
                if (!\ctype_digit($value) || \strlen($value) > 9) {
                    $qb->andWhere('1 = 0'); // text in a number column finds nothing
                } else {
                    $qb->andWhere($alias.'.'.$column['field'].' = :'.$param)->setParameter($param, (int) $value);
                }
            } else {
                $qb->andWhere('LOWER('.$alias.'.'.$column['field'].') LIKE :'.$param)->setParameter($param, '%'.\addcslashes(\mb_strtolower($value), '%_\\').'%');
            }
            $applied[$name] = $value;
        }

        return $applied;
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function doCreate(array $options = []): Response|ViewInterface
    {
        $dto = $this->factoryDataClass($options = $this->resolve($options));
        $form = $this->factoryForm($dto, $options);

        /** @var array<string, mixed> $update */
        $update = $options['update'];
        /** @var array<string, mixed> $indexOpts */
        $indexOpts = $options['index'];
        /** @var array<string, mixed> $create */
        $create = $options['create'];

        $request = $this->getRequest();
        $form->handleRequest($request);

        if ($request->query->getBoolean('quick')) {
            return $this->doQuickCreate($form, $options);
        }

        if ($form->isSubmitted()) {
            return $this->createSubmittedFormResponse($form, function (DtoInterface $dto) use ($update, $indexOpts): Response|ViewInterface {
                $entity = $this->processor->create($dto);
                $this->addFlash('success', 'crud.flash.created');

                if (isset($update['route'])) {
                    /** @var string $updateRoute */
                    $updateRoute = $update['route'];
                    /** @var array<string, mixed> $updateRouteParams */
                    $updateRouteParams = $update['route_params'];

                    return $this->createRedirectToRouteResponse($updateRoute, \array_merge([
                        'id' => $entity->getId(), // @phpstan-ignore method.notFound
                    ], $updateRouteParams));
                }

                /** @var string $indexRoute */
                $indexRoute = $indexOpts['route'];
                /** @var array<string, mixed> $indexRouteParams */
                $indexRouteParams = $indexOpts['route_params'];

                return $this->createRedirectToRouteResponse($indexRoute, $indexRouteParams);
            });
        }

        /** @var string $createView */
        $createView = $create['view'];
        /** @var array<string, mixed> $createViewParams */
        $createViewParams = $create['view_params'];

        return $this->render($createView, \array_merge([
            'parent_entity_label' => $this->getParentEntityLabel(null, $options),
            'parent_entity_title' => $this->getParentEntityTitle($options),
            'form' => $form->createView(),
            'nav' => $this->createNavigation(null, $options),
            'breadcrumbs' => $this->createBreadcrumbs('create', null, $options),
        ], $createViewParams));
    }

    /** The numbers of rows a list can show on a page (`?limit=`); the first one is the default. */
    public const array PAGE_SIZES = [32, 64, 128];

    /**
     * The size of the page of a list: `?limit=` when it is one of PAGE_SIZES, otherwise the default.
     */
    protected function pageSize(Request $request): int
    {
        $limit = $request->query->getInt('limit');

        return \in_array($limit, self::PAGE_SIZES, true) ? $limit : self::PAGE_SIZES[0];
    }

    /**
     * Where the first column of a row leads: the page of the entity ("view") when the controller has one, otherwise its edit form ("update"); null when neither route exists.
     *
     * @param array<string, mixed> $options
     *
     * @return array{route: string, route_params: array<string, mixed>}|null
     */
    protected function primaryLink(array $options): ?array
    {
        $router = $this->container->get('router');
        if (!$router instanceof \Symfony\Component\Routing\RouterInterface) {
            return null;
        }
        foreach (['view', 'update'] as $action) {
            $route = $this->getRouteOptions($options, $action);
            if (null !== $route && \is_string($route['route'] ?? null) && null !== $router->getRouteCollection()->get($route['route'])) {
                /** @var array<string, mixed> $params */
                $params = \is_array($route['route_params'] ?? null) ? $route['route_params'] : [];

                return ['route' => $route['route'], 'route_params' => $params];
            }
        }

        return null;
    }

    /**
     * The creation the select of another form asks for (`?quick=1`, see `QuickCreateExtension`): the form without the page around it; a valid one is saved and
     * answered with `{id, label}` (what the select adds), an invalid one is shown again with its errors (422).
     *
     * @param FormInterface<mixed> $form
     * @param array<string, mixed> $options
     */
    protected function doQuickCreate(FormInterface $form, array $options): Response
    {
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            if (!$data instanceof DtoInterface) {
                throw new \LogicException('The quick create form must give a DTO.');
            }
            $entity = $this->processor->create($data);
            $rawId = $entity->getId(); // @phpstan-ignore method.notFound
            $id = \is_scalar($rawId) || $rawId instanceof \Stringable ? (string) $rawId : '';

            return new JsonResponse(['id' => $id, 'label' => $this->getEntityLabel($entity, $options) ?? $id]);
        }

        /** @var array<string, mixed> $create */
        $create = $options['create'];
        /** @var array<string, mixed> $viewParams */
        $viewParams = $create['view_params'];

        return $this->render(
            '@ChamberOrchestraCms/crud/create.quick.html.twig',
            \array_merge($viewParams, ['form' => $form->createView()]),
            new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK),
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function doView(object $entity, string $view, array $options = []): Response
    {
        $options = $this->resolve($options);

        /** @var array<string, mixed> $update */
        $update = $options['update'];
        /** @var array<string, mixed> $updateViewParams */
        $updateViewParams = $update['view_params'];

        return $this->render($view, \array_merge([
            'parent_entity_label' => $this->getParentEntityLabel(null, $options),
            'parent_entity_title' => $this->getParentEntityTitle($options),
            'entity' => $entity,
            'nav' => $this->createNavigation($entity, $options),
            'breadcrumbs' => $this->createBreadcrumbs('view', $entity, $options),
        ], $updateViewParams));
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $options
     */
    protected function doRender(string $view, array $params = [], array $options = []): Response
    {
        $options = $this->resolve($options);

        /** @var array<string, mixed> $viewParams */
        $viewParams = $options['view_params'];

        return $this->render($view, \array_merge([
            'parent_entity_label' => $this->getParentEntityLabel(null, $options),
            'parent_entity_title' => $this->getParentEntityTitle($options),
            'nav' => $this->createNavigation(null, $options),
            'breadcrumbs' => $this->createBreadcrumbs('view', null, $options),
        ], $viewParams, $params));
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function doUpdate(object $entity, array $options = []): Response|ViewInterface
    {
        $options = $this->resolve($options);
        $dto = $this->factoryDataClass($options);
        $this->processor->sync($entity, $dto);
        $form = $this->factoryForm($dto, $options);

        /** @var array<string, mixed> $update */
        $update = $options['update'];

        $request = $this->getRequest();
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            return $this->createSubmittedFormResponse($form, function () use ($entity, $dto, $update): Response|ViewInterface {
                $this->processor->update($dto, $entity);
                $this->addFlash('success', 'crud.flash.updated');

                /** @var string $updateRoute */
                $updateRoute = $update['route'];
                /** @var array<string, mixed> $updateRouteParams */
                $updateRouteParams = $update['route_params'];

                return $this->createRedirectToRouteResponse($updateRoute, \array_merge([
                    'id' => $entity->getId(), // @phpstan-ignore method.notFound
                ], $updateRouteParams));
            });
        }

        $view = 'view';
        if ($request->isXmlHttpRequest()) {
            $view = 'fragment';
        }

        /** @var string $updateView */
        $updateView = $update[$view];
        /** @var array<string, mixed> $updateViewParams */
        $updateViewParams = $update['view_params'];

        return $this->render($updateView, \array_merge([
            'parent_entity_label' => $this->getParentEntityLabel(null, $options),
            'parent_entity_title' => $this->getParentEntityTitle($options),
            'form' => $form->createView(),
            'entity' => $entity,
            'entity_label' => $this->getEntityLabel($entity, $options),
            'nav' => $this->createNavigation($entity, $options),
            'breadcrumbs' => $this->createBreadcrumbs('update', $entity, $options),
        ], $updateViewParams));
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function doUpdateMeta(object $entity, array $options = []): Response|ViewInterface
    {
        $options = $this->resolve($options);

        /** @var string $entityClass */
        $entityClass = $options['class'];
        $dto = $entity instanceof TranslatableInterface
            ? new MetaTranslatableDto()
            : new MetaDto($entityClass);

        $this->processor->sync($entity, $dto);

        /** @var array<string, mixed> $meta */
        $meta = $options['meta'];

        $request = $this->getRequest();
        $form = $this->createForm(
            $entity instanceof TranslatableInterface ? MetaTranslatableType::class : MetaType::class,
            $dto
        );
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            return $this->createSubmittedFormResponse($form, function () use ($entity, $dto, $meta): Response|ViewInterface {
                $this->processor->update($dto, $entity);
                $this->addFlash('success', 'crud.flash.updated');

                /** @var string $metaRoute */
                $metaRoute = $meta['route'];
                /** @var array<string, mixed> $metaRouteParams */
                $metaRouteParams = $meta['route_params'];

                return $this->createRedirectToRouteResponse($metaRoute, \array_merge([
                    'id' => $entity->getId(), // @phpstan-ignore method.notFound
                ], $metaRouteParams));
            });
        }

        /** @var string $metaView */
        $metaView = $meta['view'];
        /** @var array<string, mixed> $metaViewParams */
        $metaViewParams = $meta['view_params'];

        return $this->render($metaView, \array_merge([
            'parent_entity_label' => $this->getParentEntityLabel(null, $options),
            'parent_entity_title' => $this->getParentEntityTitle($options),
            'entity_label' => $this->getEntityLabel($entity, $options),
            'form' => $form->createView(),
            'nav' => $this->createNavigation($entity, $options),
            'breadcrumbs' => $this->createBreadcrumbs('meta', $entity, $options),
        ], $metaViewParams));
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function doDelete(array $options = []): Response|ViewInterface
    {
        $options = $this->resolve($options);

        /** @var string $entityClass */
        $entityClass = $options['class'];
        $form = $this->createForm(DeleteType::class, new DeleteDto($entityClass), [
            'class' => $entityClass,
        ]);

        /** @var array<string, mixed> $index */
        $index = $options['index'];
        /** @var string $indexRoute */
        $indexRoute = $index['route'];
        /** @var array<string, mixed> $indexRouteParams */
        $indexRouteParams = $index['route_params'];

        return $this->handleFormCall($form, function (DeleteDto $dto) use ($indexRoute, $indexRouteParams): Response|ViewInterface {
            try {
                \assert(null !== $dto->entity);
                $this->processor->delete($dto, $dto->entity);
            } catch (ForeignKeyConstraintViolationException $e) {
                return $this->createExceptionResponse();
            }

            $this->addFlash('success', 'crud.flash.deleted');

            return $this->createRedirectResponse($this->getBackPath($indexRoute, $indexRouteParams));
        });
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $formOptions
     */
    protected function createBulkUpdateForm(array $options, array $formOptions = []): FormInterface
    {
        /** @var array<string, mixed> $bulk */
        $bulk = $options['bulk_update'];

        return $this->createForm(BulkUpdateForm::class, new BulkUpdateDto(), $formOptions + ['values_type' => $bulk['form_class']]);
    }

    /**
     * Gives the selected rows the values the form filled (what is left empty is not changed).
     *
     * @param array<string, mixed> $options
     */
    protected function doBulkUpdate(array $options = []): Response|ViewInterface
    {
        $form = $this->createBulkUpdateForm($options = $this->resolve($options));

        /** @var array<string, mixed> $index */
        $index = $options['index'];
        /** @var class-string $entityClass */
        $entityClass = $options['class'];
        /** @var string $indexRoute */
        $indexRoute = $index['route'];
        /** @var array<string, mixed> $indexRouteParams */
        $indexRouteParams = $index['route_params'];

        return $this->handleFormCall($form, function (BulkUpdateDto $dto) use ($indexRoute, $indexRouteParams, $entityClass, $options): Response|ViewInterface {
            $this->resolveAll($dto, $options);
            $values = \array_filter($dto->values, static fn (mixed $v): bool => null !== $v && '' !== $v);
            if ([] === $values) {
                $this->addFlash('danger', 'crud.flash.nothing_to_update');

                return $this->redirectBack($indexRoute, $indexRouteParams);
            }
            $this->processor->bulkUpdate($dto, $entityClass, $values);
            $this->addFlash('success', 'crud.flash.selected_updated');

            return $this->redirectBack($indexRoute, $indexRouteParams);
        });
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function doBulkDelete(array $options = []): Response|ViewInterface
    {
        $form = $this->createBulkOperationForm($options = $this->resolve($options), [
            'method' => 'DELETE',
        ]);

        /** @var array<string, mixed> $index */
        $index = $options['index'];
        /** @var class-string $entityClass */
        $entityClass = $options['class'];
        /** @var string $indexRoute */
        $indexRoute = $index['route'];
        /** @var array<string, mixed> $indexRouteParams */
        $indexRouteParams = $index['route_params'];

        return $this->handleFormCall($form, function (BulkOperationDto $dto) use ($indexRoute, $indexRouteParams, $entityClass, $options): Response|ViewInterface {
            $all = (bool) $dto->all;
            $this->resolveAll($dto, $options);
            try {
                $this->processor->bulkDelete($dto, $entityClass);
            } catch (ForeignKeyConstraintViolationException $e) {
                return $this->createExceptionResponse();
            }

            $this->addFlash('success', $all ? 'crud.flash.all_deleted' : 'crud.flash.selected_deleted');

            return $this->redirectBack($indexRoute, $indexRouteParams);
        });
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function doToggle(ToggleInterface $entity, array $options = []): Response|ViewInterface
    {
        $options = $this->resolve($options);
        $this->processor->toggle($entity);
        $this->addFlash('success', $entity->isEnabled() ? 'crud.flash.enabled' : 'crud.flash.disabled');

        /** @var array<string, mixed> $index */
        $index = $options['index'];
        /** @var string $indexRoute */
        $indexRoute = $index['route'];
        /** @var array<string, mixed> $indexRouteParams */
        $indexRouteParams = $index['route_params'];

        return $this->redirectBack($indexRoute, $indexRouteParams);
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function doExport(array $options = []): Response|ViewInterface
    {
        $options = $this->resolve($options);

        /** @var array<string, mixed> $export */
        $export = $options['export'];
        /** @var string $exportTitle */
        $exportTitle = $export['title'];
        /** @var array<string, mixed> $index */
        $index = $options['index'];

        // the rows of the list as it is filtered now (the address query of the page), the columns of the list: a CSV a spreadsheet opens (UTF-8 with a mark, `;`)
        $queryBuilder = $this->filteredQuery($options, $this->getRequest()->getQueryString());
        $alias = $queryBuilder->getRootAliases()[0] ?? 'r';
        $columns = [];
        foreach ((array) $index['fields'] as $key => $formats) {
            $name = \is_string($key) ? $key : (\is_string($formats) ? $formats : '');
            if ('' !== $name && !\str_contains($name, '.')) {
                $columns[] = $name;
            }
        }
        $queryBuilder->select(\array_map(static fn (string $c): string => \sprintf('%s.%s AS %s', $alias, $c, $c), $columns))->setMaxResults(200000);
        $queryBuilder->resetDQLPart('orderBy')->orderBy($alias.'.'.($columns[0] ?? 'id'));
        $labelFormat = \is_string($options['label_format'] ?? null) ? $options['label_format'] : '%name%';
        $domain = \is_string($options['translation_domain'] ?? null) ? $options['translation_domain'] : 'cms';
        $headers = \array_map(fn (string $c): string => $this->translator->trans(\str_replace('%name%', $c, $labelFormat), [], $domain), $columns);

        $response = new StreamedResponse(static function () use ($queryBuilder, $columns, $headers): void {
            $out = \fopen('php://output', 'w');
            if (false === $out) {
                return;
            }
            \fwrite($out, "\xEF\xBB\xBF");
            \fputcsv($out, $headers, ';', '"', '\\');
            foreach ($queryBuilder->getQuery()->toIterable([], \Doctrine\ORM\Query::HYDRATE_SCALAR) as $row) {
                $line = [];
                foreach ($columns as $column) {
                    $value = \is_array($row) ? ($row[$column] ?? null) : null;
                    $line[] = match (true) {
                        $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
                        $value instanceof \BackedEnum => $value->value,
                        \is_bool($value) => $value ? '1' : '0',
                        \is_scalar($value) => $value,
                        default => '',
                    };
                }
                \fputcsv($out, $line, ';', '"', '\\');
            }
            \fclose($out);
        });
        $fileName = \implode('-', \array_filter([$this->translator->trans($exportTitle, [], 'cms'), \date('d-m-Y')])).'.csv';
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $fileName, 'export.csv'));

        return $response;
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function createDeleteForm(array $options): FormInterface
    {
        /** @var string $entityClass */
        $entityClass = $options['class'];
        /** @var array<string, mixed> $delete */
        $delete = $options['delete'];
        /** @var string $deleteRoute */
        $deleteRoute = $delete['route'];
        /** @var array<string, mixed> $deleteRouteParams */
        $deleteRouteParams = $delete['route_params'];

        return $this->createForm(DeleteType::class, new DeleteDto($entityClass), [
            'class' => $entityClass,
            'action' => $this->generateUrl($deleteRoute, $deleteRouteParams),
        ]);
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $formOptions
     */
    protected function createBulkOperationForm(array $options, array $formOptions = []): FormInterface
    {
        return $this->createForm(BulkOperationForm::class, new BulkOperationDto(), $formOptions);
    }

    protected function doMove(object $entity, string $direction): Response|ViewInterface
    {
        $this->processor->move($entity, $direction);

        return $this->createSuccessResponse();
    }

    /**
     * @param array<string, mixed> $options
     */
    public function createBreadcrumbs(string $action, ?object $entity = null, array $options = []): ?Breadcrumbs
    {
        $callable = $options['breadcrumbs'];
        if (false === $callable) {
            return null;
        }

        $breadcrumbs = new Breadcrumbs();
        if (null !== ($controller = $options['parent_controller'])) {
            /** @var AbstractCrudController $controller */
            $breadcrumbs = $controller->createBreadcrumbs('update', $this->getForceParent($entity, $options), $controller->resolve());
        }

        if (null !== $callable && \is_callable($callable)) {
            \call_user_func($callable, $breadcrumbs, $options);
        }

        $add = static function (object $crumbs, string $prefix, ?object $entity = null, ?string $label = null) use ($options): void {
            /** @var array<string, mixed>|null $prefixOptions */
            $prefixOptions = $options[$prefix] ?? null;
            if (!isset($prefixOptions['route'])) {
                $crumbs->addCrumb($label); // @phpstan-ignore method.notFound

                return;
            }

            /** @var array<string, mixed> $params */
            $params = $prefixOptions['route_params'];
            if (null !== $entity) {
                $params['id'] = $entity->getId(); // @phpstan-ignore method.notFound
            }

            $crumbs->addCrumb($label, $prefixOptions['route'], $params); // @phpstan-ignore method.notFound
        };

        /** @var string $title */
        $title = $options['title'];
        $label = fn (string $prefix, ?object $entity = null): string => $this->translator->trans((string) ($options[$prefix]['title']['breadcrumbs'] ?? \sprintf('common.crumbs.%s', $prefix)), [ // @phpstan-ignore offsetAccess.nonOffsetAccessible, offsetAccess.nonOffsetAccessible, cast.string
            'type' => $this->translator->trans($title, [], 'cms'),
            'label' => $entity ? $this->getEntityLabel($entity, $options) : null,
        ], 'cms');

        \assert(null !== $breadcrumbs);
        $add($breadcrumbs, 'index', null, $label('index'));

        switch ($action) {
            case 'index':
                break;
            case 'create':
            case 'update':
            case 'view':
                $add($breadcrumbs, $action, $entity, $label($action, $entity));
                break;
            case 'meta':
                $add($breadcrumbs, $sub = 'update', $entity, $label($sub, $entity));
                $add($breadcrumbs, $action, $entity, $label($action, $entity));
                break;
            default:
                break;
        }

        return $breadcrumbs;
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function getEntityLabel(object $entity, array $options): ?string
    {
        /** @var (callable(object): ?string)|null $entityLabel */
        $entityLabel = $options['entity_label'];

        return $entityLabel ? $entityLabel($entity) : null;
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function getParentEntityLabel(?object $entity, array $options): ?string
    {
        if (!($parent = $this->getForceParent($entity, $options)) || !($controller = $options['parent_controller'])) {
            return null;
        }

        /** @var AbstractCrudController $controller */
        $parentResolved = $controller->resolve([]);

        return $this->getEntityLabel($parent, $parentResolved);
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function getParentEntityTitle(array $options): ?string
    {
        /** @var AbstractCrudController|null $parentController */
        $parentController = $options['parent_controller'];
        if (!$parentController) {
            return null;
        }

        /** @var string|null $title */
        $title = $parentController->resolve([])['title'];

        return $title;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function createNavigation(?object $entity = null, array $options = []): ?NavigationInterface
    {
        if (false === $options['nav']) {
            return null;
        }

        return new ClosureNavigation(function (MenuBuilder $root) use ($entity, $options): void {
            if (($parent = $this->getForceParent($entity, $options)) && ($controller = $options['parent_controller'])) {
                /** @var AbstractCrudController $controller */
                $parentOptions = $controller->resolve();
                /** @var array<string, mixed>|null $parentUpdate */
                $parentUpdate = $parentOptions['update'];
                if ($parentUpdate && isset($parentUpdate['route'])) {
                    /** @var array<string, mixed> $parentUpdateRouteParams */
                    $parentUpdateRouteParams = $parentUpdate['route_params'];
                    /** @var string|\Stringable $parentId */
                    $parentId = $parent->getId(); // @phpstan-ignore method.notFound
                    $root->add('common.nav.parent', [
                        'label' => $this->translator->trans('common.nav.parent', [
                            'type' => $parentOptions['title'],
                            'label' => $this->getEntityLabel($parent, $parentOptions),
                        ], 'cms'),
                        'route' => $parentUpdate['route'],
                        'route_params' => $parentUpdateRouteParams + ['id' => (string) $parentId],
                    ]);
                }
            }

            $add = function (MenuBuilder $builder, string $prefix, ?object $entity = null) use ($options): void {
                /** @var array<string, mixed> $prefixOptions */
                $prefixOptions = $options[$prefix];
                /** @var array<string, mixed> $params */
                $params = $prefixOptions['route_params'];
                if (null !== $entity) {
                    /** @var string|\Stringable $entityId */
                    $entityId = $entity->getId(); // @phpstan-ignore method.notFound
                    $params['id'] = (string) $entityId;
                }

                /** @var string $transId */
                $transId = $prefixOptions['title']['nav'] ?? \sprintf('common.nav.%s', $prefix); // @phpstan-ignore offsetAccess.nonOffsetAccessible
                $label = $this->translator->trans($transId, [
                    'type' => $options['title'],
                ], 'cms');
                $icon = self::NAV_ICONS[$prefix] ?? null;
                $builder->add(\sprintf('common.nav.%s', $prefix), [
                    // an icon and the text in a span: a phone shows the icon only (the text stays in the title for a screen reader)
                    'label' => null === $icon ? $label : \sprintf('<i class="fas fa-fw %s" aria-hidden="true"></i><span class="nav-text">%s</span>', $icon, \htmlspecialchars($label)),
                    'route' => $prefixOptions['route'],
                    'route_params' => $params,
                    // create and export are buttons opposite the title of the page (menu/nav_head.html.twig), the rest are the tabs under it
                    'attr' => (null === $icon ? [] : ['title' => $label, 'aria-label' => $label]) + (\in_array($prefix, ['create', 'export'], true) ? ['data-head' => '1'] : []),
                ]);
            };

            $actions = ['index', 'export', 'create'];
            foreach ($actions as $action) {
                if (null !== $options[$action]) {
                    $add($root, $action);
                }
            }

            if (null !== $entity) {
                $actions = ['update', 'meta'];
                foreach ($actions as $action) {
                    if (null !== $options[$action]) {
                        $add($root, $action, $entity);
                    }
                }
            }

            if (null !== $options['nav'] && \is_callable($options['nav'])) {
                \call_user_func($options['nav'], $root, $entity, $options);
            }
        });
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function createBulkNavigation(array $options = []): ?NavigationInterface
    {
        /** @var array<string, mixed> $index */
        $index = $options['index'];

        if (false === ($nav = $index['bulk_nav'])) {
            return null;
        }

        return new ClosureNavigation(static function (MenuBuilder $root) use ($options, $nav): void {
            /** @param array<string, mixed> $attr */
            $addRoute = static function (MenuBuilder $builder, string $prefix, array $attr = []) use ($options): void {
                /** @var array<string, mixed> $prefixOptions */
                $prefixOptions = $options[$prefix];
                /** @var string $bulkLabel */
                $bulkLabel = $prefixOptions['title']['nav'] ?? \sprintf('common.nav.%s', $prefix); // @phpstan-ignore offsetAccess.nonOffsetAccessible
                $builder->add(\sprintf('common.nav.%s', $prefix), [
                    'label' => $bulkLabel,
                    'translation_domain' => $options['translation_domain'],
                    'route' => $prefixOptions['route'],
                    'route_params' => $prefixOptions['route_params'],
                    'attr' => $attr,
                ]);
            };

            if (null !== $options['bulk_update']) {
                $addRoute($root, 'bulk_update', ['data-bulk-update' => '#modal-bulk-update']);
            }
            if (null !== $options['bulk_delete']) {
                // the deletion is confirmed in a dialog first (`data-confirm`)
                $addRoute($root, 'bulk_delete', ['data-method' => 'DELETE', 'data-confirm' => '#modal-bulk-delete']);
            }

            if (null !== $nav && \is_callable($nav)) {
                \call_user_func($nav, $root, $options);
            }
        });
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function resolve(array $options = []): array
    {
        /** @var array<string, mixed> $merged */
        $merged = \array_replace_recursive($this->options, $options + ['controller_class' => static::class]);

        return $this->configurator->resolve($merged);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>|null
     */
    protected function getRouteOptions(array $options, string $key): ?array
    {
        /** @var array<string, mixed>|null $keyOptions */
        $keyOptions = $options[$key];

        return $keyOptions ? [
            'route' => $keyOptions['route'],
            'route_params' => $keyOptions['route_params'],
        ] : null;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function factoryDataClass(array $options = []): DtoInterface
    {
        \assert(\is_callable($options['data_class']));

        /** @var DtoInterface $result */
        $result = \call_user_func($options['data_class'], $options['class'], $options);

        return $result;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function factoryForm(DtoInterface $dto, array $options = []): FormInterface
    {
        /** @var string $formClass */
        $formClass = $options['form_class'];

        return $this->createForm($formClass, $dto, [
            'label_format' => $options['label_format'],
            'translation_domain' => $options['translation_domain'],
        ]);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function getForceParent(?object $entity, array $options = []): ?object
    {
        if (!\is_callable($options['_parent']) || !$entity) {
            /** @var object|null $parent */
            $parent = $options['_parent'];

            return $parent;
        }

        /** @var object|null $parent */
        $parent = \call_user_func($options['_parent'], $entity);

        return $parent;
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function doCopy(object $entity, array $options = []): Response|ViewInterface
    {
        $options = $this->resolve($options);
        $this->processor->copy($entity);
        $this->addFlash('success', 'crud.flash.copied');

        /** @var array<string, mixed> $index */
        $index = $options['index'];
        /** @var string $indexRoute */
        $indexRoute = $index['route'];
        /** @var array<string, mixed> $indexRouteParams */
        $indexRouteParams = $index['route_params'];

        return $this->redirectBack($indexRoute, $indexRouteParams);
    }
}
