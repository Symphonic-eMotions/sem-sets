<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\DocumentTrackRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DocumentTrackRepository::class)]
#[ORM\Table(name: 'document_tracks')]
#[ORM\HasLifecycleCallbacks]
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
     * Loop-lengtes (in kwartnoten), bv. [['offset' => 0, 'length' => 192], ...].
     * Legacy was een array van maten bv [48, 48].
     * Wordt in de DB als JSON opgeslagen.
     * @var array[]
     */
    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $loopLength = [];

    /**
     * Override voor berekende loop length
     */
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $loopLengthOverride = null;

    // Eén (optionele) MIDI-asset per track
    #[ORM\ManyToOne(targetEntity: Asset::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Asset $midiAsset = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $exsPreset = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $tonePreset = null;

    #[ORM\Column(type: 'float', options: ['default' => 0])]
    private float $trackVolume = 0.0;

    #[ORM\OneToMany(
        targetEntity: DocumentTrackEffect::class,
        mappedBy: 'track',
        cascade: ['persist','remove'],
        orphanRemoval: true
    )]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $trackEffects;

    #[ORM\OneToMany(
        targetEntity: InstrumentPart::class,
        mappedBy: 'track',
        cascade: ['persist','remove'],
        orphanRemoval: true
    )]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $instrumentParts;

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

        $this->instrumentParts = new ArrayCollection();
        $this->trackEffects = new ArrayCollection();
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

    /** @return array[] */
    public function getLoopLength(): array
    {
        $raw = $this->loopLength;
        if (empty($raw)) {
            return [];
        }

        // Controleer of de JSON al het nieuwe formatter heeft (objecten met 'offset')
        if (isset($raw[0]) && is_array($raw[0]) && array_key_exists('offset', $raw[0])) {
            return $raw;
        }

        // Oude formaat: array van ints (maten). Migreer deze on-the-fly naar kwartnoten (beats).
        $beatsPerBar = 4;
        if ($this->getDocument()) {
            $beatsPerBar = $this->getDocument()->getTimeSignatureNumerator() ?? 4;
        }

        $migrated = [];
        $currentOffsetQuarters = 0;

        foreach ($raw as $bars) {
            $quarterNotes = (int)$bars * $beatsPerBar;
            if ($quarterNotes > 0) {
                $migrated[] = [
                    'offset' => $currentOffsetQuarters,
                    'length' => $quarterNotes,
                ];
                $currentOffsetQuarters += $quarterNotes;
            }
        }

        return $migrated;
    }

    /**
     * Accepteert zowel JSON array van objecten, als in sommige unieke 
     * gevallen nog een CSV / array fallback (die wordt omgezet).
     *
     * @param string|array|null $value
     */
    public function setLoopLength(string|array|null $value): self
    {
        if ($value === null || $value === '') {
            $this->loopLength = [];
            return $this;
        }

        if (is_string($value)) {
            $raw = trim($value);

            if ($raw === '') {
                $this->loopLength = [];
                return $this;
            }

            if (str_starts_with($raw, '[')) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $value = $decoded;
                } else {
                    $value = explode(',', $raw);
                }
            } else {
                $value = explode(',', $raw);
            }
        }

        if (!is_array($value)) {
            $this->loopLength = [];
            return $this;
        }

        $normalized = [];
        $beatsPerBar = 4;
        if ($this->getDocument()) {
            $beatsPerBar = $this->getDocument()->getTimeSignatureNumerator() ?? 4;
        }

        $currentFallbackOffset = 0;

        foreach ($value as $item) {
            if (is_array($item) && isset($item['offset'], $item['length'])) {
                // Nieuw formaat item
                $lengthVal = (int) $item['length'];
                if ($lengthVal > 0) {
                    $normalized[] = [
                        'offset' => max(0, (int) $item['offset']),
                        'length' => $lengthVal,
                    ];
                }
            } else {
                // Oud format (maten CSV) ingetypt
                $bars = (int) $item;
                $quarters = $bars * $beatsPerBar;
                if ($quarters > 0) {
                    $normalized[] = [
                        'offset' => $currentFallbackOffset,
                        'length' => $quarters,
                    ];
                    $currentFallbackOffset += $quarters;
                }
            }
        }

        $this->loopLength = $normalized;

        return $this;
    }

    public function getLoopLengthOverride(): ?int
    {
        return $this->loopLengthOverride;
    }

    public function setLoopLengthOverride(?int $value): self
    {
        if ($value === null) {
            $this->loopLengthOverride = null;
            return $this;
        }

        // clamp op >= 1
        if ($value < 1) {
            $value = 1;
        }

        $this->loopLengthOverride = $value;

        return $this;
    }

    public function getTrackEffects(): Collection
    {
        return $this->trackEffects;
    }

    public function addTrackEffect(DocumentTrackEffect $e): self
    {
        if (!$this->trackEffects->contains($e)) {
            $this->trackEffects->add($e);
            $e->setTrack($this);
        }
        return $this;
    }

    public function removeTrackEffect(DocumentTrackEffect $e): self
    {
        if ($this->trackEffects->removeElement($e)) {
            if ($e->getTrack() === $this) {
                $e->setTrack(null);
            }
        }
        return $this;
    }

    public function getInstrumentParts(): Collection
    {
        return $this->instrumentParts;
    }

    public function addInstrumentPart(InstrumentPart $p): self
    {
        if (!$this->instrumentParts->contains($p)) {
            $this->instrumentParts->add($p);
            $p->setTrack($this);
        }
        return $this;
    }

    public function removeInstrumentPart(InstrumentPart $p): self
    {
        if ($this->instrumentParts->removeElement($p)) {
            if ($p->getTrack() === $this) {
                $p->setTrack(null);
            }
        }
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

    public function getTonePreset(): ?string
    {
        return $this->tonePreset;
    }

    public function setTonePreset(?string $tonePreset): self
    {
        $this->tonePreset = $tonePreset;
        return $this;
    }

    public function getTrackVolume(): float
    {
        return $this->trackVolume;
    }

    public function setTrackVolume(float $volume): self
    {
        if (!is_finite($volume)) {
            $volume = 0.0;
        }

        $this->trackVolume = max(-90.0, min(12.0, $volume));

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
