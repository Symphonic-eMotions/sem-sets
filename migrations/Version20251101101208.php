<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251101101208 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE assets (id INT AUTO_INCREMENT NOT NULL, document_id INT NOT NULL, created_by_id INT DEFAULT NULL, original_name VARCHAR(255) NOT NULL, mime_type VARCHAR(127) NOT NULL, size INT UNSIGNED NOT NULL, storage_path VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_79D17D8EC33F7837 (document_id), INDEX IDX_79D17D8EB03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE document_versions (id INT AUTO_INCREMENT NOT NULL, document_id INT NOT NULL, author_id INT DEFAULT NULL, version_nr INT NOT NULL, json_text LONGTEXT NOT NULL COMMENT \'JSON snapshot\', changelog VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_961DB18BC33F7837 (document_id), INDEX IDX_961DB18BF675F31B (author_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE documents (id INT AUTO_INCREMENT NOT NULL, head_version_id INT DEFAULT NULL, created_by_id INT DEFAULT NULL, updated_by_id INT DEFAULT NULL, slug VARCHAR(160) NOT NULL, title VARCHAR(200) NOT NULL, published TINYINT(1) NOT NULL, sem_version VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_A2B07288989D9B62 (slug), INDEX IDX_A2B07288E95B59FA (head_version_id), INDEX IDX_A2B07288B03A8386 (created_by_id), INDEX IDX_A2B07288896DBBDE (updated_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, roles JSON NOT NULL, is_active TINYINT(1) NOT NULL, failed_login_attempts SMALLINT UNSIGNED NOT NULL, last_failed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE assets ADD CONSTRAINT FK_79D17D8EC33F7837 FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE assets ADD CONSTRAINT FK_79D17D8EB03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE document_versions ADD CONSTRAINT FK_961DB18BC33F7837 FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE document_versions ADD CONSTRAINT FK_961DB18BF675F31B FOREIGN KEY (author_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE documents ADD CONSTRAINT FK_A2B07288E95B59FA FOREIGN KEY (head_version_id) REFERENCES document_versions (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE documents ADD CONSTRAINT FK_A2B07288B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE documents ADD CONSTRAINT FK_A2B07288896DBBDE FOREIGN KEY (updated_by_id) REFERENCES users (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE assets DROP FOREIGN KEY FK_79D17D8EC33F7837');
        $this->addSql('ALTER TABLE assets DROP FOREIGN KEY FK_79D17D8EB03A8386');
        $this->addSql('ALTER TABLE document_versions DROP FOREIGN KEY FK_961DB18BC33F7837');
        $this->addSql('ALTER TABLE document_versions DROP FOREIGN KEY FK_961DB18BF675F31B');
        $this->addSql('ALTER TABLE documents DROP FOREIGN KEY FK_A2B07288E95B59FA');
        $this->addSql('ALTER TABLE documents DROP FOREIGN KEY FK_A2B07288B03A8386');
        $this->addSql('ALTER TABLE documents DROP FOREIGN KEY FK_A2B07288896DBBDE');
        $this->addSql('DROP TABLE assets');
        $this->addSql('DROP TABLE document_versions');
        $this->addSql('DROP TABLE documents');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
