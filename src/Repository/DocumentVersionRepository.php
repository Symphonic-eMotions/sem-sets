<?php
declare(strict_types=1);
namespace App\Repository;
use App\Entity\Document;
use App\Entity\DocumentVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class DocumentVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $r){ parent::__construct($r, DocumentVersion::class); }

    public function nextVersionNr(Document $doc): int
    {
        $max = (int) $this->createQueryBuilder('v')
            ->select('MAX(v.versionNr)')
            ->andWhere('v.document = :doc')
            ->setParameter('doc', $doc)
            ->getQuery()->getSingleScalarResult();
        return $max + 1;
    }
}