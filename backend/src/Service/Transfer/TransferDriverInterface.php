<?php

namespace App\Service\Transfer;

interface TransferDriverInterface
{
    public function getName(): string;

    /**
     * Transfer an entire folder recursively from source to destination.
     *
     * @param string   $sourceAbsPath  Absolute path to the folder on the source filesystem
     * @param string   $destBasePath   Absolute base path on the destination
     * @param string   $relFolderPath  Path of the folder relative to the library root (appended to destBasePath)
     * @param callable $onProgress     fn(int $bytesCopied, int $totalBytes): void — called periodically
     */
    public function transferFolder(
        string $sourceAbsPath,
        string $destBasePath,
        string $relFolderPath,
        callable $onProgress,
    ): void;
}
