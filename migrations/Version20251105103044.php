<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251105103044 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE documents CHANGE published published TINYINT(1) DEFAULT 0 NOT NULL, CHANGE level_durations level_durations JSON DEFAULT \'[]\' NOT NULL COMMENT \'Durations per level (ints)(DC2Type:json)\', CHANGE grid_columns grid_columns INT UNSIGNED DEFAULT 1 NOT NULL, CHANGE grid_rows grid_rows INT UNSIGNED DEFAULT 1 NOT NULL, CHANGE set_bpm set_bpm NUMERIC(5, 2) UNSIGNED DEFAULT \'1.00\' NOT NULL, CHANGE instruments_config instruments_config JSON DEFAULT \'[]\' NOT NULL COMMENT \'Array of InstrumentConfig-like associative arrays(DC2Type:json)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE documents CHANGE published published TINYINT(1) NOT NULL, CHANGE level_durations level_durations JSON NOT NULL COMMENT \'Durations per level (ints)(DC2Type:json)\', CHANGE grid_columns grid_columns INT UNSIGNED NOT NULL, CHANGE grid_rows grid_rows INT UNSIGNED NOT NULL, CHANGE set_bpm set_bpm NUMERIC(5, 2) UNSIGNED NOT NULL, CHANGE instruments_config instruments_config JSON NOT NULL COMMENT \'Array of InstrumentConfig-like objects(DC2Type:json)\'');
    }
}
