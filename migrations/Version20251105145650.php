<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251105145650 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE documents CHANGE set_bpm set_bpm NUMERIC(5, 2) UNSIGNED DEFAULT \'90.00\' NOT NULL, CHANGE instruments_config instruments_config JSON DEFAULT \'[]\' NOT NULL COMMENT \'Array of InstrumentConfigs(DC2Type:json)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE documents CHANGE set_bpm set_bpm NUMERIC(5, 2) UNSIGNED DEFAULT \'1.00\' NOT NULL, CHANGE instruments_config instruments_config JSON DEFAULT \'[]\' NOT NULL COMMENT \'Array of InstrumentConfig-like associative arrays(DC2Type:json)\'');
    }
}
