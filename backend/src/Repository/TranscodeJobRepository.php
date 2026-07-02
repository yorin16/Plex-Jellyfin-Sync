<?php

namespace App\Repository;

use App\Entity\Profile;
use App\Entity\TranscodeJob;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TranscodeJob>
 */
class TranscodeJobRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TranscodeJob::class);
    }

    /** @return TranscodeJob[] */
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

    /** @return int[] source_media_ids with an active (queued/running) transcode job for this profile */
    public function findActiveSourceIds(Profile $profile): array
    {
        $rows = $this->createQueryBuilder('j')
            ->select('IDENTITY(j.sourceMedia) AS id')
            ->where('j.profile = :profile')
            ->andWhere('j.status IN (:statuses)')
            ->setParameter('profile', $profile)
            ->setParameter('statuses', [TranscodeJob::STATUS_QUEUED, TranscodeJob::STATUS_RUNNING])
            ->getQuery()
            ->getArrayResult();

        return array_map(fn($r) => (int) $r['id'], $rows);
    }

    public function deleteFinishedByProfile(Profile $profile): int
    {
        return $this->createQueryBuilder('j')
            ->delete()
            ->where('j.profile = :profile')
            ->andWhere('j.status IN (:statuses)')
            ->setParameter('profile', $profile)
            ->setParameter('statuses', [TranscodeJob::STATUS_COMPLETED, TranscodeJob::STATUS_FAILED, TranscodeJob::STATUS_CANCELLED])
            ->getQuery()
            ->execute();
    }
}
