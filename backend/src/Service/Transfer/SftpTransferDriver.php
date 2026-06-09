<?php

namespace App\Service\Transfer;

use App\Entity\TransferConfig;
use phpseclib3\Net\SFTP;

class SftpTransferDriver implements TransferDriverInterface
{
    public function getName(): string
    {
        return TransferConfig::METHOD_SFTP;
    }

    public function transferFolder(
        string $sourceAbsPath,
        string $destBasePath,
        string $relFolderPath,
        callable $onProgress,
    ): void {
        throw new \LogicException('SftpTransferDriver requires a config array — use transferFolderWithConfig()');
    }

    public function transferFolderWithConfig(
        array $config,
        string $sourceAbsPath,
        string $relFolderPath,
        callable $onProgress,
    ): void {
        $host     = $config['host'] ?? '';
        $port     = (int) ($config['port'] ?? 22);
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? null;
        $keyPath  = $config['key_path'] ?? null;
        $destBase = rtrim($config['dest_base_path'] ?? '', '/');

        $sftp = new SFTP($host, $port);

        if ($keyPath && file_exists($keyPath)) {
            $key = \phpseclib3\Crypt\PublicKeyLoader::load(file_get_contents($keyPath));
            if (!$sftp->login($username, $key)) {
                throw new \RuntimeException("SFTP login with key failed for {$username}@{$host}");
            }
        } elseif ($password !== null) {
            if (!$sftp->login($username, $password)) {
                throw new \RuntimeException("SFTP login failed for {$username}@{$host}");
            }
        } else {
            throw new \RuntimeException('SFTP: no password or key_path provided in config');
        }

        $destPath = $destBase . '/' . ltrim($relFolderPath, '/');
        $this->mkdirRecursive($sftp, $destPath);

        $totalBytes = $this->calculateSize($sourceAbsPath);
        $bytesCopied = 0;
        $lastReport = time();

        $this->uploadRecursive($sftp, $sourceAbsPath, $destPath, $totalBytes, $bytesCopied, $lastReport, $onProgress);
    }

    private function uploadRecursive(
        SFTP $sftp,
        string $localPath,
        string $remotePath,
        int $totalBytes,
        int &$bytesCopied,
        int &$lastReport,
        callable $onProgress,
    ): void {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($localPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($items as $item) {
            $rel    = substr($item->getRealPath(), strlen(realpath($localPath)) + 1);
            $rel    = str_replace('\\', '/', $rel);
            $remote = $remotePath . '/' . $rel;

            if ($item->isDir()) {
                $sftp->mkdir($remote, -1, true);
            } else {
                $sftp->put($remote, $item->getRealPath(), SFTP::SOURCE_LOCAL_FILE);
                $bytesCopied += $item->getSize();

                if (time() - $lastReport >= 5) {
                    ($onProgress)($bytesCopied, $totalBytes);
                    $lastReport = time();
                }
            }
        }

        ($onProgress)($bytesCopied, $totalBytes);
    }

    private function mkdirRecursive(SFTP $sftp, string $path): void
    {
        $sftp->mkdir($path, -1, true);
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
