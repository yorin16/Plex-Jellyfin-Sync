<?php

namespace App\Service\Plex;

use App\Entity\CachedMedia;
use App\Entity\MediaConnection;
use App\Repository\CachedMediaRepository;
use Doctrine\ORM\EntityManagerInterface;

class PlexScanner
{
    public function __construct(
        private readonly PlexClient $client,
        private readonly CachedMediaRepository $cachedMediaRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    public function scan(MediaConnection $connection): int
    {
        $scanStartedAt = new \DateTimeImmutable();

        $movies = $this->client->getMovies(
            $connection->getUrl(),
            $connection->getApiToken(),
            $connection->getLibraryId() ?? '',
        );

        $seenExternalIds = [];
        $count = 0;

        foreach ($movies as $movie) {
            $externalId = (string) ($movie['ratingKey'] ?? '');
            if ($externalId === '') {
                continue;
            }
            $seenExternalIds[] = $externalId;

            $item = $this->cachedMediaRepository->findByConnectionAndExternalId($connection, $externalId)
                ?? (new CachedMedia())->setConnection($connection)->setExternalId($externalId);

            $item->setTitle($movie['title'] ?? 'Unknown');
            $item->setYear(isset($movie['year']) ? (int) $movie['year'] : null);
            $item->setLastSeenAt(new \DateTimeImmutable());

            [$tmdbId, $imdbId] = $this->extractGuids($movie['Guid'] ?? []);
            $item->setTmdbId($tmdbId);
            $item->setImdbId($imdbId);

            $media = $movie['Media'][0] ?? [];
            $part = $media['Part'][0] ?? [];
            $item->setPath($this->extractRelativePath($part['file'] ?? '', $connection->getLibraryRoot() ?? ''));
            $item->setFileSize(isset($part['size']) ? (int) $part['size'] : null);
            $item->setCodec($media['videoCodec'] ?? null);
            $item->setResolution($this->resolveResolution($media));
            $item->setHdrType($this->extractHdrType($part['Stream'] ?? []));
            $item->setRawMetadata($movie);

            $this->em->persist($item);
            $count++;
        }

        $this->em->flush();

        $this->cachedMediaRepository->deleteStaleByConnection($connection, $scanStartedAt);

        $connection->setLastScannedAt(new \DateTimeImmutable());
        $this->em->flush();

        return $count;
    }

    private function extractGuids(array $guids): array
    {
        $tmdb = null;
        $imdb = null;
        foreach ($guids as $guid) {
            $id = $guid['id'] ?? '';
            if (str_starts_with($id, 'tmdb://')) {
                $tmdb = substr($id, 7);
            } elseif (str_starts_with($id, 'imdb://')) {
                $imdb = substr($id, 7);
            }
        }
        return [$tmdb, $imdb];
    }

    private function resolveResolution(array $media): ?string
    {
        // Prefer Plex's own videoResolution field — height can be slightly below
        // nominal (e.g. 1038 for 1080p) due to aspect-ratio cropping, causing
        // height-based thresholds to misclassify the resolution.
        $raw = $media['videoResolution'] ?? null;
        if ($raw !== null) {
            if (is_numeric($raw)) {
                $r = (int) $raw;
                if ($r >= 2160) return '2160p';
                if ($r >= 1080) return '1080p';
                if ($r >= 720) return '720p';
                if ($r >= 480) return '480p';
            }
            if (strtolower($raw) === '4k') return '2160p';
            return $raw;
        }

        // Use generous thresholds: 1080p content with aspect-ratio cropping
        // can have heights as low as ~1036; 720p tops out at 720.
        $h = (int) ($media['height'] ?? 0);
        if ($h >= 2000) return '2160p';
        if ($h >= 1000) return '1080p';
        if ($h >= 600) return '720p';
        if ($h >= 400) return '480p';
        return null;
    }

    private function extractHdrType(array $streams): ?string
    {
        foreach ($streams as $stream) {
            if (($stream['streamType'] ?? 0) !== 1) {
                continue;
            }
            $profile = strtolower($stream['DOVIProfile'] ?? '');
            if ($profile !== '') {
                return 'Dolby Vision';
            }
            $colorTrc = strtolower($stream['colorTrc'] ?? '');
            if (str_contains($colorTrc, 'smpte2084')) {
                return 'HDR10';
            }
            if (str_contains($colorTrc, 'arib-std-b67')) {
                return 'HLG';
            }
        }
        return null;
    }

    private function extractRelativePath(string $absolutePath, string $libraryRoot): ?string
    {
        if ($absolutePath === '') {
            return null;
        }
        $root = rtrim($libraryRoot, '/\\');
        if ($root !== '' && str_starts_with($absolutePath, $root)) {
            $rel = ltrim(substr($absolutePath, strlen($root)), '/\\');
            return dirname($rel) === '.' ? $rel : dirname($rel);
        }
        return dirname($absolutePath) ?: $absolutePath;
    }
}
