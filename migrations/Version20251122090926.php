<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251122090926 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE instrument_parts (id INT AUTO_INCREMENT NOT NULL, track_id INT NOT NULL, area_of_interest JSON DEFAULT \'[]\' NOT NULL COMMENT \'(DC2Type:json)\', position SMALLINT UNSIGNED DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_B19A13975ED23C43 (track_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE instrument_parts ADD CONSTRAINT FK_B19A13975ED23C43 FOREIGN KEY (track_id) REFERENCES document_tracks (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE document_tracks DROP area_of_interest');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE instrument_parts DROP FOREIGN KEY FK_B19A13975ED23C43');
        $this->addSql('DROP TABLE instrument_parts');
        $this->addSql('ALTER TABLE document_tracks ADD area_of_interest JSON DEFAULT \'[]\' NOT NULL COMMENT \'(DC2Type:json)\'');
    }
}
