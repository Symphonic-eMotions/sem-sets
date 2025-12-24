<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251224RemoveSetTimeSignatureFromPayloadBlocks extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Remove legacy setTimeSignature default from payload_blocks (niveauSet) because it is now dynamic in Document export.";
    }

    public function up(Schema $schema): void
    {
        // 1) Lees huidige payload
        $row = $this->connection->fetchAssociative(
            "SELECT id, payload FROM payload_blocks WHERE name = 'niveauSet' LIMIT 1"
        );

        if (!$row) {
            // Niets te doen
            return;
        }

        $payloadRaw = $row['payload'] ?? null;
        if (!is_string($payloadRaw) || trim($payloadRaw) === '') {
            return;
        }

        $decoded = json_decode($payloadRaw, true);
        if (!is_array($decoded)) {
            // Als payload corrupt is, liever niks slopen
            return;
        }

        // 2) Verwijder alleen die ene key
        if (array_key_exists('setTimeSignature', $decoded)) {
            unset($decoded['setTimeSignature']);

            $json = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);

            $this->addSql(
                "UPDATE payload_blocks SET payload = :payload WHERE id = :id",
                [
                    'payload' => $json,
                    'id' => (int) $row['id'],
                ]
            );
        }
    }

    public function down(Schema $schema): void
    {
        // Down = terugzetten van de key (als je ooit rollbackt)
        $row = $this->connection->fetchAssociative(
            "SELECT id, payload FROM payload_blocks WHERE name = 'niveauSet' LIMIT 1"
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

        // Zet default teller terug (zoals je oude seed)
        if (!array_key_exists('setTimeSignature', $decoded)) {
            $decoded['setTimeSignature'] = 4;

            $json = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);

            $this->addSql(
                "UPDATE payload_blocks SET payload = :payload WHERE id = :id",
                [
                    'payload' => $json,
                    'id' => (int) $row['id'],
                ]
            );
        }
    }
}
