<?php
declare(strict_types=1);
namespace App\Repository;
use App\Entity\Asset;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class AssetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $r){ parent::__construct($r, Asset::class); }
}