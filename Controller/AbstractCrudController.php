<?php

declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\Controller;

use ChamberOrchestra\Breadcrumbs\Breadcrumbs;
use ChamberOrchestra\CmsBundle\Configurator\CrudControllerConfigurator;
use ChamberOrchestra\CmsBundle\EntityRepository\EntityRepositoryWrapper;
use ChamberOrchestra\CmsBundle\Exception\ForeignKeyConstraintViolationExceptionWrapper;
use ChamberOrchestra\CmsBundle\Form\Dto\BulkOperationDto;
use ChamberOrchestra\CmsBundle\Form\Dto\DeleteDto;
use ChamberOrchestra\CmsBundle\Form\Dto\DtoInterface;
use ChamberOrchestra\CmsBundle\Form\Type\BulkOperationForm;
use ChamberOrchestra\CmsBundle\Form\Type\DeleteType;
use ChamberOrchestra\CmsBundle\Generator\CsvGeneratorInterface;
use ChamberOrchestra\CmsBundle\Processor\CrudProcessor;
use ChamberOrchestra\DoctrineExtensionsBundle\Contracts\Entity\ToggleInterface;
use ChamberOrchestra\DoctrineExtensionsBundle\Filter\SoftDeleteFilter;
use ChamberOrchestra\MenuBundle\Menu\MenuBuilderInterface;
use ChamberOrchestra\MenuBundle\Navigation\ClosureNavigation;
use ChamberOrchestra\MenuBundle\Navigation\NavigationInterface;
use ChamberOrchestra\CmsBundle\Form\Dto\MetaDto;
use ChamberOrchestra\CmsBundle\Form\Dto\MetaTranslatableDto;
use ChamberOrchestra\CmsBundle\Form\Type\MetaTranslatableType;
use ChamberOrchestra\CmsBundle\Form\Type\MetaType;
use ChamberOrchestra\PaginationBundle\Pagination\PaginationInterface;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use ChamberOrchestra\ViewBundle\View\ViewInterface;

#[Autoconfigure(tags: [AbstractCrudController::CRUD_CONTROLLER_TAG], lazy: true, public: false)]
abstract class AbstractCrudController extends AbstractController implements CrudControllerInterface
{
    use OriginPathTrait;

    public const CRUD_CONTROLLER_TAG = 'cms.crud_controller';
    protected array $options = [];
    protected ?TranslatorInterface $translator = null;
    protected ?CsvGeneratorInterface $csvGenerator = null;
    protected ?EntityManagerInterface $em = null;
    protected CrudControllerConfigurator $configurator;
    protected ServiceLocator $crudControllersLocator;
    private const OPTIONS = [
        'translation_domain' => 'cms',
        'parent_controller_class' => 'string',
        'parent' => 'string',
        '_parent' => 'string', // will return callback or object, depends on the current entity
        'controller_class' => 'string',
        'class' => 'string',
        'entity_label' => 'null|callable',
        'label_format' => 'null|string',
        'form_class' => 'null|string',
        'data_class' => 'null|string|callable',
        'form_themes' => 'array',
        'route_prefix' => 'null|string', // will be defined by "controller_class"
        'view_prefix' => 'null|string',
        'view_params' => 'array', // params which would be exposed to templated
        'title' => 'null|string', // by default will autodetected by "class" option
        'nav' => 'null|bool|callable|string',
        'breadcrumbs' => 'null|bool|callable|string',
        'index' => [
            'title' => 'string',
            'view' => 'string',
            'view_params' => 'array',
            'route' => 'string',
            'route_params' => 'array',
            'fields' => 'null|array',
            'actions' => 'null|callable',
            'criteria' => 'array|'.Criteria::class,
            'orderBy' => 'array',
            'filter_mapping' => 'array',
            'filter' => 'string',
            'bulk_operation' => 'bool',
            'bulk_nav' => 'null|callable',
            'export' => 'null|array',
            'alias' => 'null|array',
        ],
        'create' => [
            'title' => 'string',
            'view' => 'string',
            'view_params' => 'array',
            'route' => 'string',
            'route_params' => 'array',
        ],
        'update' => [
            'title' => 'string',
            'view' => 'string',
            'view_params' => 'array',
            'route' => 'string',
            'route_params' => 'array',
        ],
        'view' => [
            'title' => 'string',
            'view' => 'string',
            'view_params' => 'array',
            'route' => 'string',
            'route_params' => 'array',
        ],
        'meta' => [
            'title' => 'string',
            'view' => 'string',
            'view_params' => 'array',
            'route' => 'string',
            'route_params' => 'array',
        ],
        'move' => [
            'route' => 'string',
            'route_params' => 'array',
        ],
        'delete' => [
            'route' => 'string',
            'route_params' => 'array',
        ],
        'bulk_delete' => [
            'route' => 'string',
            'route_params' => 'array',
        ],
        'toggle' => [
            'route' => 'string',
            'route_params' => 'array',
        ],
        'copy' => [
            'route' => 'string',
            'route_params' => 'array',
        ],
        'export' => [
            'route' => 'string',
            'route_params' => 'array',
        ],
    ];

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

