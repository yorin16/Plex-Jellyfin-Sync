<?php

namespace App\Repository;

use App\Entity\Profile;
use App\Entity\TransferJob;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TransferJob>
 */
class TransferJobRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TransferJob::class);
    }

    /** @return TransferJob[] */
    public function findByProfileAndStatuses(Profile $profile, array $statuses): array
    {
        return $this->createQueryBuilder('j')
            ->where('j.profile = :profile')
            ->andWhere('j.status IN (:statuses)')
            ->setParameter('profile', $profile)
            ->setParameter('statuses', $statuses)
            ->orderBy('j.queuedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function deleteFinishedByProfile(Profile $profile): int
    {
        return $this->createQueryBuilder('j')
            ->delete()
            ->where('j.profile = :profile')
            ->andWhere('j.status IN (:statuses)')
            ->setParameter('profile', $profile)
            ->setParameter('statuses', [TransferJob::STATUS_COMPLETED, TransferJob::STATUS_FAILED, TransferJob::STATUS_CANCELLED])
            ->getQuery()
            ->execute();
    }

    public function getDashboardCounts(): array
    {
        $rows = $this->createQueryBuilder('j')
            ->select('j.status, COUNT(j.id) AS cnt')
            ->groupBy('j.status')
            ->getQuery()
            ->getArrayResult();

        $counts = ['queued' => 0, 'running' => 0, 'completed' => 0, 'failed' => 0, 'cancelled' => 0];
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['cnt'];
        }
        return $counts;
    }
}
