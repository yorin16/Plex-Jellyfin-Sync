<?php

namespace App\Controller;

use App\Entity\TranscodeJob;
use App\Entity\TransferJob;
use App\Message\TranscodeJobMessage;
use App\Message\TransferJobMessage;
use App\Repository\CachedMediaRepository;
use App\Repository\ProfileRepository;
use App\Repository\TranscodeJobRepository;
use App\Repository\TranscodeProfileRepository;
use App\Repository\TransferJobRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class TransferJobController extends AbstractApiController
{
    public function __construct(
        private readonly ProfileRepository $profileRepo,
        private readonly TransferJobRepository $jobRepo,
        private readonly TranscodeJobRepository $transcodeJobRepo,
        private readonly CachedMediaRepository $mediaRepo,
        private readonly TranscodeProfileRepository $transcodeProfileRepo,
        private readonly MessageBusInterface $bus,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/profiles/{id}/jobs', methods: ['GET'])]
    public function index(int $id, Request $request): JsonResponse
    {
        $profile = $this->profileRepo->find($id);
        if (!$profile) {
            return $this->notFound();
        }

        $statusParam = $request->query->get('status');
        $statuses = $statusParam
            ? explode(',', $statusParam)
            : [TransferJob::STATUS_QUEUED, TransferJob::STATUS_RUNNING, TransferJob::STATUS_COMPLETED, TransferJob::STATUS_FAILED, TransferJob::STATUS_CANCELLED];

        $jobs = $this->jobRepo->findByProfileAndStatuses($profile, $statuses);
        return $this->json(array_map(fn($j) => $this->jobToArray($j), $jobs));
    }

    #[Route('/profiles/{id}/jobs', methods: ['POST'])]
    public function queue(int $id, Request $request): JsonResponse
    {
        $profile = $this->profileRepo->find($id);
        if (!$profile) {
            return $this->notFound();
        }

        $data     = json_decode($request->getContent(), true) ?? [];
        $mediaIds = $data['mediaIds'] ?? [];

        if (empty($mediaIds) || !is_array($mediaIds)) {
            return $this->badRequest('mediaIds array is required');
        }

        $transcodeProfileId = isset($data['transcodeProfileId']) ? (int) $data['transcodeProfileId'] : null;
        $transcodeProfile   = $transcodeProfileId ? $this->transcodeProfileRepo->find($transcodeProfileId) : null;

        $created = [];

        foreach ($mediaIds as $mediaId) {
            $media = $this->mediaRepo->find((int) $mediaId);
            if (!$media) {
                continue;
            }

            if ($transcodeProfile !== null) {
                $job = new TranscodeJob();
                $job->setProfile($profile);
                $job->setSourceMedia($media);
                $job->setTranscodeProfile($transcodeProfile);
                $this->em->persist($job);
                $this->em->flush();

                $this->bus->dispatch(new TranscodeJobMessage($job->getId()));
                $created[] = $this->transcodeJobToArray($job);
            } else {
                $job = new TransferJob();
                $job->setProfile($profile);
                $job->setSourceMedia($media);
                $this->em->persist($job);
                $this->em->flush();

                $this->bus->dispatch(new TransferJobMessage($job->getId()));
                $created[] = $this->jobToArray($job);
            }
        }

        return $this->json($created, 201);
    }

    #[Route('/profiles/{id}/jobs/finished', methods: ['DELETE'])]
    public function clearFinished(int $id): JsonResponse
    {
        $profile = $this->profileRepo->find($id);
        if (!$profile) {
            return $this->notFound();
        }

        $deleted = $this->jobRepo->deleteFinishedByProfile($profile);
        return $this->json(['deleted' => $deleted]);
    }

    #[Route('/jobs/{id}', methods: ['DELETE'])]
    public function cancel(int $id): JsonResponse
    {
        $job = $this->jobRepo->find($id);
        if (!$job) {
            return $this->notFound();
        }

        if ($job->getStatus() === TransferJob::STATUS_QUEUED) {
            $job->setStatus(TransferJob::STATUS_CANCELLED);
            $this->em->flush();
        }

        return $this->json($this->jobToArray($job));
    }
}
