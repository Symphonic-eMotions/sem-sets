<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\EffectSettingsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EffectSettingsRepository::class)]
class EffectSettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'effects')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?DocumentTrack $track = null;

    #[ORM\Column(length: 120)]
    private string $name = '';

    /**
     * We slaan JSON op als array in DB.
     * Doctrine json type serialiseert/deserialiseert automatisch.
     */
    #[ORM\Column(type: 'json')]
    private array $config = [];

    /**
     * Sorteerveld zodat je effecten kunt ordenen binnen een track.
     */
    #[ORM\Column(type: 'integer')]
    private int $position = 0;

    public function getId(): ?int { return $this->id; }

    public function getTrack(): ?DocumentTrack { return $this->track; }
    public function setTrack(?DocumentTrack $track): self
    {
        $this->track = $track;
        return $this;
    }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self
    {
        $this->name = trim($name);
        return $this;
    }

    public function getConfig(): array { return $this->config; }

    /**
     * Sanity check:
     * - accepteert array (van Form / Doctrine)
     * - accepteert string met JSON (van textarea)
     * - forceert object/array root
     */
    public function setConfig(array|string|null $config): self
    {
        if ($config === null || $config === '') {
            $this->config = [];
            return $this;
        }

        if (is_string($config)) {
            $decoded = json_decode($config, true);
            if (!is_array($decoded)) {
                throw new \InvalidArgumentException('Effect config moet geldige JSON zijn.');
            }
            $config = $decoded;
        }

        // extra sanity: root moet array/object zijn
        if (!is_array($config)) {
            throw new \InvalidArgumentException('Effect config moet JSON object/array zijn.');
        }

        $this->config = $config;
        return $this;
    }

    public function getConfigAsPrettyJson(): string
    {
        return json_encode($this->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): self
    {
        $this->position = $position;
        return $this;
    }
}
