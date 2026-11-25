<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Audit;

use ChamberOrchestra\CmsBundle\Audit\Attribute\Auditable;
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
 * The journal: after every flush made by a request under `path_prefix` with a user of `user_class` signed in, one line for each row of an `#[Auditable]` entity created, changed,
 * deleted or brought back (the actions the attribute lists), with what changed: `#[AuditIgnore]` properties are left out, `#[AuditTransform]` ones are written in their
 * transformed form. Commands, imports, the schedule and the requests of other users are not written (there is nobody to name), nor is an entity without the attribute.
 * The lines are collected while the unit of work is known (`onFlush`) and written after it (`postFlush`, when a new row has its id).
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class AuditSubscriber
{
    /** A field with such a name is written as a mask even without `#[AuditTransform]` (a safety net: a password must not reach the journal because an attribute was forgotten). */
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
        private readonly AuditMetadata $metadata = new AuditMetadata(),
        private readonly AuditTransformer $transformer = new AuditTransformer(''),
        private readonly bool $recordIp = false,
        private readonly bool $anonymizeIp = true,
        /** false: a line that cannot be written is an error (development and tests); true: it is logged and the saved change stays saved */
        private readonly bool $silentFailures = true,
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
            if ($this->tracked($entity, Auditable::CREATE)) {
                $this->pending[] = ['action' => 'create', 'entity' => $entity, 'id' => null, 'label' => $this->label($entity), 'changes' => $this->changes($entity, $uow->getEntityChangeSet($entity), true)];
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
            if (!$this->tracked($entity, $action)) {
                continue;
            }
            $changes = $this->changes($entity, $set, false);
            if ('update' === $action && [] === $changes) {
                continue; // only noise (or what is ignored) changed
            }
            $this->pending[] = ['action' => $action, 'entity' => $entity, 'id' => $this->id($em, $entity), 'label' => $this->label($entity), 'changes' => [] === $changes ? null : $changes];
        }
        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if ($this->tracked($entity, Auditable::DELETE)) {
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
        $ip = $this->ip();
        foreach ($pending as $line) {
            $id = $line['id'] ?? $this->id($em, $line['entity']);
            if (null === $id) {
                continue;
            }
            try {
                $this->writer->write($user->getUserIdentifier(), $line['action'], $em->getClassMetadata($line['entity']::class)->getName(), $id, $line['label'], $line['changes'], $ip);
            } catch (\Throwable $e) {
                if (!$this->silentFailures) {
                    throw $e;
                }
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

    /**
     * Is the row journalled: its class (or a parent) has `#[Auditable]`, and the attribute lists the action (any action when `$action` is null: the row is only looked at).
     */
    private function tracked(object $entity, ?string $action = null): bool
    {
        if ($entity instanceof AuditLog) {
            return false;
        }
        $meta = $this->metadata->of($entity);

        return null !== $meta && (null === $action || \in_array($action, $meta['actions'], true));
    }

    /**
     * The address of the client when `record_ip` is on: the last part of it is cut off when `anonymize_ip` is on (IPv4: the last byte; IPv6: the last 80 bits).
     */
    private function ip(): ?string
    {
        $ip = $this->recordIp ? $this->requests->getMainRequest()?->getClientIp() : null;
        if (null === $ip) {
            return null;
        }
        if (!$this->anonymizeIp) {
            return $ip;
        }
        $packed = @\inet_pton($ip);
        if (false === $packed) {
            return null;
        }
        $keep = 4 === \strlen($packed) ? 3 : 6;

        return \inet_ntop(\substr($packed, 0, $keep).\str_repeat("\0", \strlen($packed) - $keep)) ?: null;
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
    private function changes(object $entity, array $set, bool $created): array
    {
        $meta = $this->metadata->of($entity) ?? ['actions' => [], 'ignored' => [], 'transforms' => []];
        $changes = [];
        foreach ($set as $field => $pair) {
            if (!\is_array($pair) || !\array_key_exists(1, $pair)) {
                continue; // a collection: not written
            }
            [$before, $after] = [$pair[0] ?? null, $pair[1]];
            if (\in_array($field, self::NOISE, true) || isset($meta['ignored'][$field])) {
                continue;
            }
            if ($created && (null === $after || '' === $after || [] === $after)) {
                continue;
            }
            if (isset($meta['transforms'][$field])) {
                $changes[$field] = [$this->transformer->apply($meta['transforms'][$field], $this->value($before)), $this->transformer->apply($meta['transforms'][$field], $this->value($after))];
                continue;
            }
            if (1 === \preg_match(self::SECRET, $field)) {
                $changes[$field] = [AuditTransformer::MASK, AuditTransformer::MASK];
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