    public function __construct(protected readonly CrudProcessor $processor, #[ArrayShape(self::OPTIONS)] array $options = [])
    {
        $this->options = $options;
        $this->configurator = new CrudControllerConfigurator();
    }

    protected function doIndex(iterable $entities, ?PaginationInterface $pagination = null, array $options = [], array $parameters = []): Response
    {
        $options = $this->resolve($options);
        $deleteForm = null;
        if (null !== $options['delete']) {
            $deleteForm = $this->createDeleteForm($options);
        }

        $bulkOperationForm = null;
        if ($options['index']['bulk_operation'] ?? null) {
            $bulkOperationForm = $this->createBulkOperationForm($options);
        }

        if (\class_exists(SoftDeleteFilter::class)) {
            $this->em->getFilters()->enable(SoftDeleteFilter::class);
        }

        return $this->render($options['index']['view'], \array_merge_recursive([
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
            'bulk_operation_form' => $bulkOperationForm?->createView(),
            'order_by' => \array_keys($options['index']['order_by_mapping']),
        ], $options['index']['view_params'], $parameters));
    }

    protected function list(array $options = [], array $parameters = []): Response
    {
        $resolved = $this->resolve($options);
        if (\class_exists(SoftDeleteFilter::class)) {
            $this->em->getFilters()->enable(SoftDeleteFilter::class);
        }

        $request = $this->container->get('request_stack')->getCurrentRequest();
        $filter = $resolved['parent'] ? ['parent' => $resolved['parent']] : [];
        $form = null;
        if ($resolved['index']['filter']) {
            $form = $this->createForm($resolved['index']['filter'], null, [
                'translation_domain' => $resolved['translation_domain'],
                'label_format' => $resolved['label_format'],
            ]);
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $filter += $form->getData();
            }
        }

        $orderBy = $resolved['index']['order_by'];
        if ($sortBy = $request->query->get('sort')) {
            $direction = \strtoupper($request->query->getString('order', 'DESC'));
            $orderBy = [$sortBy => \in_array($direction, ['ASC', 'DESC'], true) ? $direction : 'DESC'];
        }

        $entities = (new EntityRepositoryWrapper($this->em->getRepository($resolved['class'])))->filterBy(
            $resolved['index']['query_builder'],
            $filter,
            $resolved['index']['filter_mapping'],
            $orderBy,
            $resolved['index']['order_by_mapping'],
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

    protected function doCreate(array $options = []): Response|ViewInterface
    {
        $dto = $this->factoryDataClass($options = $this->resolve($options));
        $form = $this->factoryForm($dto, $options);

        $request = $this->container->get('request_stack')->getMainRequest();
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            return $this->createSubmittedFormResponse($form, function (DtoInterface $dto) use ($options): Response|ViewInterface {
                $entity = $this->processor->create($dto);
                $this->addFlash('success', 'crud.flash.created');

                if (isset($options['update']['route'])) {
                    return $this->createRedirectToRouteResponse($options['update']['route'], \array_merge([
                        'id' => $entity->getId(),
                    ], $options['update']['route_params']));
                }

                return $this->createRedirectToRouteResponse(
                    $options['index']['route'],
                    $options['index']['route_params']
                );
            });
        }

        return $this->render($options['create']['view'], \array_merge([
            'parent_entity_label' => $this->getParentEntityLabel(null, $options),
            'parent_entity_title' => $this->getParentEntityTitle($options),
            'form' => $form->createView(),
            'nav' => $this->createNavigation(null, $options),
            'breadcrumbs' => $this->createBreadcrumbs('create', null, $options),
        ], $options['create']['view_params']));
    }

    protected function doView(object $entity, string $view, array $options = []): Response
    {
        $options = $this->resolve($options);

        return $this->render($view, \array_merge([
            'parent_entity_label' => $this->getParentEntityLabel(null, $options),
            'parent_entity_title' => $this->getParentEntityTitle($options),
            'entity' => $entity,
            'nav' => $this->createNavigation($entity, $options),
            'breadcrumbs' => $this->createBreadcrumbs('view', $entity, $options),
        ], $options['update']['view_params']));
    }

    protected function doRender(string $view, array $params = [], array $options = []): Response
    {
        $options = $this->resolve($options);

        return $this->render($view, \array_merge([
            'parent_entity_label' => $this->getParentEntityLabel(null, $options),
            'parent_entity_title' => $this->getParentEntityTitle($options),
            'nav' => $this->createNavigation(null, $options),
            'breadcrumbs' => $this->createBreadcrumbs('view', null, $options),
        ], $options['view_params'], $params));
    }

    protected function doUpdate(object $entity, array $options = []): Response|ViewInterface
    {
        $options = $this->resolve($options);
        $dto = $this->factoryDataClass($options);
        $this->processor->sync($entity, $dto);
        $form = $this->factoryForm($dto, $options);

        $request = $this->container->get('request_stack')->getMainRequest();
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            return $this->createSubmittedFormResponse($form, function () use ($entity, $dto, $options): Response|ViewInterface {
                $this->processor->update($dto, $entity);
                $this->addFlash('success', 'crud.flash.updated');

                return $this->createRedirectToRouteResponse($options['update']['route'], \array_merge([
                    'id' => $entity->getId(),
                ], $options['update']['route_params']));
            });
        }

        $view = 'view';
        if ($request->isXmlHttpRequest()) {
            $view = 'fragment';
        }

        return $this->render($options['update'][$view], \array_merge([
            'parent_entity_label' => $this->getParentEntityLabel(null, $options),
            'parent_entity_title' => $this->getParentEntityTitle($options),
            'form' => $form->createView(),
            'entity' => $entity,
            'entity_label' => $this->getEntityLabel($entity, $options),
            'nav' => $this->createNavigation($entity, $options),
            'breadcrumbs' => $this->createBreadcrumbs('update', $entity, $options),
        ], $options['update']['view_params']));
    }

