<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251123131946 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE document_track_effects (id INT AUTO_INCREMENT NOT NULL, track_id INT NOT NULL, preset_id INT NOT NULL, position INT NOT NULL, INDEX IDX_B63A003C5ED23C43 (track_id), INDEX IDX_B63A003C80688E6F (preset_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE document_track_effects ADD CONSTRAINT FK_B63A003C5ED23C43 FOREIGN KEY (track_id) REFERENCES document_tracks (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE document_track_effects ADD CONSTRAINT FK_B63A003C80688E6F FOREIGN KEY (preset_id) REFERENCES effect_settings (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE effect_settings DROP FOREIGN KEY FK_D78A065B5ED23C43');
        $this->addSql('DROP INDEX IDX_D78A065B5ED23C43 ON effect_settings');
        $this->addSql('ALTER TABLE effect_settings DROP track_id, DROP position');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE document_track_effects DROP FOREIGN KEY FK_B63A003C5ED23C43');
        $this->addSql('ALTER TABLE document_track_effects DROP FOREIGN KEY FK_B63A003C80688E6F');
        $this->addSql('DROP TABLE document_track_effects');
        $this->addSql('ALTER TABLE effect_settings ADD track_id INT NOT NULL, ADD position INT NOT NULL');
        $this->addSql('ALTER TABLE effect_settings ADD CONSTRAINT FK_D78A065B5ED23C43 FOREIGN KEY (track_id) REFERENCES document_tracks (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_D78A065B5ED23C43 ON effect_settings (track_id)');
    }
}
