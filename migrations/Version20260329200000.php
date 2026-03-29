<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add displayName column to assets table for inline renaming.
 */
final class Version20260329200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add displayName column to assets table for inline MIDI asset renaming';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE assets ADD display_name VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE assets DROP display_name');
    }

    public function isTransactional(): bool
    {
        return true;
    }
}
