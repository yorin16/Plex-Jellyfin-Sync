<?php

namespace App\Repository;

use App\Entity\CachedMedia;
use App\Entity\MediaConnection;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CachedMedia>
 */
class CachedMediaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CachedMedia::class);
    }

    /** @return CachedMedia[] */
    public function findByConnection(MediaConnection $connection): array
    {
        return $this->findBy(['connection' => $connection]);
    }

    public function findByConnectionAndExternalId(MediaConnection $connection, string $externalId): ?CachedMedia
    {
        return $this->findOneBy(['connection' => $connection, 'externalId' => $externalId]);
    }

    public function deleteStaleByConnection(MediaConnection $connection, \DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('m')
            ->delete()
            ->where('m.connection = :conn')
            ->andWhere('m.lastSeenAt < :since')
            ->setParameter('conn', $connection)
            ->setParameter('since', $since)
            ->getQuery()
            ->execute();
    }
}
