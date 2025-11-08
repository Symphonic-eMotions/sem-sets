<?php
declare(strict_types=1);
namespace App\Repository;
use App\Entity\Asset;
use App\Entity\Document;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class AssetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $r){ parent::__construct($r, Asset::class); }

    // src/Repository/AssetRepository.php
    public function findForDocument(Document $doc): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.document = :doc')
            ->setParameter('doc', $doc)
            ->orderBy('a.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}