<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\EffectSettingsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EffectSettingsRepository::class)]
#[ORM\Table(name: 'effect_settings')]
class EffectSettings
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $name = '';

    #[ORM\Column(type: 'json')]
    private array $config = [];

    #[ORM\OneToMany(
        targetEntity: EffectSettingsKeyValue::class,
        mappedBy: 'effectSettings',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $keysValues;

    public function __construct()
    {
        $this->keysValues = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self
    {
        $this->name = trim($name);
        return $this;
    }

    public function getConfig(): array { return $this->config; }

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

    public function __toString(): string
    {
        return $this->name ?: ('Effect #' . $this->id);
    }

    /** @return Collection<int, EffectSettingsKeyValue> */
    public function getKeysValues(): Collection
    {
        return $this->keysValues;
    }

    public function addKeyValue(EffectSettingsKeyValue $kv): void
    {
        if (!$this->keysValues->contains($kv)) {
            $this->keysValues->add($kv);
            $kv->setEffectSettings($this); // owning side sync
        }
    }

    public function removeKeyValue(EffectSettingsKeyValue $kv): void
    {
        if ($this->keysValues->removeElement($kv)) {
            $kv->setEffectSettings(null); // triggers orphanRemoval
        }
    }
}
