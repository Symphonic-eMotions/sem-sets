<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\DocumentTrackRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DocumentTrackRepository::class)]
#[ORM\Table(name: 'document_tracks')]
class DocumentTrack
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    // Elke track hoort bij precies één Document
    #[ORM\ManyToOne(targetEntity: Document::class, inversedBy: 'tracks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Document $document = null;

    #[ORM\Column(type: 'string', length: 50, unique: false, nullable: true)]
    private ?string $trackId = null;

    // Levels vanuit je JSON: ints >= 0
    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $levels = [];

    /**
     * Loop-lengtes in maten, bv. [100] of [48,48] of [32,32,32].
     *
     * Wordt in de DB als JSON opgeslagen.
     *
     * @var int[]
     */
    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $loopLength = [];

    // Eén (optionele) MIDI-asset per track
    #[ORM\ManyToOne(targetEntity: Asset::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Asset $midiAsset = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $exsPreset = null;

    // UI-volgorde voor netjes ordenen in formulieren
    #[ORM\Column(type: 'smallint', options: ['unsigned' => true, 'default' => 0])]
    private int $position = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int { return $this->id; }

    public function getDocument(): ?Document { return $this->document ?? null; }
    public function setDocument(?Document $d): self { $this->document = $d; return $this; }

    public function getTrackId(): ?string { return $this->trackId; }
    public function setTrackId(?string $id): self {
        $this->trackId = $id;
        return $this;
    }

    /** @return int[] */
    public function getLevels(): array { return $this->levels; }
    /** @param int[] $levels */
    public function setLevels(array $levels): self {
        $this->levels = array_values(array_map(static fn($v)=> (int)$v, $levels));
        return $this;
    }

    /**
     * @return int[]
     */
    public function getLoopLength(): array
    {
        return $this->loopLength;
    }

    /**
     * Accepteert zowel een string (bijv. "[48,48]" of "48,48")
     * als een array, en normaliseert naar int[].
     *
     * @param string|array<int,mixed>|null $value
     */
    public function setLoopLength(string|array|null $value): self
    {
        if ($value === null || $value === '') {
            $this->loopLength = [];
            return $this;
        }

        // String-invoer: uit formulier / JS
        if (is_string($value)) {
            $raw = trim($value);

            if ($raw === '') {
                $this->loopLength = [];
                return $this;
            }

            // Probeer JSON-array, bv. "[48,48]"
            if (str_starts_with($raw, '[')) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $value = $decoded;
                } else {
                    // fallback naar CSV
                    $value = explode(',', $raw);
                }
            } else {
                // CSV-vorm: "48,48"
                $value = explode(',', $raw);
            }
        }

        if (!is_array($value)) {
            $this->loopLength = [];
            return $this;
        }

        // Normaliseer naar int[] en filter alles ← 0 eruit
        $normalized = array_values(
            array_filter(
                array_map(
                    static fn ($v) => (int) $v,
                    $value
                ),
                static fn (int $v): bool => $v > 0
            )
        );

        $this->loopLength = $normalized;

        return $this;
    }

    public function getExsPreset(): ?string
    {
        return $this->exsPreset;
    }
    public function setExsPreset(?string $exsPreset): self
    {
        $this->exsPreset = $exsPreset;
        return $this;
    }

    public function getMidiAsset(): ?Asset { return $this->midiAsset; }
    public function setMidiAsset(?Asset $a): self { $this->midiAsset = $a; return $this; }

    public function getPosition(): int { return $this->position; }
    public function setPosition(int $p): self { $this->position = $p; return $this; }

    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): DateTimeImmutable { return $this->updatedAt; }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void { $this->updatedAt = new DateTimeImmutable(); }
}
