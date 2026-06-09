<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

abstract class AbstractApiController extends AbstractController
{
    protected function json(mixed $data, int $status = 200, array $headers = [], array $context = []): JsonResponse
    {
        return parent::json($data, $status, $headers, $context);
    }

    protected function notFound(string $message = 'Not found'): JsonResponse
    {
        return $this->json(['error' => $message], 404);
    }

    protected function badRequest(string $message): JsonResponse
    {
        return $this->json(['error' => $message], 400);
    }

    protected function mediaToArray(\App\Entity\CachedMedia $m): array
    {
        return [
            'id'         => $m->getId(),
            'title'      => $m->getTitle(),
            'year'       => $m->getYear(),
            'tmdbId'     => $m->getTmdbId(),
            'imdbId'     => $m->getImdbId(),
            'path'       => $m->getPath(),
            'fileSize'   => $m->getFileSize(),
            'codec'      => $m->getCodec(),
            'resolution' => $m->getResolution(),
            'hdrType'    => $m->getHdrType(),
        ];
    }

    protected function jobToArray(\App\Entity\TransferJob $job): array
    {
        return [
            'id'               => $job->getId(),
            'profileId'        => $job->getProfile()->getId(),
            'sourceMedia'      => $this->mediaToArray($job->getSourceMedia()),
            'status'           => $job->getStatus(),
            'bytesCopied'      => $job->getBytesCopied(),
            'totalBytes'       => $job->getTotalBytes(),
            'progressPercent'  => $job->getProgressPercent(),
            'transferSpeedBps' => $job->getTransferSpeedBps(),
            'errorMessage'     => $job->getErrorMessage(),
            'queuedAt'         => $job->getQueuedAt()->format(\DateTimeInterface::ATOM),
            'startedAt'        => $job->getStartedAt()?->format(\DateTimeInterface::ATOM),
            'completedAt'      => $job->getCompletedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
