<?php

namespace App\Service;

use App\Entity\TranscodeProfile;
use Psr\Log\LoggerInterface;

class TranscodeService
{
    private const VIDEO_EXTENSIONS = ['mkv', 'mp4', 'avi', 'mov', 'ts', 'm2ts', 'wmv'];
    private const LOSSLESS_CODECS  = ['truehd', 'mlp', 'flac'];

    public function __construct(private readonly LoggerInterface $logger) {}

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

    /** Returns ['duration' => float, 'videoHeight' => ?int, 'audioStreams' => [['codec' => string, 'profile' => string], ...]] */
    public function probeFile(string $filePath): array
    {
        $cmd = 'ffprobe -v quiet'
            . ' -show_entries format=duration:stream=codec_name,profile,codec_type,height'
            . ' -of json '
            . escapeshellarg($filePath);

        $raw  = shell_exec($cmd) ?? '{}';
        $data = json_decode($raw, true) ?? [];

        $duration    = (float) ($data['format']['duration'] ?? 0);
        $videoHeight = null;
        $audioStreams = [];

        foreach ($data['streams'] ?? [] as $stream) {
            $type = $stream['codec_type'] ?? '';
            if ($type === 'video' && $videoHeight === null && isset($stream['height'])) {
                $videoHeight = (int) $stream['height'];
            } elseif ($type === 'audio') {
                $audioStreams[] = [
                    'codec'   => $stream['codec_name'] ?? '',
                    'profile' => $stream['profile']    ?? '',
                ];
            }
        }

        return ['duration' => $duration, 'videoHeight' => $videoHeight, 'audioStreams' => $audioStreams];
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
        ?int $sourceHeight = null,
    ): void {
        $videoBitrate = $profile->getVideoBitrateKbps();
        $estimatedBytes = $duration > 0
            ? (int) (($videoBitrate + $profile->getLosslessAudioBitrateKbps()) * 1000 / 8 * $duration)
            : PHP_INT_MAX;

        $isQsv   = str_ends_with($profile->getVideoCodec(), '_qsv');
        $isVaapi = str_ends_with($profile->getVideoCodec(), '_vaapi');
        $args    = ['ffmpeg'];

        if ($isQsv) {
            // Explicit device helps libmfx find the right DRI node
            array_push($args, '-hwaccel', 'qsv', '-qsv_device', '/dev/dri/renderD128', '-hwaccel_output_format', 'qsv');
        } elseif ($isVaapi) {
            array_push($args, '-hwaccel', 'vaapi', '-hwaccel_device', '/dev/dri/renderD128', '-hwaccel_output_format', 'vaapi');
        }

        array_push($args, '-i', $inputPath, '-map', '0');

        // Video filter: only add when resize is actually needed.
        // scale_vaapi runs on EU shaders — skipping it when the source is already at/below
        // the target height avoids unnecessary GPU compute.
        if ($profile->getMaxHeight() !== null) {
            $h = $profile->getMaxHeight();
            $needsScale = $sourceHeight === null || $sourceHeight > $h;

            if ($needsScale) {
                if ($isQsv) {
                    $vf = $profile->isHdrToSdr()
                        ? "vpp_qsv=tonemap=1:w=-2:h={$h}"
                        : "scale_qsv=-2:{$h}";
                } elseif ($isVaapi) {
                    $vf = "scale_vaapi=w=-2:h={$h}";
                } else {
                    $vf = $profile->isHdrToSdr()
                        ? "zscale=t=linear:npl=100,format=gbrpf32le,zscale=p=bt709,tonemap=hable:desat=0,zscale=t=bt709:m=bt709:r=tv,format=yuv420p,scale=-2:{$h}"
                        : "scale=-2:{$h}";
                }
                array_push($args, '-vf', $vf);
            }
        }

        array_push(
            $args,
            '-c:v', $profile->getVideoCodec(),
            '-b:v', $videoBitrate . 'k',
            '-maxrate', (int) ($videoBitrate * 1.5) . 'k',
            '-bufsize', ($videoBitrate * 3) . 'k',
            '-profile:v', 'main',
        );

        // VDENC (low-power) path uses fixed-function encode hardware instead of EU-assisted PAK,
        // freeing EU shaders and significantly increasing throughput on Gen12+ iGPU.
        if ($isVaapi && str_starts_with($profile->getVideoCodec(), 'hevc')) {
            array_push($args, '-low_power', '1');
        }

        // Audio: explicit per-stream codec to avoid "multiple -c options" warning in FFmpeg 5.x
        foreach ($audioStreams as $idx => $stream) {
            if ($this->isLosslessAudio($stream)) {
                array_push(
                    $args,
                    '-c:a:' . $idx, $profile->getLosslessAudioCodec(),
                    '-b:a:' . $idx, $profile->getLosslessAudioBitrateKbps() . 'k',
                );
            } else {
                array_push($args, '-c:a:' . $idx, 'copy');
            }
        }
        // Fallback for any streams ffprobe missed (e.g. commentary tracks added after probe)
        if (empty($audioStreams)) {
            array_push($args, '-c:a', 'copy');
        }

        array_push($args, '-c:s', 'copy', '-progress', 'pipe:1', '-y', $outputPath);

        $this->runProcess($args, $duration, $estimatedBytes, $onProgress);
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

    private function runProcess(array $args, float $duration, int $estimatedBytes, callable $onProgress): void
    {
        $cmd        = implode(' ', array_map('escapeshellarg', $args));
        $stderrFile = tempnam(sys_get_temp_dir(), 'ffmpeg_err_');

        $proc = proc_open($cmd, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'r'],
            2 => ['file', $stderrFile, 'w'],
        ], $pipes);

