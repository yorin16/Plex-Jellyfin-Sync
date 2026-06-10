<?php

namespace App\MessageHandler;

use App\Entity\TransferJob;
use App\Message\TransferJobMessage;
use App\Repository\TransferJobRepository;
use App\Service\Jellyfin\JellyfinClient;
use App\Service\Transfer\SftpTransferDriver;
use App\Service\Transfer\TransferDriverRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class TransferJobHandler
{
    public function __construct(
        private readonly TransferJobRepository $jobRepo,
        private readonly TransferDriverRegistry $driverRegistry,
        private readonly JellyfinClient $jellyfinClient,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(TransferJobMessage $message): void
    {
        $job = $this->jobRepo->find($message->transferJobId);

        if ($job === null || $job->getStatus() !== TransferJob::STATUS_QUEUED) {
            return;
        }

        $job->setStatus(TransferJob::STATUS_RUNNING);
        $job->setStartedAt(new \DateTimeImmutable());
        $this->em->flush();

        try {
            $this->execute($job);
            $job->setStatus(TransferJob::STATUS_COMPLETED);
            $job->setCompletedAt(new \DateTimeImmutable());
            $this->triggerJellyfinRefresh($job);
        } catch (\Throwable $e) {
            $this->logger->error('Transfer job {id} failed: {msg}', [
                'id' => $job->getId(),
                'msg' => $e->getMessage(),
            ]);
            $job->setStatus(TransferJob::STATUS_FAILED);
            $job->setErrorMessage($e->getMessage());
            $job->setCompletedAt(new \DateTimeImmutable());
        } finally {
            $this->em->flush();
        }
    }

    private function execute(TransferJob $job): void
    {
        $profile      = $job->getProfile();
        $sourceMedia  = $job->getSourceMedia();
        $sourceConn   = $sourceMedia->getConnection();
        $transferConf = $profile->getTransferConfig();

        if ($transferConf === null) {
            throw new \RuntimeException('No transfer config for profile ' . $profile->getId());
        }

        $localPath = $sourceConn->getLocalPath() ?? '/var/media/source';
        $relPath   = $sourceMedia->getPath() ?? '';
        $sourceAbs = rtrim($localPath, '/') . '/' . ltrim($relPath, '/');

        if (!is_dir($sourceAbs)) {
            throw new \RuntimeException("Source folder not found: {$sourceAbs}");
        }

        $totalBytes = $job->getTotalBytes();
        if ($totalBytes === 0) {
            $totalBytes = $this->calculateSize($sourceAbs);
            $job->setTotalBytes($totalBytes);
            $this->em->flush();
        }

        $method   = $transferConf->getMethod();
        $config   = $transferConf->getConfig();
        $destBase = $config['dest_base_path'] ?? '';
        $startTime = time();
        $lastBytes = 0;

        $onProgress = function (int $bytesCopied, int $total) use ($job, &$startTime, &$lastBytes): void {
            $elapsed = max(1, time() - $startTime);
            $speed   = (int) (($bytesCopied - $lastBytes) / max(1, 5));
            $job->setBytesCopied($bytesCopied);
            $job->setTotalBytes($total);
            $job->setTransferSpeedBps($speed);
            $lastBytes = $bytesCopied;
            $this->em->flush();
        };

        if ($method === 'sftp') {
            /** @var SftpTransferDriver $driver */
            $driver = $this->driverRegistry->get('sftp');
            $driver->transferFolderWithConfig($config, $sourceAbs, $relPath, $onProgress);
        } else {
            $driver = $this->driverRegistry->get($method);
            $driver->transferFolder($sourceAbs, $destBase, $relPath, $onProgress);
        }
    }

    private function calculateSize(string $path): int
    {
        $total = 0;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile()) {
                $total += $f->getSize();
            }
        }
        return $total;
    }

    private function triggerJellyfinRefresh(TransferJob $job): void
    {
        $profile = $job->getProfile();
        foreach ($profile->getConnections() as $conn) {
            if ($conn->getType() === 'jellyfin') {
                $this->jellyfinClient->triggerLibraryScan($conn->getUrl(), $conn->getApiToken());
                break;
            }
        }
    }
}
