<?php
declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'instrument_parts')]
#[ORM\HasLifecycleCallbacks]
class InstrumentPart
{
    public const TARGET_TYPE_NONE      = 'none';
    public const TARGET_TYPE_EFFECT    = 'effect';
    public const TARGET_TYPE_SEQUENCER = 'sequencer';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    // Id om gegevens terug naar de server te sturen
    #[ORM\Column(type: 'string', length: 26, unique: true)]
    private string $partId;


    #[ORM\ManyToOne(targetEntity: DocumentTrack::class, inversedBy: 'instrumentParts')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?DocumentTrack $track = null;

    /**
     * Area of interest in grid volgorde, bv [1,0,1,0]
     * @var int[]
     */
    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $areaOfInterest = [];

    /**
     * Loop-index per gridcel (0 = loop A, 1 = B, etc.).
     * Wordt als JSON-array in de DB opgeslagen, bv [0,0,1,1,...].
     *
     * @var int[]
     */
    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $loopsToGrid = [];

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'none'])]
    private string $targetType = self::TARGET_TYPE_NONE;

    #[ORM\ManyToOne(targetEntity: EffectSettingsKeyValue::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?EffectSettingsKeyValue $targetEffectParam = null;

    // Voor nu alleen "velocity", maar later kun je hier "swing", "gate", etc. aan toevoegen
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $targetSequencerParam = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $targetBinding = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $targetRangeLow = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $targetRangeHigh = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $minimalLevel = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $rampSpeed = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $rampSpeedDown = null;

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
        $this->minimalLevel   = 0.10;
        $this->rampSpeed      = 0.04;
        $this->rampSpeedDown  = 0.02;
        $this->partId = (new Ulid())->toBase32();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTrack(): ?DocumentTrack
    {
        return $this->track;
    }

    public function setTrack(?DocumentTrack $t): self
    {
        $this->track = $t;
        return $this;
    }

    /** @return int[] */
    public function getAreaOfInterest(): array
    {
        return $this->areaOfInterest;
    }

    /**
     * @param string|array<int,mixed>|null $value
     */
    public function setAreaOfInterest(string|array|null $value): self
    {
        if ($value === null || $value === '') {
            $this->areaOfInterest = [];
            return $this;
        }

        if (is_string($value)) {
            $raw = trim($value);
            if ($raw === '') {
                $this->areaOfInterest = [];
                return $this;
            }

            if (str_starts_with($raw, '[')) {
                $decoded = json_decode($raw, true);
                $value = is_array($decoded) ? $decoded : explode(',', $raw);
            } else {
                $value = explode(',', $raw);
            }
        }

        if (!is_array($value)) {
            $this->areaOfInterest = [];
            return $this;
        }

        $normalized = array_values(array_map(
            static fn($v) => (int)((int)$v === 1),
            $value
        ));

        $expected = $this->expectedAreaCount();
        if ($expected !== null) {
            if (count($normalized) === 0) {
                $normalized = array_fill(0, $expected, 1);
            } elseif (count($normalized) > $expected) {
                $normalized = array_slice($normalized, 0, $expected);
            } elseif (count($normalized) < $expected) {
                $normalized = array_merge($normalized, array_fill(0, $expected - count($normalized), 0));
            }
        }

        $this->areaOfInterest = $normalized;
        return $this;
    }

    private function expectedAreaCount(): ?int
    {
        $doc = $this->track?->getDocument();
        if (!$doc) {
            return null;
        }

        $cols = $doc->getGridColumns();
        $rows = $doc->getGridRows();
        $n = (int)($cols * $rows);

        return $n > 0 ? $n : 1;
    }

    /** @return int[] */
    public function getLoopsToGrid(): array
    {
        return $this->loopsToGrid;
    }

    /**
     * @param string|array<int,mixed>|null $value
     */
    public function setLoopsToGrid(string|array|null $value): self
    {
        if ($value === null || $value === '') {
            $this->loopsToGrid = [];
            return $this;
        }

        if (is_string($value)) {
            $raw = trim($value);
            if ($raw === '') {
                $this->loopsToGrid = [];
                return $this;
            }

            if (str_starts_with($raw, '[')) {
                $decoded = json_decode($raw, true);
                $value = is_array($decoded) ? $decoded : explode(',', $raw);
            } else {
                $value = explode(',', $raw);
            }
        }

        if (!is_array($value)) {
            $this->loopsToGrid = [];
            return $this;
        }

        // Normaliseer naar ints ≥ 0
        $normalized = array_values(array_map(
            static function ($v): int {
                $n = (int) $v;
                return $n < 0 ? 0 : $n;
            },
            $value
        ));

        $this->loopsToGrid = $normalized;

        return $this;
    }


    // --- nieuwe target-velden ---
    public function getTargetType(): string
    {
        return $this->targetType;
    }

    public function setTargetType(string $type): self
    {
        $this->targetType = $type;
        return $this;
    }

    public function getTargetEffectParam(): ?EffectSettingsKeyValue
    {
        return $this->targetEffectParam;
    }

    public function setTargetEffectParam(?EffectSettingsKeyValue $kv): self
    {
        $this->targetEffectParam = $kv;
        return $this;
    }

    public function getTargetSequencerParam(): ?string
    {
        return $this->targetSequencerParam;
    }

    public function setTargetSequencerParam(?string $param): self
    {
        $this->targetSequencerParam = $param;
        return $this;
    }

    public function getTargetBinding(): ?string
    {
        return $this->targetBinding;
    }

    public function setTargetBinding(?string $targetBinding): self
    {
        $this->targetBinding = $targetBinding;

        return $this;
    }

    public function getTargetRangeLow(): ?float
    {
        return $this->targetRangeLow;
    }

    public function setTargetRangeLow(?float $value): self
    {
        if ($value === null) {
            $this->targetRangeLow = null;
            return $this;
        }

        $this->targetRangeLow = (float) $value;
        return $this;
    }

    public function getTargetRangeHigh(): ?float
    {
        return $this->targetRangeHigh;
    }

    public function setTargetRangeHigh(?float $value): self
    {
        if ($value === null) {
            $this->targetRangeHigh = null;
            return $this;
        }

        $this->targetRangeHigh = (float) $value;
        return $this;
    }

    public function getMinimalLevel(): ?float
    {
        return $this->minimalLevel;
    }

    public function setMinimalLevel(?float $value): self
    {
        if ($value === null) {
            return $this;
        }

        $v = (float) $value;

        // Clamp 0–1
        if ($v < 0.0) {
            $v = 0.0;
        } elseif ($v > 1.0) {
            $v = 1.0;
        }

        $this->minimalLevel = $v;
        return $this;
    }

    public function getRampSpeed(): ?float
    {
        return $this->rampSpeed;
    }

    public function setRampSpeed(?float $value): self
    {
        if ($value === null) {
            return $this;
        }

        $v = (float) $value;
        if ($v < 0.0) {
            $v = 0.0;
        } elseif ($v > 1.0) {
            $v = 1.0;
        }

        $this->rampSpeed = $v;
        return $this;
    }

    public function getRampSpeedDown(): ?float
    {
        return $this->rampSpeedDown;
    }

    public function setRampSpeedDown(?float $value): self
    {
        if ($value === null) {
            return $this;
        }

        $v = (float) $value;
        if ($v < 0.0) {
            $v = 0.0;
        } elseif ($v > 1.0) {
            $v = 1.0;
        }

        $this->rampSpeedDown = $v;
        return $this;
    }


    // --- overig ---

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $p): self
    {
        $this->position = $p;
        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
