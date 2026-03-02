<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Controller;

use ChamberOrchestra\CmsBundle\Entity\ContentEntry;
use ChamberOrchestra\CmsBundle\Entity\ContentType;
use ChamberOrchestra\CmsBundle\Form\Dto\ContentEntryDto;
use ChamberOrchestra\CmsBundle\Form\Dto\DeleteDto;
use ChamberOrchestra\CmsBundle\Form\Type\ContentEntryType;
use ChamberOrchestra\CmsBundle\Form\Type\DeleteType;
use ChamberOrchestra\CmsBundle\Repository\ContentEntryRepository;
use ChamberOrchestra\CmsBundle\Repository\ContentTypeRepository;
use ChamberOrchestra\DoctrineExtensionsBundle\Filter\SoftDeleteFilter;
use ChamberOrchestra\ViewBundle\View\ViewInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @mixin AbstractCrudController
 */
trait SupportsContentEntriesOperation // @phpstan-ignore trait.unused
{
    #[Route('/{id}/entries', name: 'entries_index', methods: ['GET'])]
    public function entriesIndex(object|string|int $id): Response|ViewInterface
    {
        $parent = $this->resolveEntryParent($id);
        $options = $this->resolve();
        $routePrefix = $options['route_prefix'];

        /** @var ContentEntryRepository $entryRepo */
        $entryRepo = $this->em->getRepository(ContentEntry::class);
        /** @var ContentTypeRepository $typeRepo */
        $typeRepo = $this->em->getRepository(ContentType::class);

        if (\class_exists(SoftDeleteFilter::class)) {
            $this->em->getFilters()->enable(SoftDeleteFilter::class);
        }

        $entries = $entryRepo->findByOwner($parent);
        $contentTypes = $typeRepo->findAll();

        $deleteForm = $this->createForm(DeleteType::class, new DeleteDto(ContentEntry::class), [
            'class' => ContentEntry::class,
            'action' => $this->generateUrl($routePrefix.'_entries_delete', ['id' => $parent->getId()]),
        ]);

        return $this->render('@ChamberOrchestraCms/content/entries/index.html.twig', [
            'entities' => $entries,
            'content_types' => $contentTypes,
            'parent' => $parent,
            'delete_form' => $deleteForm->createView(),
            'parent_entity_label' => $this->getParentEntityLabel(null, $options),
            'parent_entity_title' => $this->getParentEntityTitle($options),
            'nav' => $this->createNavigation($parent, $options),
            'breadcrumbs' => $this->createBreadcrumbs('index', $parent, $options),
            'route_prefix' => $routePrefix,
            'move' => [
                'route' => $routePrefix.'_entries_move',
                'route_params' => ['id' => $parent->getId()],
            ],
        ]);
    }

    #[Route('/{id}/entries/{contentType}/create', name: 'entries_create', methods: ['GET', 'POST'])]
    public function entriesCreate(object|string|int $id, ContentType $contentType): Response|ViewInterface
    {
        $parent = $this->resolveEntryParent($id);
        $options = $this->resolve();
        $routePrefix = $options['route_prefix'];
        $parentId = $parent->getId();

        /** @var ContentTypeRepository $typeRepo */
        $typeRepo = $this->em->getRepository(ContentType::class);

        if (\class_exists(SoftDeleteFilter::class)) {
            $this->em->getFilters()->enable(SoftDeleteFilter::class);
        }

        $contentTypes = $typeRepo->findAll();

        $dto = new ContentEntryDto($contentType, $parent);
        $form = $this->createForm(ContentEntryType::class, $dto);
        $request = $this->container->get('request_stack')->getMainRequest();
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            return $this->createSubmittedFormResponse($form, function () use ($dto, $routePrefix, $parentId): Response|ViewInterface {
                $this->processor->create($dto);
                $this->addFlash('success', 'crud.flash.created');

                return $this->createRedirectToRouteResponse($routePrefix.'_entries_index', ['id' => $parentId]);
            });
        }

