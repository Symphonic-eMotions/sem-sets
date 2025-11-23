<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\EffectSettingsRepository;
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
}
