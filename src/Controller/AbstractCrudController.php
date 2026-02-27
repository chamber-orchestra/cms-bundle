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
use ChamberOrchestra\CmsBundle\Form\Dto\BulkOperationDto;
use ChamberOrchestra\CmsBundle\Form\Dto\DeleteDto;
use ChamberOrchestra\CmsBundle\Form\Dto\DtoInterface;
use ChamberOrchestra\CmsBundle\Form\Dto\MetaDto;
use ChamberOrchestra\CmsBundle\Form\Dto\MetaTranslatableDto;
use ChamberOrchestra\CmsBundle\Form\Type\BulkOperationForm;
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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
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
    public function withCmsControllers(#[AutowireLocator(AbstractCrudController::CRUD_CONTROLLER_TAG)] ServiceLocator $controllersLocator): void
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

        $bulkOperationForm = null;
        if ($index['bulk_operation'] ?? null) {
            $bulkOperationForm = $this->createBulkOperationForm($options);
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
            'bulk_nav' => $this->createBulkNavigation($options),
            'breadcrumbs' => $this->createBreadcrumbs('index', null, $options),
            'delete_form' => $deleteForm?->createView(),
            'toggle' => $this->getRouteOptions($options, 'toggle'),
            'copy' => $this->getRouteOptions($options, 'copy'),
            'update' => $this->getRouteOptions($options, 'update'),
            'move' => $this->getRouteOptions($options, 'move'),
            'export' => $this->getRouteOptions($options, 'export'),
            'entries' => $this->getRouteOptions($options, 'entries'),
            'bulk_operation_form' => $bulkOperationForm?->createView(),
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

        /** @var \Doctrine\ORM\QueryBuilder $queryBuilder */
        $queryBuilder = $index['query_builder'];
        /** @var array<string, mixed> $filterMapping */
        $filterMapping = $index['filter_mapping'];
        /** @var array<string, mixed> $orderByMapping */
        $orderByMapping = $index['order_by_mapping'];
        /** @var class-string $entityClass */
        $entityClass = $resolved['class'];

        $entities = (new EntityRepositoryWrapper($this->em->getRepository($entityClass)))->filterBy(
            $queryBuilder,
            $filter,
            $filterMapping,
            $orderBy,
            $orderByMapping,
            $pagination = $this->getPagination(),
        );

        return $this->doIndex(
            $entities,
            $pagination,
            $options,
            $parameters + [
                'filter_form' => $form?->createView(),
            ]
        );
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

        return $this->handleFormCall($form, function (BulkOperationDto $dto) use ($indexRoute, $indexRouteParams, $entityClass): Response|ViewInterface {
            try {
                $this->processor->bulkDelete($dto, $entityClass);
            } catch (ForeignKeyConstraintViolationException $e) {
                return $this->createExceptionResponse();
            }

            $this->addFlash('success', $dto->all ? 'crud.flash.all_deleted' : 'crud.flash.selected_deleted');

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

        $file = $this->csvGenerator->generate($options);
        $fileName = \implode('-', \array_filter([$this->translator->trans($exportTitle, [], 'cms'), \date('d-m-Y')])).'.csv';
        $response = new BinaryFileResponse($file);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $fileName);

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

        return new ClosureNavigation(function (MenuBuilder $root) use ($entity, $options) {
            if (($parent = $this->getForceParent($entity, $options)) && ($controller = $options['parent_controller'])) {
                /** @var AbstractCrudController $controller */
                $parentOptions = $controller->resolve();
                /** @var array<string, mixed>|null $parentUpdate */
                $parentUpdate = $parentOptions['update'];
                if ($parentUpdate && isset($parentUpdate['route'])) {
                    /** @var array<string, mixed> $parentUpdateRouteParams */
                    $parentUpdateRouteParams = $parentUpdate['route_params'];
                    $root->add('common.nav.parent', [
                        'label' => $this->translator->trans('common.nav.parent', [
                            'type' => $parentOptions['title'],
                            'label' => $this->getEntityLabel($parent, $parentOptions),
                        ], 'cms'),
                        'route' => $parentUpdate['route'],
                        'route_params' => $parentUpdateRouteParams + ['id' => $parent->getId()], // @phpstan-ignore method.notFound
                    ]);
                }
            }

            $add = function (MenuBuilder $builder, string $prefix, ?object $entity = null) use ($options): void {
                /** @var array<string, mixed> $prefixOptions */
                $prefixOptions = $options[$prefix];
                /** @var array<string, mixed> $params */
                $params = $prefixOptions['route_params'];
                if (null !== $entity) {
                    $params['id'] = $entity->getId(); // @phpstan-ignore method.notFound
                }

                /** @var string $transId */
                $transId = $prefixOptions['title']['nav'] ?? \sprintf('common.nav.%s', $prefix); // @phpstan-ignore offsetAccess.nonOffsetAccessible
                $builder->add(\sprintf('common.nav.%s', $prefix), [
                    'label' => $this->translator->trans($transId, [
                        'type' => $options['title'],
                    ], 'cms'),
                    'route' => $prefixOptions['route'],
                    'route_params' => $params,
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

        return new ClosureNavigation(function (MenuBuilder $root) use ($options, $nav) {
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

            if (null !== $options['bulk_delete']) {
                $addRoute($root, 'bulk_delete', ['data-method' => 'DELETE']);
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
