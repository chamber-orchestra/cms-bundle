<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Command;

use ChamberOrchestra\CmsBundle\Audit\AuditSigner;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * `cms:audit:check`: checks the seal of every line of the journal (`audit.integrity`). A line that was changed, or made by hand in the table, does not fit its seal and is listed; a
 * line written before the seals were switched on has none and is only counted. Exit code 1 when a line does not fit.
 */
#[AsCommand(name: 'cms:audit:check', description: 'Checks the seals of the lines of the audit journal')]
final class AuditCheckCommand
{
    private const int BATCH = 500;

    public function __construct(private readonly Connection $connection, private readonly AuditSigner $signer)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        if (!$this->signer->enabled()) {
            $io->warning('The seals are off (chamber_orchestra_cms.audit.integrity.enabled): there is nothing to check.');

            return Command::SUCCESS;
        }
        $checked = $unsealed = 0;
        $broken = [];
        $last = '';
        do {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT id::text AS id, to_char(occurred_at, \'YYYY-MM-DD HH24:MI:SS\') AS occurred_at, actor, action, entity_class, entity_id, entity_label, changes, ip, signature FROM audit_log WHERE id::text > ? ORDER BY id::text LIMIT '.self::BATCH,
                [$last],
            );
            foreach ($rows as $row) {
                $last = self::text($row['id']);
                if (null === $row['signature']) {
                    ++$unsealed;
                    continue;
                }
                ++$checked;
                $changes = \is_string($row['changes']) ? \json_decode($row['changes'], true) : null;
                if (!$this->signer->verify(self::text($row['signature']), $last, self::text($row['occurred_at']), self::text($row['actor']), self::text($row['action']), self::text($row['entity_class']), self::text($row['entity_id']), self::text($row['entity_label']), \is_array($changes) ? $changes : null, \is_string($row['ip']) ? $row['ip'] : null)) {
                    $broken[] = [$last, self::text($row['occurred_at']), self::text($row['actor']), self::text($row['action']), self::text($row['entity_label'])];
                }
            }
        } while (self::BATCH === \count($rows));

        $io->writeln(\sprintf('Sealed lines checked: %d; without a seal (written before the seals were on): %d.', $checked, $unsealed));
        if ([] === $broken) {
            $io->success('Every sealed line fits its seal.');

            return Command::SUCCESS;
        }
        $io->table(['Id', 'When', 'Who', 'Action', 'Row'], $broken);
        $io->error(\sprintf('%d line(s) do not fit their seal: changed or made by hand.', \count($broken)));

        return Command::FAILURE;
    }

    private static function text(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }
}
