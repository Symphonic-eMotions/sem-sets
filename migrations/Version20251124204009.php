<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251124204009 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE instrument_parts ADD target_effect_param_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE instrument_parts ADD CONSTRAINT FK_B19A139726868C9E FOREIGN KEY (target_effect_param_id) REFERENCES effect_settings_key_value (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_B19A139726868C9E ON instrument_parts (target_effect_param_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE instrument_parts DROP FOREIGN KEY FK_B19A139726868C9E');
        $this->addSql('DROP INDEX IDX_B19A139726868C9E ON instrument_parts');
        $this->addSql('ALTER TABLE instrument_parts DROP target_effect_param_id');
    }
}
