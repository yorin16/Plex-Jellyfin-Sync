<?php

namespace App\MessageHandler;

use App\Entity\TranscodeJob;
use App\Entity\TransferJob;
use App\Message\TranscodeJobMessage;
use App\Message\TransferJobMessage;
use App\Repository\TranscodeJobRepository;
use App\Service\TranscodeService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class TranscodeJobHandler
{
    public function __construct(
        private readonly TranscodeJobRepository $jobRepo,
        private readonly TranscodeService $transcodeService,
        private readonly MessageBusInterface $bus,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'TRANSCODE_BASE_PATH')]
        private readonly string $transcodeBasePath,
    ) {}

    public function __invoke(TranscodeJobMessage $message): void
    {
        $job = $this->jobRepo->find($message->transcodeJobId);

        if ($job === null || $job->getStatus() !== TranscodeJob::STATUS_QUEUED) {
            return;
        }

        $job->setStatus(TranscodeJob::STATUS_RUNNING);
        $job->setStartedAt(new \DateTimeImmutable());
        $this->em->flush();

        try {
            $outputDir = $this->execute($job);
            $job->setStatus(TranscodeJob::STATUS_COMPLETED);
            $job->setCompletedAt(new \DateTimeImmutable());
            $this->em->flush();

            $this->dispatchTransfer($job, $outputDir);
        } catch (\Throwable $e) {
            $this->logger->error('Transcode job {id} failed: {msg}', [
                'id'  => $job->getId(),
                'msg' => $e->getMessage(),
            ]);
            $job->setStatus(TranscodeJob::STATUS_FAILED);
            $job->setErrorMessage($e->getMessage());
            $job->setCompletedAt(new \DateTimeImmutable());
            $this->em->flush();
        }
    }

    private function execute(TranscodeJob $job): string
    {
        $sourceMedia  = $job->getSourceMedia();
        $sourceConn   = $sourceMedia->getConnection();
        $relPath      = $sourceMedia->getPath() ?? '';
        $localPath    = $sourceConn->getLocalPath() ?? '/var/media/source';
        $sourceFolder = rtrim($localPath, '/') . '/' . ltrim($relPath, '/');

        if (!is_dir($sourceFolder)) {
            throw new \RuntimeException("Source folder not found: {$sourceFolder}");
        }

        $videoFile = $this->transcodeService->findVideoFile($sourceFolder);
        if ($videoFile === null) {
            throw new \RuntimeException("No video file found in: {$sourceFolder}");
        }

        $outputDir = rtrim($this->transcodeBasePath, '/') . '/' . ltrim($relPath, '/');
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $outputPath = $outputDir . '/' . $videoFile->getFilename();

        $probeData    = $this->transcodeService->probeFile($videoFile->getRealPath());
        $duration     = $probeData['duration'];
        $audioStreams  = $probeData['audioStreams'];
        $sourceHeight = $probeData['videoHeight'];

        $this->logger->info('Transcode job {id}: duration={dur}s, source height={h}px, audio streams={n}, file={f}', [
            'id'  => $job->getId(),
            'dur' => $duration,
            'h'   => $sourceHeight ?? 'unknown',
            'n'   => count($audioStreams),
            'f'   => $videoFile->getFilename(),
        ]);

        $estimatedBytes = $duration > 0
            ? (int) (
                ($job->getTranscodeProfile()->getVideoBitrateKbps()
                + $job->getTranscodeProfile()->getLosslessAudioBitrateKbps())
                * 1000 / 8 * $duration
            )
            : 1;

        $job->setTotalBytes($estimatedBytes);
        $this->em->flush();

        $lastReport = 0;
        $this->transcodeService->transcode(
            $videoFile->getRealPath(),
            $outputPath,
            $job->getTranscodeProfile(),
            $duration,
            $audioStreams,
            function (int $bytesDone, int $totalBytes, ?float $fps, ?string $speed) use ($job, &$lastReport): void {
                $now = time();
                if ($now - $lastReport >= 5) {
                    $job->setBytesDone($bytesDone);
                    $job->setTotalBytes($totalBytes);
                    $job->setCurrentFps($fps);
                    $job->setCurrentSpeed($speed);
                    try {
                        $this->em->flush();
                    } catch (\Throwable $e) {
                        // Log but do NOT propagate — a failed DB update must not kill the FFmpeg process
                        $this->logger->error('Progress flush failed for transcode job {id}: {msg}', [
                            'id'  => $job->getId(),
                            'msg' => $e->getMessage(),
                        ]);
                    }
                    $lastReport = $now;
                }
            },
            $sourceHeight,
        );

        $this->transcodeService->copyNonVideoFiles($sourceFolder, $outputDir, $videoFile->getFilename());

        return $outputDir;
    }

    private function dispatchTransfer(TranscodeJob $transcodeJob, string $outputDir): void
    {
        $transferJob = new TransferJob();
        $transferJob->setProfile($transcodeJob->getProfile());
        $transferJob->setSourceMedia($transcodeJob->getSourceMedia());
        $transferJob->setSourceDirOverride($outputDir);
        $this->em->persist($transferJob);
        $this->em->flush();

        $this->bus->dispatch(new TransferJobMessage($transferJob->getId()));
    }
}
