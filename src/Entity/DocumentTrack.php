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
    private Document $document;

    #[ORM\Column(type: 'string', length: 50, unique: false, nullable: true)]
    private ?string $trackId = null;

    // Levels vanuit je JSON: ints >= 0
    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $levels = [];

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
