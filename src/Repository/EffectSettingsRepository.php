<?php
declare(strict_types=1);
namespace App\Repository;

use App\Entity\EffectSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EffectSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EffectSettings::class);
    }

    /**
     * Optioneel: alle effecten voor een track ophalen
     */
    public function findByTrackOrdered(int $trackId): array
    {
        return $this
            ->createQueryBuilder('e')
            ->andWhere('e.track = :track')
            ->setParameter('track', $trackId)
            ->orderBy('e.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
