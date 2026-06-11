<?php

namespace App\Controller;

use App\Entity\TranscodeJob;
use App\Repository\ProfileRepository;
use App\Repository\TranscodeJobRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class TranscodeJobController extends AbstractApiController
{
    public function __construct(
        private readonly ProfileRepository $profileRepo,
        private readonly TranscodeJobRepository $jobRepo,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/profiles/{id}/transcode-jobs', methods: ['GET'])]
    public function index(int $id, Request $request): JsonResponse
    {
        $profile = $this->profileRepo->find($id);
        if (!$profile) {
            return $this->notFound();
        }

        $statusParam = $request->query->get('status');
        $statuses = $statusParam
            ? explode(',', $statusParam)
            : [TranscodeJob::STATUS_QUEUED, TranscodeJob::STATUS_RUNNING, TranscodeJob::STATUS_COMPLETED, TranscodeJob::STATUS_FAILED, TranscodeJob::STATUS_CANCELLED];

        $jobs = $this->jobRepo->findByProfileAndStatuses($profile, $statuses);
        return $this->json(array_map(fn($j) => $this->transcodeJobToArray($j), $jobs));
    }

    #[Route('/transcode-jobs/{id}', methods: ['DELETE'])]
    public function cancel(int $id): JsonResponse
    {
        $job = $this->jobRepo->find($id);
        if (!$job) {
            return $this->notFound();
        }

        if ($job->getStatus() === TranscodeJob::STATUS_QUEUED) {
            $job->setStatus(TranscodeJob::STATUS_CANCELLED);
            $this->em->flush();
        }

        return $this->json($this->transcodeJobToArray($job));
    }

    #[Route('/profiles/{id}/transcode-jobs/finished', methods: ['DELETE'])]
    public function clearFinished(int $id): JsonResponse
    {
        $profile = $this->profileRepo->find($id);
        if (!$profile) {
            return $this->notFound();
        }

        $deleted = $this->jobRepo->deleteFinishedByProfile($profile);
        return $this->json(['deleted' => $deleted]);
    }
}
