<?php

namespace App\Service;

use App\Entity\TranscodeProfile;

class TranscodeService
{
    private const VIDEO_EXTENSIONS = ['mkv', 'mp4', 'avi', 'mov', 'ts', 'm2ts', 'wmv'];
    private const LOSSLESS_CODECS  = ['truehd', 'mlp', 'flac'];

    public function findVideoFile(string $folderPath): ?\SplFileInfo
    {
        $largest     = null;
        $largestSize = 0;

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($folderPath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($it as $file) {
            if (!$file->isFile()) {
                continue;
            }
            if (!in_array(strtolower($file->getExtension()), self::VIDEO_EXTENSIONS, true)) {
                continue;
            }
            if ($file->getSize() > $largestSize) {
                $largestSize = $file->getSize();
                $largest     = $file;
            }
        }

        return $largest;
    }

    /** Returns ['duration' => float, 'audioStreams' => [['codec' => string, 'profile' => string], ...]] */
    public function probeFile(string $filePath): array
    {
        $cmd = 'ffprobe -v quiet'
            . ' -show_entries format=duration:stream=codec_name,profile'
            . ' -select_streams a'
            . ' -of json '
            . escapeshellarg($filePath);

        $raw  = shell_exec($cmd) ?? '{}';
        $data = json_decode($raw, true) ?? [];

        $duration     = (float) ($data['format']['duration'] ?? 0);
        $audioStreams  = array_map(fn(array $s) => [
            'codec'   => $s['codec_name'] ?? '',
            'profile' => $s['profile']    ?? '',
        ], $data['streams'] ?? []);

        return ['duration' => $duration, 'audioStreams' => $audioStreams];
    }

    public function isLosslessAudio(array $stream): bool
    {
        $codec   = strtolower($stream['codec']   ?? '');
        $profile = strtolower($stream['profile'] ?? '');

        if (in_array($codec, self::LOSSLESS_CODECS, true)) {
            return true;
        }
        if (str_starts_with($codec, 'pcm_')) {
            return true;
        }
        // DTS-HD MA and DTS:X are lossless; plain DTS is not
        if ($codec === 'dts' && (str_contains($profile, 'ma') || str_contains($profile, ':x'))) {
            return true;
        }

        return false;
    }

    public function transcode(
        string $inputPath,
        string $outputPath,
        TranscodeProfile $profile,
        float $duration,
        array $audioStreams,
        callable $onProgress,
    ): void {
        $videoBitrate = $profile->getVideoBitrateKbps();
        $estimatedBytes = $duration > 0
            ? (int) (($videoBitrate + $profile->getLosslessAudioBitrateKbps()) * 1000 / 8 * $duration)
            : PHP_INT_MAX;

        $args = ['ffmpeg', '-hwaccel', 'qsv', '-hwaccel_output_format', 'qsv', '-i', $inputPath, '-map', '0'];

        // Video filter (scale + optional HDR→SDR tone-mapping)
        if ($profile->getMaxHeight() !== null) {
            $h  = $profile->getMaxHeight();
            $vf = $profile->isHdrToSdr()
                ? "vpp_qsv=tonemap=1:w=-2:h={$h}"
                : "scale_qsv=-2:{$h}";
            array_push($args, '-vf', $vf);
        }

        array_push(
            $args,
            '-c:v', $profile->getVideoCodec(),
            '-b:v', $videoBitrate . 'k',
            '-maxrate', (int) ($videoBitrate * 1.5) . 'k',
            '-bufsize', ($videoBitrate * 3) . 'k',
            '-profile:v', 'main',
        );

        // Audio: copy everything, then override lossless streams
        array_push($args, '-c:a', 'copy');
        foreach ($audioStreams as $idx => $stream) {
            if ($this->isLosslessAudio($stream)) {
                array_push(
                    $args,
                    '-c:a:' . $idx, $profile->getLosslessAudioCodec(),
                    '-b:a:' . $idx, $profile->getLosslessAudioBitrateKbps() . 'k',
                );
            }
        }

        array_push($args, '-c:s', 'copy', '-progress', 'pipe:1', '-y', $outputPath);

        $this->runProcess($args, $estimatedBytes, $onProgress);
    }

    public function copyNonVideoFiles(string $sourceFolder, string $destFolder, string $videoFilename): void
    {
        $sourceFolder = rtrim(realpath($sourceFolder) ?: $sourceFolder, '/\\');

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceFolder, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($it as $item) {
            if ($item->getFilename() === $videoFilename && $item->isFile()) {
                continue;
            }
            $rel  = str_replace('\\', '/', substr($item->getRealPath(), strlen($sourceFolder) + 1));
            $dest = $destFolder . '/' . $rel;

            if ($item->isDir()) {
                if (!is_dir($dest)) {
                    mkdir($dest, 0755, true);
                }
            } else {
                $destDir = dirname($dest);
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0755, true);
                }
                copy($item->getRealPath(), $dest);
            }
        }
    }

    public function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($it as $item) {
            $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
        }

        rmdir($path);
    }

    private function runProcess(array $args, int $estimatedBytes, callable $onProgress): void
    {
        $cmd  = implode(' ', array_map('escapeshellarg', $args));
        $proc = proc_open($cmd, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'r'],
            2 => ['pipe', 'r'],
        ], $pipes);

        if (!is_resource($proc)) {
            throw new \RuntimeException('Failed to start ffmpeg process');
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $progressBuf = '';
        $stderrBuf   = '';
        $stdoutDone  = false;
        $stderrDone  = false;
        $lastReport  = 0;
        $bytesDone   = 0;

        while (!$stdoutDone || !$stderrDone) {
            $read = [];
            if (!$stdoutDone) {
                $read[] = $pipes[1];
            }
            if (!$stderrDone) {
                $read[] = $pipes[2];
            }

            $write = $except = null;
            @stream_select($read, $write, $except, 0, 200000);

            foreach ($read as $pipe) {
                $chunk = @fread($pipe, 65536);
                if ($chunk === false || ($chunk === '' && feof($pipe))) {
                    if ($pipe === $pipes[1]) {
                        $stdoutDone = true;
                    } else {
                        $stderrDone = true;
                    }
                    continue;
                }
                if ($chunk === '') {
                    continue;
                }
                if ($pipe === $pipes[1]) {
                    $progressBuf .= $chunk;
                } else {
                    $stderrBuf .= $chunk;
                }
            }

            // Parse -progress pipe:1 key=value lines
            $lines       = explode("\n", $progressBuf);
            $progressBuf = (string) array_pop($lines);
            foreach ($lines as $line) {
                $line = trim($line);
                if (str_starts_with($line, 'total_size=')) {
                    $bytesDone = (int) substr($line, 11);
                }
            }

            if (time() - $lastReport >= 5 && $bytesDone > 0) {
                ($onProgress)($bytesDone, max($bytesDone, $estimatedBytes));
                $lastReport = time();
            }
        }

        @fclose($pipes[1]);
        @fclose($pipes[2]);
        $exitCode = proc_close($proc);

        if ($exitCode !== 0) {
            throw new \RuntimeException('ffmpeg failed (exit ' . $exitCode . '): ' . trim($stderrBuf));
        }

        ($onProgress)($estimatedBytes, $estimatedBytes);
    }
}
