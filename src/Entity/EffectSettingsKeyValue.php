<?php
declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'effect_settings_key_value')]
#[ORM\UniqueConstraint(name: 'uniq_effect_key_type', columns: ['effect_settings_id', 'key_name', 'type'])]
class EffectSettingsKeyValue
{
    public const TYPE_NAME = 'name';          // effectName record
    public const TYPE_PARAM = 'parameter';    // parameter record

    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: EffectSettings::class, inversedBy: 'keysValues')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?EffectSettings $effectSettings;

    #[ORM\Column(length: 32)]
    private string $type = self::TYPE_PARAM;

    #[ORM\Column(name: 'key_name', length: 120)]
    private string $keyName = '';

    // Voor TYPE_NAME: hier komt "lowPassFilter"
    // Voor TYPE_PARAM: dit kan null blijven
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $value = null;

    public function __construct(EffectSettings $effectSettings, string $type, string $keyName, ?string $value = null)
    {
        $this->effectSettings = $effectSettings;
        $this->type = $type;
        $this->keyName = $keyName;
        $this->value = $value;
    }

    public function getId(): ?int { return $this->id; }
    public function getEffectSettings(): EffectSettings { return $this->effectSettings; }

    public function setEffectSettings(?EffectSettings $effectSettings): void
    {
        if ($effectSettings === null) {
            // allow null so orphanRemoval can delete
            // property must become nullable:
            $this->effectSettings = null;
            return;
        }

        $this->effectSettings = $effectSettings;
    }

    public function getType(): string { return $this->type; }
    public function getKeyName(): string { return $this->keyName; }
    public function getValue(): ?string { return $this->value; }
}
