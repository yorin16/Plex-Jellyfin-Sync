<?php

namespace App\Repository;

use App\Entity\IgnoredItem;
use App\Entity\Profile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<IgnoredItem>
 */
class IgnoredItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IgnoredItem::class);
    }

    /** @return int[] source_media_ids that are ignored for this profile */
    public function findIgnoredSourceIds(Profile $profile): array
    {
        $rows = $this->createQueryBuilder('i')
            ->select('IDENTITY(i.sourceMedia) AS id')
            ->where('i.profile = :profile')
            ->setParameter('profile', $profile)
            ->getQuery()
            ->getArrayResult();

        return array_map(fn($r) => (int) $r['id'], $rows);
    }
}
