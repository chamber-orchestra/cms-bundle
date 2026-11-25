<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Audit;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Uid\Uuid;

/**
 * Writes one line of the journal (plain SQL, so it never takes part in the unit of work it describes), with its seal when `integrity` is on.
 */
final readonly class AuditWriter
{
    public function __construct(private Connection $connection, private AuditSigner $signer)
    {
    }

    /**
     * @param array<string, array{0: mixed, 1: mixed}>|null $changes
     */
    public function write(string $actor, string $action, string $entityClass, string $entityId, string $entityLabel, ?array $changes, ?string $ip = null): void
    {
        $id = Uuid::v7()->toRfc4122();
        $at = new \DateTimeImmutable('now', new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $label = \mb_substr($entityLabel, 0, 255);
        $this->connection->executeStatement(
            'INSERT INTO audit_log (id, occurred_at, actor, action, entity_class, entity_id, entity_label, changes, ip, signature) VALUES (?, ?, ?, ?, ?, ?, ?, ?::jsonb, ?, ?)',
            [$id, $at, $actor, $action, $entityClass, $entityId, $label, null === $changes ? null : \json_encode($changes, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_INVALID_UTF8_SUBSTITUTE), $ip, $this->signer->sign($id, $at, $actor, $action, $entityClass, $entityId, $label, $changes, $ip)],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING],
        );
    }
}