    protected function doUpdateMeta(object $entity, array $options = []): Response|ViewInterface
    {
        $options = $this->resolve($options);

        $dto = $entity instanceof TranslatableInterface
            ? new MetaTranslatableDto($options['class'])
            : new MetaDto($options['class']);

        $this->processor->sync($entity, $dto);

        $request = $this->container->get('request_stack')->getMainRequest();
        $form = $this->createForm(
            $entity instanceof TranslatableInterface ? MetaTranslatableType::class : MetaType::class,
            $dto
        );
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            return $this->createSubmittedFormResponse($form, function () use ($entity, $dto, $options): Response|ViewInterface {
                $this->processor->update($dto, $entity);
                $this->addFlash('success', 'crud.flash.updated');

                return $this->createRedirectToRouteResponse($options['meta']['route'], \array_merge([
                    'id' => $entity->getId(),
                ], $options['meta']['route_params']));
            });
        }

        return $this->render($options['meta']['view'], \array_merge([
            'parent_entity_label' => $this->getParentEntityLabel(null, $options),
            'parent_entity_title' => $this->getParentEntityTitle($options),
            'entity_label' => $this->getEntityLabel($entity, $options),
            'form' => $form->createView(),
            'nav' => $this->createNavigation($entity, $options),
            'breadcrumbs' => $this->createBreadcrumbs('meta', $entity, $options),
        ], $options['meta']['view_params']));
    }

    protected function doDelete(array $options = []): Response|ViewInterface
    {
        $options = $this->resolve($options);
        $form = $this->createForm(DeleteType::class, new DeleteDto($options['class']), [
            'class' => $options['class'],
        ]);

        return $this->handleFormCall($form, function (DeleteDto $dto) use ($options): Response|ViewInterface {
            try {
                $this->processor->delete($dto, $dto->entity);
            } catch (ForeignKeyConstraintViolationException $e) {
                return $this->createExceptionResponse(new ForeignKeyConstraintViolationExceptionWrapper($e));
            }

            $this->addFlash('success', 'crud.flash.deleted');

            return $this->createRedirectResponse($this->getBackPath($options['index']['route'], $options['index']['route_params']));
        });
    }

    protected function doBulkDelete(array $options = []): Response|ViewInterface
    {
        $form = $this->createBulkOperationForm($options = $this->resolve($options), [
            'method' => 'DELETE',
        ]);

        return $this->handleFormCall($form, function (BulkOperationDto $dto) use ($options): Response|ViewInterface {
            try {
                $this->processor->bulkDelete($dto, $options['class']);
            } catch (ForeignKeyConstraintViolationException $e) {
                return $this->createExceptionResponse(new ForeignKeyConstraintViolationExceptionWrapper($e));
            }

            $this->addFlash('success', $dto->all ? 'crud.flash.all_deleted' : 'crud.flash.selected_deleted');

            return $this->redirectBack($options['index']['route'], $options['index']['route_params']);
        });
    }

    protected function doToggle(ToggleInterface $entity, array $options = []): Response|ViewInterface
    {
        $options = $this->resolve($options);
        $this->processor->toggle($entity);
        $this->addFlash('success', $entity->isEnabled() ? 'crud.flash.enabled' : 'crud.flash.disabled');

        return $this->redirectBack($options['index']['route'], $options['index']['route_params']);
    }

    protected function doExport(array $options = []): Response|ViewInterface
    {
        $options = $this->resolve($options);

        $file = $this->csvGenerator->generate($options);
        $fileName = \implode('-', \array_filter([($this->translator->trans($options['export']['title'], [], 'cms') ?? null), \date('d-m-y')])).'.csv';
        $response = new BinaryFileResponse($file);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $fileName);

        return $response;
    }

    protected function createDeleteForm(array $options): FormInterface
    {
        return $this->createForm(DeleteType::class, new DeleteDto($options['class']), [
            'class' => $options['class'],
            'action' => $this->generateUrl($options['delete']['route'], $options['delete']['route_params']),
        ]);
    }

    protected function createBulkOperationForm(array $options, array $formOptions = []): FormInterface
    {
        return $this->createForm(BulkOperationForm::class, new BulkOperationDto(), $formOptions);
    }

    protected function doMove(object $entity, string $direction): Response|ViewInterface
    {
        $this->processor->move($entity, $direction);

        return $this->createSuccessResponse();
    }

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

        $add = static function (Breadcrumbs $crumbs, string $prefix, ?object $entity = null, ?string $label = null) use ($options): void {
            if (!isset($options[$prefix]['route'])) {
                $crumbs->addCrumb($label);

                return;
            }

            $params = $options[$prefix]['route_params'];
            if (null !== $entity) {
                $params['id'] = $entity->getId();
            }

            $crumbs->addCrumb($label, $options[$prefix]['route'], $params);
        };

        $label = fn(string $prefix, ?object $entity = null): string => $this->translator->trans($options[$prefix]['title']['breadcrumbs'] ?? \sprintf('common.crumbs.%s', $prefix), [
            'type' => $this->translator->trans($options['title'], [], 'cms'),
            'label' => $entity ? $this->getEntityLabel($entity, $options) : null,
        ], 'cms');

        $add($breadcrumbs, 'index', null, $label('index'));

        switch ($action) {
            case 'index':
                break;
            case 'create':
            case 'update':
                $add($breadcrumbs, $action, $entity, $label($action, $entity));
                break;
            case 'meta':
                $add($breadcrumbs, $sub = 'update', $entity, $label($sub, $entity));
                $add($breadcrumbs, $action, $entity, $label($action, $entity));
                break;
        }

        return $breadcrumbs;
    }

    protected function getEntityLabel(object $entity, array $options): ?string
    {
        return $options['entity_label'] ? $options['entity_label']($entity) : null;
    }

    protected function getParentEntityLabel(?object $entity, array $options): ?string
    {
        if (!($parent = $this->getForceParent($entity, $options)) || !($controller = $options['parent_controller'])) {
            return null;
        }

        return $this->getEntityLabel($parent, $controller->resolve([]));
    }

    protected function getParentEntityTitle(array $options): ?string
    {
        return $options['parent_controller'] ? $options['parent_controller']->resolve([])['title'] : null;
    }

    public function createNavigation(?object $entity = null, array $options = []): ?NavigationInterface
    {
        if (false === $options['nav']) {
            return null;
        }

        return new ClosureNavigation(function (MenuBuilderInterface $root) use ($entity, $options) {
            if (($parent = $this->getForceParent($entity, $options)) && ($controller = $options['parent_controller'])) {
                if (($parentOptions = $controller->resolve()) && isset($parentOptions['update']['route'])) {
                    $root->add('common.nav.parent', [
                        'label' => $this->translator->trans('common.nav.parent', [
                            'type' => $parentOptions['title'],
                            'label' => $parent ? $this->getEntityLabel($parent, $parentOptions) : null,
                        ], 'cms'),
                        'route' => $parentOptions['update']['route'],
                        'route_params' => $parentOptions['update']['route_params'] + ['id' => $parent->getId()],
                    ]);
                }
            }

            $add = function (MenuBuilderInterface $builder, string $prefix, ?object $entity = null) use ($options): void {
                $params = $options[$prefix]['route_params'];
                if (null !== $entity) {
                    $params['id'] = $entity->getId();
                }

                $builder->add(\sprintf('common.nav.%s', $prefix), [
                    'label' => $this->translator->trans($options[$prefix]['title']['nav'] ?? \sprintf('common.nav.%s', $prefix), [
                        'type' => $options['title'],
                    ], 'cms'),
                    'route' => $options[$prefix]['route'],
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

    protected function createBulkNavigation(array $options = []): ?NavigationInterface
    {
        if (false === ($nav = $options['index']['bulk_nav'])) {
            return null;
        }

        return new ClosureNavigation(function (MenuBuilderInterface $root) use ($options, $nav) {
            $addRoute = static function (MenuBuilderInterface $builder, string $prefix, array $attr = []) use ($options): void {
                $builder->add(\sprintf('common.nav.%s', $prefix), [
                    'label' => $options[$prefix]['title']['nav'] ?? \sprintf('common.nav.%s', $prefix),
                    'translation_domain' => $options['translation_domain'],
                    'route' => $options[$prefix]['route'],
                    'route_params' => $options[$prefix]['route_params'],
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

    public function resolve(array $options = []): array
    {
        return $this->configurator->resolve(\array_replace_recursive($this->options, $options + ['controller_class' => static::class]));
    }

    protected function getRouteOptions(array $options, string $key): ?array
    {
        return $options[$key] ? [
            'route' => $options[$key]['route'],
            'route_params' => $options[$key]['route_params'],
        ] : null;
    }

    private function factoryDataClass(array $options = []): DtoInterface
    {
        return \call_user_func($options['data_class'], $options['class'], $options);
    }

    private function factoryForm(DtoInterface $dto, array $options = []): FormInterface
    {
        return $this->createForm($options['form_class'], $dto, [
            'label_format' => $options['label_format'],
            'translation_domain' => $options['translation_domain'],
        ]);
    }

    private function getForceParent(?object $entity, array $options = []): ?object
    {
        return (!\is_callable($options['_parent']) || !$entity) ? $options['_parent'] : \call_user_func($options['_parent'], $entity);
    }

    protected function doCopy(object $entity, array $options = []): Response|ViewInterface
    {
        $options = $this->resolve($options);
        $this->processor->copy($entity);
        $this->addFlash('success', 'crud.flash.copied');

        return $this->redirectBack($options['index']['route'], $options['index']['route_params']);
    }
}
