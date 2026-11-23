<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Audit\Entity;

use ChamberOrchestra\DoctrineExtensionsBundle\Entity\GeneratedIdTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\DatePointType;
use Symfony\Component\Clock\DatePoint;

/**
 * One line of the journal of the admin: who (`actor`, the e-mail of the administrator), when, what (`action`: create, update, delete, restore), which row (`entityClass`,
 * `entityId`, `entityLabel`: how the row was called then) and what changed (`changes`: `{field: [before, after]}`). Written by `AuditWriter` with plain SQL after the flush of
 * a request of the admin; never changed.
 */
#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'audit_log')]
#[ORM\Index(name: 'audit_log_occurred_idx', columns: ['occurred_at'])]
#[ORM\Index(name: 'audit_log_entity_idx', columns: ['entity_class', 'entity_id'])]
#[ORM\Cache(usage: 'NONSTRICT_READ_WRITE')]
#[ORM\ChangeTrackingPolicy(value: 'DEFERRED_EXPLICIT')]
class AuditLog
{
    use GeneratedIdTrait;

    #[ORM\Column(name: 'occurred_at', type: DatePointType::NAME)]
    public private(set) DatePoint $occurredAt;

    #[ORM\Column(type: Types::STRING, length: 180)]
    public private(set) string $actor;

    #[ORM\Column(type: Types::STRING, length: 16)]
    public private(set) string $action;

    #[ORM\Column(name: 'entity_class', type: Types::STRING, length: 191)]
    public private(set) string $entityClass;

    #[ORM\Column(name: 'entity_id', type: Types::STRING, length: 64)]
    public private(set) string $entityId;

    #[ORM\Column(name: 'entity_label', type: Types::STRING, length: 255)]
    public private(set) string $entityLabel;

    /** @var array<string, array{0: mixed, 1: mixed}>|null */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['jsonb' => true])]
    public private(set) ?array $changes = null;
}
