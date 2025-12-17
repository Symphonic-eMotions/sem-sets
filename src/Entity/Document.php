<?php
declare(strict_types=1);

namespace App\Entity;

use App\Enum\SemVersion;
use App\Repository\DocumentRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DocumentRepository::class)]
#[ORM\Table(name: 'documents')]
#[ORM\HasLifecycleCallbacks]
class Document
{
    private const MIN_GRID = 1;
    private const MAX_GRID = 4;
    private const MIN_BPM = 20.0;
    private const MAX_BPM = 999.99;

    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 160, unique: true)]
    private string $slug;

    #[ORM\Column(type: 'string', length: 200)]
    private string $title;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $published = false;

    #[ORM\Column(enumType: SemVersion::class)]
    private SemVersion $semVersion = SemVersion::V_2_9_0;

    #[ORM\ManyToOne(targetEntity: DocumentVersion::class)]
    #[ORM\JoinColumn(name: 'head_version_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?DocumentVersion $headVersion = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $updatedBy = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'json', options: ['comment' => 'Durations per level (ints)', 'default' => '[]'])]
    private array $levelDurations = [];

    #[ORM\Column(type: 'integer', options: ['unsigned' => true, 'default' => 1])]
    private int $gridColumns = self::MIN_GRID;

    #[ORM\Column(type: 'integer', options: ['unsigned' => true, 'default' => 1])]
    private int $gridRows = self::MIN_GRID;

    // Doctrine decimal = string in PHP
    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, options: ['unsigned' => true, 'default' => '90.00'])]
    private string $setBPM = '90.00';

    #[ORM\OneToMany(
        targetEntity: DocumentTrack::class,
        mappedBy: 'document',
        cascade: ['persist','remove'],
        orphanRemoval: true
    )]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $tracks;

    public function __construct()
    {
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;

        $this->tracks = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new DateTimeImmutable();
        $this->createdAt = $this->createdAt ?? $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    // IDs & meta
    public function getId(): ?int { return $this->id; }

    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): self { $this->slug = $slug; return $this; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }

    public function isPublished(): bool { return $this->published; }
    public function setPublished(bool $p): self { $this->published = $p; return $this; }

    public function getSemVersion(): SemVersion { return $this->semVersion; }
    public function setSemVersion(SemVersion $v): self { $this->semVersion = $v; return $this; }

    public function getHeadVersion(): ?DocumentVersion { return $this->headVersion; }
    public function setHeadVersion(?DocumentVersion $v): self { $this->headVersion = $v; return $this; }

    public function getCreatedBy(): ?User { return $this->createdBy; }
    public function setCreatedBy(?User $u): self { $this->createdBy = $u; return $this; }

    public function getUpdatedBy(): ?User { return $this->updatedBy; }
    public function setUpdatedBy(?User $u): self { $this->updatedBy = $u; return $this; }

    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(DateTimeImmutable $d): self { $this->updatedAt = $d; return $this; }

    // Level durations (ints > 0)
    public function getLevelDurations(): array { return $this->levelDurations; }

    public function setLevelDurations(array $arr): self
    {
        // force ints, keep >0, reindex
        $clean = array_values(array_filter(array_map(
            fn($v) => is_numeric($v) ? (int)$v : null,
            $arr
        ), fn($v) => is_int($v) && $v >= 0));

        // NEW: levels on set-niveau zijn altijd "aan"
        $clean = array_map(static fn($_) => 1, $clean);

        $this->levelDurations = $clean;
        return $this;
    }

    // Grid (clamp 1..4)
    public function getGridColumns(): int { return $this->gridColumns; }
    public function setGridColumns(int $n): self
    {
        $this->gridColumns = max(self::MIN_GRID, min(self::MAX_GRID, $n));
        return $this;
    }

    public function getGridRows(): int { return $this->gridRows; }
    public function setGridRows(int $n): self
    {
        $this->gridRows = max(self::MIN_GRID, min(self::MAX_GRID, $n));
        return $this;
    }

    /** decimal as string (precision 5, scale 2) */
    public function getSetBPM(): string { return $this->setBPM; }

    /**
     * @param string|int|float $bpm
     * @return Document
     */
    public function setSetBPM(string|int|float $bpm): self
    {
        $raw = is_string($bpm) ? strtr(trim($bpm), [',' => '.']) : (float)$bpm;
        $num = (float)$raw;
        $num = max(self::MIN_BPM, min(self::MAX_BPM, $num));
        $this->setBPM = number_format($num, 2, '.', '');
        return $this;
    }

    public function getTracks(): Collection { return $this->tracks;}

    public function addTrack(DocumentTrack $t): self
    {
        if (!$this->tracks->contains($t)) {
            $this->tracks->add($t);
            $t->setDocument($this);
        }
        return $this;
    }

    public function removeTrack(DocumentTrack $t): self
    {
        if ($this->tracks->removeElement($t)) {
            if ($t->getDocument() === $this) {
                $t->setDocument(null);
            }
        }
        return $this;
    }
}