        if (!is_resource($proc)) {
            @unlink($stderrFile);
            throw new \RuntimeException('Failed to start ffmpeg process. Command: ' . $cmd);
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);

        $progressBuf = '';
        $stdoutDone  = false;
        $lastReport  = 0;
        $lastLogAt   = 0;
        $durationUs  = $duration > 0 ? (int) ($duration * 1_000_000) : 0;

        while (!$stdoutDone) {
            $read = [$pipes[1]];
            $write = $except = null;
            @stream_select($read, $write, $except, 0, 200000);

            foreach ($read as $pipe) {
                $chunk = @fread($pipe, 65536);
                if ($chunk === false || ($chunk === '' && feof($pipe))) {
                    $stdoutDone = true;
                    continue;
                }
                if ($chunk !== '') {
                    $progressBuf .= $chunk;
                }
            }

            // Parse -progress pipe:1 key=value lines; use out_time_us for reliable
            // time-based progress (total_size stays 0 with VA-API / MKV muxer)
            $lines       = explode("\n", $progressBuf);
            $progressBuf = (string) array_pop($lines);
            $outTimeUs   = null;
            $fps         = null;
            $speed       = null;
            foreach ($lines as $line) {
                $line = trim($line);
                if (str_starts_with($line, 'out_time_us=')) {
                    $outTimeUs = (int) substr($line, 12);
                } elseif (str_starts_with($line, 'fps=')) {
                    $fps = (float) substr($line, 4);
                } elseif (str_starts_with($line, 'speed=')) {
                    $speed = substr($line, 6); // e.g. "4.23x"
                }
            }

            $now = time();
            if ($outTimeUs !== null && $outTimeUs > 0 && $durationUs > 0) {
                $bytesDone = (int) min($outTimeUs / $durationUs * $estimatedBytes, $estimatedBytes);
                if ($now - $lastReport >= 5) {
                    ($onProgress)($bytesDone, $estimatedBytes, $fps, $speed);
                    $lastReport = $now;
                }
            }

            if ($fps !== null && $now - $lastLogAt >= 30) {
                $pct = $durationUs > 0 && $outTimeUs !== null
                    ? round($outTimeUs / $durationUs * 100, 1)
                    : '?';
                $this->logger->info(
                    'FFmpeg progress: {fps} fps, {speed}, {pct}% done',
                    ['fps' => $fps, 'speed' => $speed ?? '?', 'pct' => $pct],
                );
                $lastLogAt = $now;
            }
        }

        @fclose($pipes[1]);
        $exitCode  = proc_close($proc);
        $stderrBuf = is_file($stderrFile) ? trim((string) file_get_contents($stderrFile)) : '';
        @unlink($stderrFile);

        if ($exitCode !== 0) {
            throw new \RuntimeException(
                'ffmpeg failed (exit ' . $exitCode . '): ' . $stderrBuf
                . "\nCommand: " . $cmd
            );
        }

        ($onProgress)($estimatedBytes, $estimatedBytes, null, null);
    }
}
