<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251216210624 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return false;
    }

    public function getDescription(): string
    {
        return 'Add part_id to instrument_parts and backfill unique identifiers for existing rows';
    }

    public function up(Schema $schema): void
    {
        // Sla de migratie over als part_id al als NOT NULL bestaat (eerder toegepast via een andere migratie).
        $alreadyApplied = (bool) $this->connection->fetchOne("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'instrument_parts'
              AND COLUMN_NAME  = 'part_id'
              AND IS_NULLABLE  = 'NO'
        ");
        $this->skipIf($alreadyApplied, 'Kolom part_id bestaat al als NOT NULL — migratie overgeslagen.');

        // MariaDB 10.11 in local Docker does not provide TO_BASE32().
        // Use UUID-based backfill instead and make the migration rerunnable after a partial failure.
        $this->addSql('ALTER TABLE instrument_parts ADD COLUMN IF NOT EXISTS part_id VARCHAR(26) DEFAULT NULL');

        $this->addSql("
            UPDATE instrument_parts
            SET part_id = LEFT(CONCAT(REPLACE(UUID(), '-', ''), REPLACE(UUID(), '-', '')), 26)
            WHERE part_id IS NULL OR part_id = ''
        ");

        $this->addSql("
            UPDATE instrument_parts p
            JOIN (
                SELECT part_id
                FROM instrument_parts
                WHERE part_id IS NOT NULL AND part_id <> ''
                GROUP BY part_id
                HAVING COUNT(*) > 1
            ) d ON d.part_id = p.part_id
            SET p.part_id = LEFT(CONCAT(REPLACE(UUID(), '-', ''), REPLACE(UUID(), '-', '')), 26)
        ");

        $this->addSql('ALTER TABLE instrument_parts MODIFY part_id VARCHAR(26) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_B19A13974CE34BEC ON instrument_parts (part_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS UNIQ_B19A13974CE34BEC ON instrument_parts');
        $this->addSql('ALTER TABLE instrument_parts DROP COLUMN IF EXISTS part_id');
    }
}
