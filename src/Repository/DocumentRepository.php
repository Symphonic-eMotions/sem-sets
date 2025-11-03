<?php
declare(strict_types=1);
namespace App\Repository;
use App\Entity\Document;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class DocumentRepository extends ServiceEntityRepository
{
    private Document $document;
    private int $versionNr;
    private string $jsonText;
    private ?User $author;
    private ?string $changelog;

    public function __construct(ManagerRegistry $r) { parent::__construct($r, Document::class); }

    /** @return Document[] */
    public function findPublished(): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.published = 1')
            ->orderBy('d.updatedAt', 'DESC')
            ->getQuery()->getResult();
    }

    public function findOnePublishedBySlug(string $slug): ?Document
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.slug = :slug')
            ->andWhere('d.published = 1')
            ->setParameter('slug', $slug)
            ->getQuery()->getOneOrNullResult();
    }

    public function setDocument(Document $d): self { $this->document = $d; return $this; }
    public function setVersionNr(int $n): self { $this->versionNr = $n; return $this; }
    public function setJsonText(string $t): self { $this->jsonText = $t; return $this; }
    public function setAuthor(?User $u): self { $this->author = $u; return $this; }
    public function setChangelog(?string $c): self { $this->changelog = $c; return $this; }

    public function getVersionNr(): int { return $this->versionNr; }
    public function getJsonText(): string { return $this->jsonText; }
}
