<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Controller;

use ChamberOrchestra\DoctrineExtensionsBundle\Contracts\Entity\SoftDeleteInterface;
use ChamberOrchestra\DoctrineExtensionsBundle\Filter\SoftDeleteFilter;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The page of the journal (`audit_log`, written by `Audit\AuditSubscriber`; on when `chamber_orchestra_cms.audit.enabled`, 404 otherwise): who changed or deleted what and when, newest first, with a filter, and a way back:
 * a row that was deleted (soft delete) can be restored from here.
 */
final class AuditController extends AbstractController
{
    private const int PER_PAGE = 50;

    public function __construct(private readonly Connection $connection, private readonly EntityManagerInterface $em, private readonly TranslatorInterface $translator, #[Autowire('%chamber_orchestra_cms.audit.enabled%')] private readonly bool $enabled = false)
    {
    }

    #[Route('/audit', name: 'audit_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->assertEnabled();
        $where = ['true'];
        $params = [];
        $actor = \trim($request->query->getString('actor'));
        if ('' !== $actor) {
            $where[] = 'a.actor = ?';
            $params[] = $actor;
        }
        $action = $request->query->getString('action');
        if (\in_array($action, ['create', 'update', 'delete', 'restore'], true)) {
            $where[] = 'a.action = ?';
            $params[] = $action;
        }
        $class = $request->query->getString('class');
        if ('' !== $class) {
            $where[] = 'a.entity_class = ?';
            $params[] = $class;
        }
        $text = \trim($request->query->getString('q'));
        if ('' !== $text) {
            $where[] = 'a.entity_label ILIKE ?';
            $params[] = '%'.\addcslashes($text, '%_\\').'%';
        }
        $condition = \implode(' AND ', $where);

        $total = (int) (\is_numeric($count = $this->connection->fetchOne('SELECT count(*) FROM audit_log a WHERE '.$condition, $params)) ? $count : 0);
        $pages = \max(1, (int) \ceil($total / self::PER_PAGE));
        $page = \min(\max(1, $request->query->getInt('page', 1)), $pages);
        $rows = $this->connection->fetchAllAssociative(
            'SELECT a.id::text AS id, a.occurred_at, a.actor, a.action, a.entity_class, a.entity_id, a.entity_label, a.changes FROM audit_log a WHERE '.$condition.' ORDER BY a.occurred_at DESC, a.id DESC LIMIT '.self::PER_PAGE.' OFFSET '.(($page - 1) * self::PER_PAGE),
            $params,
        );
        foreach ($rows as &$row) {
            $changes = \is_string($row['changes']) ? \json_decode($row['changes'], true) : null;
            $row['changes'] = \is_array($changes) ? $changes : [];
            $row['kind'] = $this->kind(self::text($row['entity_class']));
            $row['deleted'] = 'delete' === $row['action'] && $this->isStillDeleted(self::text($row['entity_class']), self::text($row['entity_id']));
        }
        unset($row);

        return $this->render('@ChamberOrchestraCms/audit/index.html.twig', [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'filter' => ['actor' => $actor, 'action' => $action, 'class' => $class, 'q' => $text],
            'actors' => $this->connection->fetchFirstColumn('SELECT DISTINCT actor FROM audit_log ORDER BY actor'),
            'classes' => \array_map(fn (mixed $class): array => ['class' => self::text($class), 'label' => $this->kind(self::text($class))], $this->connection->fetchFirstColumn('SELECT DISTINCT entity_class FROM audit_log ORDER BY entity_class')),
        ]);
    }

    #[Route('/audit/{id}/restore', name: 'audit_restore', methods: ['POST'], requirements: ['id' => '[0-9a-f-]{36}'])]
    public function restore(string $id, Request $request): RedirectResponse
    {
        $this->assertEnabled();
        if (!$this->isCsrfTokenValid('audit_restore', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $line = $this->connection->fetchAssociative('SELECT entity_class, entity_id FROM audit_log WHERE id = ? AND action = ?', [$id, 'delete']);
        if (!\is_array($line)) {
            throw $this->createNotFoundException();
        }
        $entity = $this->find(self::text($line['entity_class']), self::text($line['entity_id']));
        if (!\interface_exists(SoftDeleteInterface::class) || !$entity instanceof SoftDeleteInterface || !$entity->isDeleted()) {
            $this->addFlash('danger', 'audit.flash.not_restorable');

            return $this->redirectToRoute('audit_index');
        }
        $entity->restore();
        try {
            $this->em->persist($entity);
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            $this->addFlash('danger', 'audit.flash.taken'); // a new row took its address meanwhile

            return $this->redirectToRoute('audit_index');
        }
        $this->addFlash('success', 'audit.flash.restored');

        return $this->redirectToRoute('audit_index');
    }

    private function assertEnabled(): void
    {
        if (!$this->enabled) {
            throw $this->createNotFoundException(); // the journal is off: chamber_orchestra_cms.audit.enabled
        }
    }

    /**
     * How a kind of row is called: the application names its classes (`audit.kind.<ShortName>` in its `cms` translations), what it did not name is called by its class.
     */
    private function kind(string $class): string
    {
        $short = \substr((string) \strrchr('\\'.$class, '\\'), 1);
        $label = $this->translator->trans('audit.kind.'.$short, [], 'cms');

        return 'audit.kind.'.$short === $label ? $short : $label;
    }

    private static function text(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }

    private function isStillDeleted(string $class, string $id): bool
    {
        $entity = $this->find($class, $id);

        return \interface_exists(SoftDeleteInterface::class) && $entity instanceof SoftDeleteInterface && $entity->isDeleted();
    }

    /**
     * The row, deleted or not.
     */
    private function find(string $class, string $id): ?object
    {
        if (!\interface_exists(SoftDeleteInterface::class) || !\class_exists($class) || !\is_a($class, SoftDeleteInterface::class, true)) {
            return null;
        }
        $filters = $this->em->getFilters();
        $was = $filters->isEnabled(SoftDeleteFilter::class);
        if ($was) {
            $filters->disable(SoftDeleteFilter::class);
        }
        try {
            return $this->em->find($class, $id);
        } finally {
            if ($was) {
                $filters->enable(SoftDeleteFilter::class);
            }
        }
    }
}
