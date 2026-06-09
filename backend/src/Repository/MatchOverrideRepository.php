<?php

namespace App\Repository;

use App\Entity\MatchOverride;
use App\Entity\Profile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MatchOverride>
 */
class MatchOverrideRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MatchOverride::class);
    }

    /** @return array<int, int> source_media_id => destination_media_id */
    public function findOverrideMapForProfile(Profile $profile): array
    {
        $rows = $this->createQueryBuilder('m')
            ->select('IDENTITY(m.sourceMedia) AS src, IDENTITY(m.destinationMedia) AS dst')
            ->where('m.profile = :profile')
            ->setParameter('profile', $profile)
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['src']] = (int) $row['dst'];
        }
        return $map;
    }
}
