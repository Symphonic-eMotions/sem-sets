<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251216210624 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add part_id to instrument_parts and backfill unique ULIDs for existing rows';
    }

    public function up(Schema $schema): void
    {
        // 1) Add nullable column first (no UNIQUE yet)
        $this->addSql('ALTER TABLE instrument_parts ADD part_id VARCHAR(26) DEFAULT NULL');

        // 2) Backfill unique values for existing rows
        // Use ULID-like base32(16 bytes) without padding, uppercased, 26 chars.
        // MariaDB: TO_BASE32() exists in recent versions; if yours doesn’t, use the fallback further below.
        $this->addSql("
            UPDATE instrument_parts
            SET part_id = UPPER(REPLACE(TO_BASE32(RANDOM_BYTES(16)), '=', ''))
            WHERE part_id IS NULL OR part_id = ''
        ");

        // 2b) Safety: in the extremely unlikely event of collisions, rerun a second pass for any duplicates
        // (This is defensive; collisions with 16 random bytes are practically nonexistent.)
        $this->addSql("
            UPDATE instrument_parts p
            JOIN (
                SELECT part_id
                FROM instrument_parts
                WHERE part_id IS NOT NULL
                GROUP BY part_id
                HAVING COUNT(*) > 1
            ) d ON d.part_id = p.part_id
            SET p.part_id = UPPER(REPLACE(TO_BASE32(RANDOM_BYTES(16)), '=', ''))
        ");

        // 3) Make it NOT NULL
        $this->addSql('ALTER TABLE instrument_parts MODIFY part_id VARCHAR(26) NOT NULL');

        // 4) Add unique index
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B19A13974CE34BEC ON instrument_parts (part_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_B19A13974CE34BEC ON instrument_parts');
        $this->addSql('ALTER TABLE instrument_parts DROP part_id');
    }
}
