<?php

namespace App\Service;

use App\Entity\TransferConfig;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;

class DiskUsageService
{
    public function getUsage(TransferConfig $config): ?array
    {
        $method = $config->getMethod();
        $cfg    = $config->getConfig();

        if ($method === TransferConfig::METHOD_LOCAL) {
            $path  = $cfg['dest_base_path'] ?? '/';
            $total = disk_total_space($path);
            $free  = disk_free_space($path);
            if ($total === false || $free === false) {
                return null;
            }
            return ['total' => (int) $total, 'used' => (int) ($total - $free), 'free' => (int) $free];
        }

        if ($method === TransferConfig::METHOD_SFTP || $method === TransferConfig::METHOD_RSYNC) {
            return $this->getUsageViaSsh($cfg);
        }

        return null;
    }

    private function getUsageViaSsh(array $cfg): ?array
    {
        $host     = $cfg['host'] ?? '';
        $port     = (int) ($cfg['port'] ?? 22);
        $username = $cfg['username'] ?? '';
        $password = $cfg['password'] ?? null;
        $keyPath  = $cfg['key_path'] ?? null;
        $destBase = $cfg['dest_base_path'] ?? '/';

        $ssh = new SSH2($host, $port);
        $ssh->setTimeout(10);

        if ($keyPath && file_exists($keyPath)) {
            $key = PublicKeyLoader::load(file_get_contents($keyPath));
            if (!$ssh->login($username, $key)) {
                throw new \RuntimeException("SSH login with key failed for {$username}@{$host}");
            }
        } elseif ($password !== null) {
            if (!$ssh->login($username, $password)) {
                throw new \RuntimeException("SSH login failed for {$username}@{$host}");
            }
        } else {
            throw new \RuntimeException('No password or key_path provided for SSH disk usage check');
        }

        $output = $ssh->exec('df -B1 ' . escapeshellarg($destBase) . ' 2>/dev/null | tail -1');
        $ssh->disconnect();

        // df -B1 columns: Filesystem  1B-blocks  Used  Available  Use%  Mounted
        if (!preg_match('/\S+\s+(\d+)\s+(\d+)\s+(\d+)/', trim($output), $m)) {
            return null;
        }

        return [
            'total' => (int) $m[1],
            'used'  => (int) $m[2],
            'free'  => (int) $m[3],
        ];
    }
}
