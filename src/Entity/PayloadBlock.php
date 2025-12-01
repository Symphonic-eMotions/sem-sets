<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\PayloadBlockRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PayloadBlockRepository::class)]
#[ORM\Table(name: 'payload_blocks')]
class PayloadBlock
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    // Unieke naam zoals "midiFile.v1", "instrument.v1", etc.
    #[ORM\Column(type: 'string', length: 120, unique: true)]
    private string $name;

    // Vrij veld: bijv. "Host v1 MIDI file blokje"
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $description = null;

    /**
     * Canonieke payload-structuur voor dit blok.
     * Dit is wat uiteindelijk naar JSON gaat (voor merge/override).
     *
     * Voorbeeld:
     * [
     *   "loopLength"   => [],
     *   "loopsToGrid"  => [],
     *   "loopsToLevel" => [],
     *   "midiFileExt"  => null,
     *   "midiFileName" => null,
     *   "deprecatedFoo" => null,
     * ]
     */
    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $payload = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    public function __construct(string $name)
    {
        $this->name = $name;
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->payload = [];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /** @return array<string,mixed> */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /** @param array<string,mixed> $payload */
    public function setPayload(array $payload): self
    {
        $this->payload = $payload;
        return $this;
    }

    public function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
