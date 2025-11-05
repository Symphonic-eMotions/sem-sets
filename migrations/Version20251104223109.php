<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251104223109 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE documents ADD level_durations JSON NOT NULL COMMENT \'Durations per level (ints)(DC2Type:json)\', ADD grid_columns INT UNSIGNED NOT NULL, ADD grid_rows INT UNSIGNED NOT NULL, ADD set_bpm NUMERIC(5, 2) UNSIGNED NOT NULL, ADD instruments_config JSON NOT NULL COMMENT \'Array of InstrumentConfig-like objects(DC2Type:json)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE documents DROP level_durations, DROP grid_columns, DROP grid_rows, DROP set_bpm, DROP instruments_config');
    }
}
