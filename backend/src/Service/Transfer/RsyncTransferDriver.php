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

        // -rlt: recursive, preserve symlinks and timestamps; skip -pog (owner/group/perms)
        // which fail when the destination user isn't root.
        $cmd = 'rsync -rlt --info=progress2 --no-inc-recursive'
            . ' -e ' . escapeshellarg('ssh ' . $sshArgs)
            . ' ' . escapeshellarg(rtrim($sourceAbsPath, '/') . '/')
            . ' ' . escapeshellarg($username . '@' . $host . ':' . $destPath . '/');

        if ($password !== null && !($keyPath && file_exists($keyPath))) {
            $cmd = 'sshpass -p ' . escapeshellarg($password) . ' ' . $cmd;
        }

        $totalBytes = $this->calculateSize($sourceAbsPath);
        $lastReport = 0; // fire first update immediately

        $proc = proc_open($cmd, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'r'],
            2 => ['pipe', 'r'],
        ], $pipes);

        if (!is_resource($proc)) {
            throw new \RuntimeException('Failed to start rsync process');
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdoutBuf = '';
        $stderrBuf = '';
        $stdoutDone = false;
        $stderrDone = false;

        // rsync --info=progress2 writes to stderr on some versions, stdout on others.
        // Parse both so progress is captured regardless.
        $parseProgress = function (string &$buf) use ($totalBytes, &$lastReport, $onProgress): void {
            $parts = preg_split('/[\r\n]+/', $buf);
            $buf = (string) array_pop($parts);
            foreach ($parts as $line) {
                if (preg_match('/^\s*([\d,]+)\s+\d+%/', $line, $m)) {
                    $bytesCopied = (int) str_replace(',', '', $m[1]);
                    if (time() - $lastReport >= 2) {
                        ($onProgress)($bytesCopied, $totalBytes);
                        $lastReport = time();
                    }
                }
            }
        };

        while (!$stdoutDone || !$stderrDone) {
            $read = [];
            if (!$stdoutDone) $read[] = $pipes[1];
            if (!$stderrDone) $read[] = $pipes[2];

            $write = $except = null;
            $n = @stream_select($read, $write, $except, 0, 200000);

            if ($n > 0) {
                foreach ($read as $pipe) {
                    $chunk = @fread($pipe, 65536);
                    if ($chunk === false || $chunk === '') {
                        if ($pipe === $pipes[1]) $stdoutDone = true;
                        else $stderrDone = true;
                        continue;
                    }
                    if ($pipe === $pipes[1]) {
                        $stdoutBuf .= $chunk;
                    } else {
                        $stderrBuf .= $chunk;
                    }
                }
            }

            $parseProgress($stdoutBuf);
            $parseProgress($stderrBuf);
        }

        @fclose($pipes[1]);
        @fclose($pipes[2]);
        $exitCode = proc_close($proc);

        if ($exitCode !== 0) {
            throw new \RuntimeException('rsync failed (exit ' . $exitCode . '): ' . trim($stderrBuf));
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
