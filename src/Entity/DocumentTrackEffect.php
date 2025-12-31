<?php
declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'document_track_effects')]
class DocumentTrackEffect
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'trackEffects')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?DocumentTrack $track = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?EffectSettings $preset = null;

    /**
     * Track-specifieke parameter values voor dit effect.
     * Structuur (full copy): ["cutoffFrequency" => ["value" => 12000], "resonance" => ["value" => -6]]
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $overrides = null;

    #[ORM\Column(type: 'integer')]
    private int $position = 0;

    public function getId(): ?int { return $this->id; }

    public function getTrack(): ?DocumentTrack { return $this->track; }
    public function setTrack(?DocumentTrack $track): self
    {
        $this->track = $track;
        return $this;
    }

    public function getPreset(): ?EffectSettings { return $this->preset; }
    public function setPreset(?EffectSettings $preset): self
    {
        $this->preset = $preset;
        return $this;
    }

    public function getOverrides(): ?array
    {
        return $this->overrides;
    }

    public function setOverrides(?array $overrides): self
    {
        $this->overrides = $overrides;
        return $this;
    }

    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): self
    {
        $this->position = $position;
        return $this;
    }
}
