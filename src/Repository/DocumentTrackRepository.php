<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\Document;
use App\Entity\DocumentTrack;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class DocumentTrackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $r) { parent::__construct($r, DocumentTrack::class); }

    /** @return DocumentTrack[] */
    public function findByDocument(Document $doc): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.document = :doc')->setParameter('doc', $doc)
            ->orderBy('t.position', 'ASC')
            ->getQuery()->getResult();
    }
}
