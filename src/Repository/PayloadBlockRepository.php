<?php
namespace App\Repository;

use App\Entity\PayloadBlock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class PayloadBlockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PayloadBlock::class);
    }

    public function findOneByName(string $name): ?PayloadBlock
    {
        return $this->findOneBy(['name' => $name]);
    }
}