        return $this->render('@ChamberOrchestraCms/content/entries/create.html.twig', \array_merge([
            'parent_entity_label' => $this->getParentEntityLabel(null, $options),
            'parent_entity_title' => $this->getParentEntityTitle($options),
            'form' => $form->createView(),
            'nav' => $this->createNavigation(null, $options),
            'breadcrumbs' => $this->createBreadcrumbs('create', null, $options),
            'content_types' => $contentTypes,
            'owner' => $parent,
            'route_prefix' => $routePrefix,
        ], $options['create']['view_params']));
    }

    #[Route('/{id}/entries/{entryId}/update', name: 'entries_update', methods: ['GET', 'POST'])]
    public function entriesUpdate(object|string|int $id, object|string|int $entryId): Response|ViewInterface
    {
        $parent = $this->resolveEntryParent($id);
        $options = $this->resolve();
        $routePrefix = $options['route_prefix'];
        $parentId = $parent->getId();

        $entry = \is_object($entryId)
            ? $entryId
            : $this->em->getRepository(ContentEntry::class)->find($entryId);

        if (null === $entry) {
            throw $this->createNotFoundException(\sprintf('ContentEntry "%s" not found.', $entryId));
        }

        /** @var ContentTypeRepository $typeRepo */
        $typeRepo = $this->em->getRepository(ContentType::class);

        if (\class_exists(SoftDeleteFilter::class)) {
            $this->em->getFilters()->enable(SoftDeleteFilter::class);
        }

        $contentTypes = $typeRepo->findAll();

        $dto = new ContentEntryDto($entry->getContentType(), $parent);
        $this->processor->sync($entry, $dto);
        $form = $this->createForm(ContentEntryType::class, $dto);
        $request = $this->container->get('request_stack')->getMainRequest();
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            return $this->createSubmittedFormResponse($form, function () use ($entry, $dto, $routePrefix, $parentId): Response|ViewInterface {
                $this->processor->update($dto, $entry);
                $this->addFlash('success', 'crud.flash.updated');

                return $this->createRedirectToRouteResponse($routePrefix.'_entries_update', [
                    'id' => $parentId,
                    'entryId' => $entry->getId(),
                ]);
            });
        }

        return $this->render('@ChamberOrchestraCms/content/entries/update.html.twig', \array_merge([
            'parent_entity_label' => $this->getParentEntityLabel(null, $options),
            'parent_entity_title' => $this->getParentEntityTitle($options),
            'form' => $form->createView(),
            'entity' => $entry,
            'entity_label' => $this->getEntityLabel($entry, $options),
            'nav' => $this->createNavigation($parent, $options),
            'breadcrumbs' => $this->createBreadcrumbs('index', $parent, $options),
            'content_types' => $contentTypes,
            'owner' => $parent,
            'route_prefix' => $routePrefix,
        ], $options['update']['view_params']));
    }

    #[Route('/{id}/entries/{entryId}/move/{direction<up|down>}', name: 'entries_move', methods: ['POST'])]
    public function entriesMove(object|string|int $id, object|string|int $entryId, string $direction): Response|ViewInterface
    {
        $this->resolveEntryParent($id);

        $entry = \is_object($entryId)
            ? $entryId
            : $this->em->getRepository(ContentEntry::class)->find($entryId);

        if (null === $entry) {
            throw $this->createNotFoundException(\sprintf('ContentEntry "%s" not found.', $entryId));
        }

        return $this->doMove($entry, $direction);
    }

    #[Route('/{id}/entries/delete', name: 'entries_delete', methods: ['DELETE'])]
    public function entriesDelete(object|string|int $id): Response|ViewInterface
    {
        $parent = $this->resolveEntryParent($id);
        $options = $this->resolve();
        $routePrefix = $options['route_prefix'];
        $parentId = $parent->getId();

        $form = $this->createForm(DeleteType::class, new DeleteDto(ContentEntry::class), [
            'class' => ContentEntry::class,
        ]);

        return $this->handleFormCall($form, function (DeleteDto $dto) use ($routePrefix, $parentId): Response|ViewInterface {
            $this->processor->delete($dto, $dto->entity);
            $this->addFlash('success', 'crud.flash.deleted');

            return $this->createRedirectResponse(
                $this->getBackPath($routePrefix.'_entries_index', ['id' => $parentId])
            );
        });
    }

    private function resolveEntryParent(object|string|int $id): object
    {
        if (\is_object($id)) {
            return $id;
        }

        if (!isset($this->options['class'])) {
            throw new \RuntimeException('SupportsContentEntriesOperation requires the "class" option.');
        }

        $entity = $this->em->getRepository($this->options['class'])->find($id);

        if (null === $entity) {
            throw $this->createNotFoundException(\sprintf('Entity "%s" with id "%s" not found.', $this->options['class'], $id));
        }

        return $entity;
    }
}
