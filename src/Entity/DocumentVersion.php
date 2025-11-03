<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\DocumentVersionRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DocumentVersionRepository::class)]
#[ORM\Table(name: 'document_versions')]
class DocumentVersion
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Document::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Document $document;

    #[ORM\Column(type: 'integer')]
    private int $versionNr;

    #[ORM\Column(type: 'text', options: ['comment' => 'JSON snapshot'])]
    private string $jsonText;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $author = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $changelog = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function setDocument(Document $d): self { $this->document = $d; return $this; }
    public function setVersionNr(int $n): self { $this->versionNr = $n; return $this; }
    public function setJsonText(string $t): self { $this->jsonText = $t; return $this; }
    public function setAuthor(?User $u): self { $this->author = $u; return $this; }
    public function setChangelog(?string $c): self { $this->changelog = $c; return $this; }

    public function getVersionNr(): int { return $this->versionNr; }
    public function getJsonText(): string { return $this->jsonText; }
}
