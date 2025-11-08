<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251107232144 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE document_tracks (id INT AUTO_INCREMENT NOT NULL, document_id INT NOT NULL, midi_asset_id INT DEFAULT NULL, track_id VARCHAR(50) NOT NULL, levels JSON DEFAULT \'[]\' NOT NULL COMMENT \'(DC2Type:json)\', position SMALLINT UNSIGNED DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_9A0F9B48C33F7837 (document_id), INDEX IDX_9A0F9B48F3BC28B4 (midi_asset_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE document_tracks ADD CONSTRAINT FK_9A0F9B48C33F7837 FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE document_tracks ADD CONSTRAINT FK_9A0F9B48F3BC28B4 FOREIGN KEY (midi_asset_id) REFERENCES assets (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE document_tracks DROP FOREIGN KEY FK_9A0F9B48C33F7837');
        $this->addSql('ALTER TABLE document_tracks DROP FOREIGN KEY FK_9A0F9B48F3BC28B4');
        $this->addSql('DROP TABLE document_tracks');
    }
}
