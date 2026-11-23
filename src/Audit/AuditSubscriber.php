<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Audit;

use ChamberOrchestra\CmsBundle\Audit\Entity\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The journal: after every flush made by a request under `path_prefix` with a user of `user_class` signed in, one line for each row created, changed, deleted or brought
 * back, with what changed. Commands, imports, the schedule and the requests of other users are not written (there is nobody to name), nor are the `ignore`d entities.
 * The lines are collected while the unit of work is known (`onFlush`) and written after it (`postFlush`, when a new row has its id).
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class AuditSubscriber
{
    /** The fields whose value is never written. */
    private const string SECRET = '/password|token|secret|hash/i';
    /** Fields that change with every save and say nothing. */
    private const array NOISE = ['updatedDatetime', 'lastLoginAt', 'version'];
    private const int VALUE_LENGTH = 300;

    /** @var list<array{action: string, entity: object, id: ?string, label: string, changes: ?array<string, array{0: mixed, 1: mixed}>}> */
    private array $pending = [];

    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requests,
        private readonly AuditWriter $writer,
        private readonly string $pathPrefix = '/',
        private readonly string $userClass = UserInterface::class,
        /** @var list<string> entities that are never written */
        private readonly array $ignore = [],
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $user = $this->actor();
        if (null === $user) {
            return;
        }
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            if ($this->tracked($entity)) {
                $this->pending[] = ['action' => 'create', 'entity' => $entity, 'id' => null, 'label' => $this->label($entity), 'changes' => $this->changes($uow->getEntityChangeSet($entity), true)];
            }
        }
        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if (!$this->tracked($entity)) {
                continue;
            }
            $set = $uow->getEntityChangeSet($entity);
            $action = 'update';
            if (isset($set['deletedDatetime']) && \is_array($set['deletedDatetime'])) {
                $action = null === ($set['deletedDatetime'][1] ?? null) ? 'restore' : 'delete';
                unset($set['deletedDatetime']);
            }
            $changes = $this->changes($set, false);
            if ('update' === $action && [] === $changes) {
                continue; // only noise changed
            }
            $this->pending[] = ['action' => $action, 'entity' => $entity, 'id' => $this->id($em, $entity), 'label' => $this->label($entity), 'changes' => [] === $changes ? null : $changes];
        }
        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if ($this->tracked($entity)) {
                $this->pending[] = ['action' => 'delete', 'entity' => $entity, 'id' => $this->id($em, $entity), 'label' => $this->label($entity), 'changes' => null];
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ([] === $this->pending) {
            return;
        }
        $pending = $this->pending;
        $this->pending = [];
        $user = $this->actor();
        if (null === $user) {
            return;
        }
        $em = $args->getObjectManager();
        foreach ($pending as $line) {
            $id = $line['id'] ?? $this->id($em, $line['entity']);
            if (null === $id) {
                continue;
            }
            try {
                $this->writer->write($user->getUserIdentifier(), $line['action'], $em->getClassMetadata($line['entity']::class)->getName(), $id, $line['label'], $line['changes']);
            } catch (\Throwable $e) {
                // the change itself is saved: a line of the journal that fails is logged, it does not turn a saved change into an error page
                $this->logger?->error('The journal of the admin could not be written: '.$e->getMessage());
            }
        }
    }

    private function actor(): ?UserInterface
    {
        $request = $this->requests->getMainRequest();
        if (null === $request || !\str_starts_with($request->getPathInfo(), $this->pathPrefix)) {
            return null;
        }
        $user = $this->security->getUser();

        return $user instanceof UserInterface && $user instanceof $this->userClass ? $user : null;
    }

    private function tracked(object $entity): bool
    {
        if ($entity instanceof AuditLog) {
            return false;
        }
        foreach ($this->ignore as $class) {
            if ($entity instanceof $class) {
                return false;
            }
        }

        return true;
    }

    private function id(EntityManagerInterface $em, object $entity): ?string
    {
        $values = $em->getUnitOfWork()->getEntityIdentifier($entity);
        $id = \array_values($values)[0] ?? null;

        return \is_scalar($id) || $id instanceof \Stringable ? (string) $id : null;
    }

    /**
     * How the row is called: the first of the usual name-like fields, else its class.
     */
    private function label(object $entity): string
    {
        foreach (['title', 'name', 'article', 'email', 'number', 'url'] as $field) {
            if (isset($entity->{$field}) && \is_scalar($entity->{$field}) && '' !== (string) $entity->{$field}) {
                return (string) $entity->{$field};
            }
        }

        return new \ReflectionClass($entity)->getShortName();
    }

    /**
     * @param array<string, mixed> $set
     *
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    private function changes(array $set, bool $created): array
    {
        $changes = [];
        foreach ($set as $field => $pair) {
            if (!\is_array($pair) || !\array_key_exists(1, $pair)) {
                continue; // a collection: not written
            }
            [$before, $after] = [$pair[0] ?? null, $pair[1]];
            if (\in_array($field, self::NOISE, true)) {
                continue;
            }
            if (1 === \preg_match(self::SECRET, $field)) {
                $changes[$field] = ['***', '***'];
                continue;
            }
            if ($created && (null === $after || '' === $after || [] === $after)) {
                continue;
            }
            $changes[$field] = [$this->value($before), $this->value($after)];
        }

        return $changes;
    }

    private function value(mixed $value): mixed
    {
        return match (true) {
            null === $value, \is_bool($value), \is_int($value), \is_float($value) => $value,
            \is_string($value) => \mb_substr($value, 0, self::VALUE_LENGTH),
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \UnitEnum => $value->name,
            $value instanceof File => $value->getFilename(),
            \is_array($value) => \mb_substr((string) \json_encode($value, \JSON_UNESCAPED_UNICODE | \JSON_INVALID_UTF8_SUBSTITUTE), 0, self::VALUE_LENGTH),
            \is_object($value) && isset($value->id) && \is_scalar($value->id) => (string) $value->id,
            $value instanceof \Stringable => \mb_substr((string) $value, 0, self::VALUE_LENGTH),
            default => \get_debug_type($value),
        };
    }
}
