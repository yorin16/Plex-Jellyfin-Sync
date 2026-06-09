<?php

namespace App\Service\Transfer;

use App\Entity\TransferConfig;

class LocalTransferDriver implements TransferDriverInterface
{
    public function getName(): string
    {
        return TransferConfig::METHOD_LOCAL;
    }

    public function transferFolder(
        string $sourceAbsPath,
        string $destBasePath,
        string $relFolderPath,
        callable $onProgress,
    ): void {
        $destPath = rtrim($destBasePath, '/\\') . DIRECTORY_SEPARATOR . ltrim($relFolderPath, '/\\');

        if (!is_dir($destPath)) {
            mkdir($destPath, 0755, true);
        }

        $totalBytes = $this->calculateSize($sourceAbsPath);
        $bytesCopied = 0;
        $lastReport = time();

        $this->copyRecursive($sourceAbsPath, $destPath, $totalBytes, $bytesCopied, $lastReport, $onProgress);
    }

    private function copyRecursive(
        string $src,
        string $dst,
        int $totalBytes,
        int &$bytesCopied,
        int &$lastReport,
        callable $onProgress,
    ): void {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($items as $item) {
            $rel  = substr($item->getRealPath(), strlen(realpath($src)) + 1);
            $dest = $dst . DIRECTORY_SEPARATOR . $rel;

            if ($item->isDir()) {
                if (!is_dir($dest)) {
                    mkdir($dest, 0755, true);
                }
            } else {
                copy($item->getRealPath(), $dest);
                $bytesCopied += $item->getSize();

                if (time() - $lastReport >= 5) {
                    ($onProgress)($bytesCopied, $totalBytes);
                    $lastReport = time();
                }
            }
        }

        ($onProgress)($bytesCopied, $totalBytes);
    }

    private function calculateSize(string $path): int
    {
        $total = 0;
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($items as $item) {
            if ($item->isFile()) {
                $total += $item->getSize();
            }
        }
        return $total;
    }
}
