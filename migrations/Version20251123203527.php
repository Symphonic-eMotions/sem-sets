<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251123203527 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE effect_settings_key_value (id INT AUTO_INCREMENT NOT NULL, effect_settings_id INT NOT NULL, type VARCHAR(32) NOT NULL, key_name VARCHAR(120) NOT NULL, value VARCHAR(255) DEFAULT NULL, INDEX IDX_2CA4240A5D9FC90E (effect_settings_id), UNIQUE INDEX uniq_effect_key_type (effect_settings_id, key_name, type), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE effect_settings_key_value ADD CONSTRAINT FK_2CA4240A5D9FC90E FOREIGN KEY (effect_settings_id) REFERENCES effect_settings (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE effect_settings_key_value DROP FOREIGN KEY FK_2CA4240A5D9FC90E');
        $this->addSql('DROP TABLE effect_settings_key_value');
    }
}
