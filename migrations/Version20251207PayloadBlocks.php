<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use DateTimeImmutable;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use JsonException;

final class Version20251207PayloadBlocks extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed initial payload_blocks (niveauTrack, niveauSet, masterEffects)';
    }

    /**
     * @throws JsonException
     */
    public function up(Schema $schema): void
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        // 1) niveauTrack
        $payloadNiveauTrack = [
            'midiGroup'          => [],
            'muted'              => false,
            'noteNumbersClips'   => [],
            'noteSource'         => 'midiFile',
            'notesSequenceType'  => 'firstNote',
            'notesToGrid'        => [],
            'notesToLevel'       => [],
            'startType'          => 'loopedTransport',
            'instrumentColor'    => 'InstrumentColor000',
            'instrumentVolume'   => 1.0,
        ];

        $this->insertOrUpdatePayloadBlock(
            name: 'niveauTrack',
            description: 'Alle niet in de editor aanwezige nodes op InstrumentTrack niveau',
            payload: $payloadNiveauTrack,
            createdAt: $now,
            updatedAt: $now
        );

        // 2) niveauSet
        $payloadNiveauSet = [
            'hasTempo'          => true,
            'setTimeSignature'  => 4,
            'customName'        => '',
            'defaultSkin'       => 'swiftUI',
            'fileGroup'         => 'pro',
            'setPath'           => '',
            'skin'              => null,
        ];

        $this->insertOrUpdatePayloadBlock(
            name: 'niveauSet',
            description: 'Set level settings',
            payload: $payloadNiveauSet,
            createdAt: $now,
            updatedAt: $now
        );

        // 3) masterEffects
        $payloadMasterEffects = [
            'masterTrackEffects' => [[
                'attackTime' => [
                    'range' => [0.0001, 0.2],
                    'value' => 0.0010000000474974513,
                ],
                'effectName' => 'compressor',
                'headRoom' => [
                    'range' => [0.1, 40],
                    'value' => 4,
                ],
                'masterGain' => [
                    'range' => [-40, 40],
                    'value' => 9.9644136428833008,
                ],
                'releaseTime' => [
                    'range' => [0.01, 3],
                    'value' => 0.05000000074505806,
                ],
                'threshold' => [
                    'range' => [-40, 20],
                    'value' => -20,
                ],
            ]],
        ];

        $this->insertOrUpdatePayloadBlock(
            name: 'masterEffects',
            description: 'Set level signal processing',
            payload: $payloadMasterEffects,
            createdAt: $now,
            updatedAt: $now
        );
    }

    public function down(Schema $schema): void
    {
        // Als je bij rollback deze drie wilt verwijderen:
        $this->addSql("DELETE FROM payload_blocks WHERE name IN ('niveauTrack', 'niveauSet', 'masterEffects')");
    }

    /**
     * Helper om een payload_block te "upserten" op basis van de unieke naam.
     *
     * @param array<string,mixed> $payload
     * @throws JsonException
     */
    private function insertOrUpdatePayloadBlock(
        string $name,
        ?string $description,
        array $payload,
        string $createdAt,
        string $updatedAt
    ): void {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);

        // MySQL/MariaDB: ON DUPLICATE KEY UPDATE op unieke "name"
        $this->addSql(
            <<<SQL
INSERT INTO payload_blocks (name, description, payload, created_at, updated_at)
VALUES (:name, :description, :payload, :created_at, :updated_at)
ON DUPLICATE KEY UPDATE
    description = VALUES(description),
    payload     = VALUES(payload),
    updated_at  = VALUES(updated_at)
SQL,
            [
                'name'        => $name,
                'description' => $description,
                'payload'     => $json,
                'created_at'  => $createdAt,
                'updated_at'  => $updatedAt,
            ]
        );
    }
}
