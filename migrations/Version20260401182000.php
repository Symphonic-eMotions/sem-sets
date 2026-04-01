<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260401182000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add track_volume to document_tracks and remove legacy instrumentVolume from niveauTrack payload block';
    }

    public function up(Schema $schema): void
    {
        if (
            $schema->hasTable('document_tracks')
            && !$schema->getTable('document_tracks')->hasColumn('track_volume')
        ) {
            $this->addSql(
                "ALTER TABLE document_tracks ADD track_volume DOUBLE PRECISION DEFAULT 0 NOT NULL COMMENT 'Volume in dB (-90 to 12)'"
            );
        }

        $row = $this->connection->fetchAssociative(
            "SELECT id, payload FROM payload_blocks WHERE name = 'niveauTrack' LIMIT 1"
        );

        if (!$row) {
            return;
        }

        $payloadRaw = $row['payload'] ?? null;
        if (!is_string($payloadRaw) || trim($payloadRaw) === '') {
            return;
        }

        $decoded = json_decode($payloadRaw, true);
        if (!is_array($decoded)) {
            return;
        }

        if (!array_key_exists('instrumentVolume', $decoded)) {
            return;
        }

        unset($decoded['instrumentVolume']);

        $json = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        if (!is_string($json)) {
            return;
        }

        $this->addSql(
            'UPDATE payload_blocks SET payload = :payload WHERE id = :id',
            [
                'payload' => $json,
                'id' => (int) $row['id'],
            ]
        );
    }

    public function down(Schema $schema): void
    {
        if (
            $schema->hasTable('document_tracks')
            && $schema->getTable('document_tracks')->hasColumn('track_volume')
        ) {
            $this->addSql('ALTER TABLE document_tracks DROP track_volume');
        }

        $row = $this->connection->fetchAssociative(
            "SELECT id, payload FROM payload_blocks WHERE name = 'niveauTrack' LIMIT 1"
        );

        if (!$row) {
            return;
        }

        $payloadRaw = $row['payload'] ?? null;
        if (!is_string($payloadRaw) || trim($payloadRaw) === '') {
            return;
        }

        $decoded = json_decode($payloadRaw, true);
        if (!is_array($decoded)) {
            return;
        }

        if (array_key_exists('instrumentVolume', $decoded)) {
            return;
        }

        $decoded['instrumentVolume'] = 1.0;

        $json = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        if (!is_string($json)) {
            return;
        }

        $this->addSql(
            'UPDATE payload_blocks SET payload = :payload WHERE id = :id',
            [
                'payload' => $json,
                'id' => (int) $row['id'],
            ]
        );
    }
}
