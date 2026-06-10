<?php

namespace App\Service\Transfer;

use App\Entity\TransferConfig;

class RsyncTransferDriver implements TransferDriverInterface
{
    public function getName(): string
    {
        return TransferConfig::METHOD_RSYNC;
    }

    public function transferFolder(
        string $sourceAbsPath,
        string $destBasePath,
        string $relFolderPath,
        callable $onProgress,
    ): void {
        throw new \LogicException('RsyncTransferDriver requires config — use transferFolderWithConfig()');
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
        $destPath = $destBase . '/' . ltrim($relFolderPath, '/');

        $sshArgs = '-p ' . $port
            . ' -o StrictHostKeyChecking=no'
            . ' -o UserKnownHostsFile=/dev/null'
            . ' -o LogLevel=ERROR';

        if ($keyPath && file_exists($keyPath)) {
            $sshArgs .= ' -i ' . escapeshellarg($keyPath);
        }

        $cmd = 'rsync -a --info=progress2 --no-inc-recursive'
            . ' -e ' . escapeshellarg('ssh ' . $sshArgs)
            . ' ' . escapeshellarg(rtrim($sourceAbsPath, '/') . '/')
            . ' ' . escapeshellarg($username . '@' . $host . ':' . $destPath . '/');

        if ($password !== null && !($keyPath && file_exists($keyPath))) {
            $cmd = 'sshpass -p ' . escapeshellarg($password) . ' ' . $cmd;
        }

        $totalBytes = $this->calculateSize($sourceAbsPath);
        $lastReport = time();

        $proc = proc_open($cmd, [1 => ['pipe', 'r'], 2 => ['pipe', 'r']], $pipes);
        if (!is_resource($proc)) {
            throw new \RuntimeException('Failed to start rsync process');
        }

        $stderr = '';
        while (!feof($pipes[1])) {
            $line = fgets($pipes[1]);
            if ($line === false) {
                break;
            }
            // --info=progress2: "      58,985,472  30%  112.53MB/s    0:00:22"
            if (preg_match('/^\s*([\d,]+)\s+\d+%/', $line, $m)) {
                $bytesCopied = (int) str_replace(',', '', $m[1]);
                if (time() - $lastReport >= 5) {
                    ($onProgress)($bytesCopied, $totalBytes);
                    $lastReport = time();
                }
            }
        }

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);

        if ($exitCode !== 0) {
            throw new \RuntimeException("rsync failed (exit {$exitCode}): " . trim($stderr));
        }

        ($onProgress)($totalBytes, $totalBytes);
    }

    private function calculateSize(string $path): int
    {
        $total = 0;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($it as $f) {
            if ($f->isFile()) {
                $total += $f->getSize();
            }
        }
        return $total;
    }
}
