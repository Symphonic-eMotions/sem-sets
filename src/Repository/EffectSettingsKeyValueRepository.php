<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\EffectSettingsKeyValue;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class EffectSettingsKeyValueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EffectSettingsKeyValue::class);
    }

    /**
     * Haal alle parameter-key-values (dus alleen type=parameter)
     * gesorteerd op effect name + key name.
     */
    public function findAllParams(): array
    {
        return $this->createQueryBuilder('kv')
            ->andWhere('kv.type = :type')
            ->setParameter('type', EffectSettingsKeyValue::TYPE_PARAM)
            ->orderBy('kv.keyName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Haal alle keys bij een bepaald effect (optioneel handig)
     */
    public function findByEffect(int $effectSettingsId): array
    {
        return $this->createQueryBuilder('kv')
            ->andWhere('kv.effectSettings = :id')
            ->setParameter('id', $effectSettingsId)
            ->orderBy('kv.keyName', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
