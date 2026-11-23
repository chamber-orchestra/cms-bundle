<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The table of the journal (`audit_log`) of the journal (the cms-bundle, `audit.enabled`): who changed or deleted what, when. `IF NOT EXISTS`: an application that had the table before the bundle was made keeps it.
 */
final class Version20261124000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'audit_log: the journal of the admin';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS audit_log (id UUID NOT NULL, occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, actor VARCHAR(180) NOT NULL, action VARCHAR(16) NOT NULL, entity_class VARCHAR(191) NOT NULL, entity_id VARCHAR(64) NOT NULL, entity_label VARCHAR(255) NOT NULL, changes JSONB DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IF NOT EXISTS audit_log_occurred_idx ON audit_log (occurred_at)');
        $this->addSql('CREATE INDEX IF NOT EXISTS audit_log_entity_idx ON audit_log (entity_class, entity_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS audit_log');
    }
}
