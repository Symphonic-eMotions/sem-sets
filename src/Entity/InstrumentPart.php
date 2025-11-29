<?php
declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

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

    #[ORM\ManyToOne(targetEntity: DocumentTrack::class, inversedBy: 'instrumentParts')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?DocumentTrack $track = null;

    /**
     * Area of interest in grid volgorde, bv [1,0,1,0]
     * @var int[]
     */
    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $areaOfInterest = [];

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
