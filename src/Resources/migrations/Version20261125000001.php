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
 * The journal knows the address of the client (`record_ip`) and seals its lines (`integrity`): two columns, both empty when the features are off.
 */
final class Version20261125000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'audit_log: ip and signature';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE audit_log ADD COLUMN IF NOT EXISTS ip VARCHAR(45) DEFAULT NULL');
        $this->addSql('ALTER TABLE audit_log ADD COLUMN IF NOT EXISTS signature VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE audit_log DROP COLUMN IF EXISTS signature');
        $this->addSql('ALTER TABLE audit_log DROP COLUMN IF EXISTS ip');
    }
}
