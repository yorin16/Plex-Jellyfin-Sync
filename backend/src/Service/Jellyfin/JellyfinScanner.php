<?php

namespace App\Service\Jellyfin;

use App\Entity\CachedMedia;
use App\Entity\MediaConnection;
use App\Repository\CachedMediaRepository;
use Doctrine\ORM\EntityManagerInterface;

class JellyfinScanner
{
    public function __construct(
        private readonly JellyfinClient $client,
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

        $count = 0;

        foreach ($movies as $movie) {
            $externalId = $movie['Id'] ?? '';
            if ($externalId === '') {
                continue;
            }

            $item = $this->cachedMediaRepository->findByConnectionAndExternalId($connection, $externalId)
                ?? (new CachedMedia())->setConnection($connection)->setExternalId($externalId);

            $item->setTitle($movie['Name'] ?? 'Unknown');
            $item->setYear(isset($movie['ProductionYear']) ? (int) $movie['ProductionYear'] : null);
            $item->setLastSeenAt(new \DateTimeImmutable());

            $providers = $movie['ProviderIds'] ?? [];
            $item->setTmdbId($providers['Tmdb'] ?? null);
            $item->setImdbId($providers['Imdb'] ?? null);

            $item->setPath($this->extractRelativePath($movie['Path'] ?? '', $connection->getLibraryRoot() ?? ''));
            $item->setFileSize(isset($movie['Size']) ? (int) $movie['Size'] : null);

            $streams = $movie['MediaStreams'] ?? [];
            $item->setCodec($this->extractVideoCodec($streams));
            $item->setResolution($this->extractResolution($streams));
            $item->setHdrType($this->extractHdrType($streams));
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

    private function extractVideoCodec(array $streams): ?string
    {
        foreach ($streams as $stream) {
            if (($stream['Type'] ?? '') === 'Video') {
                return $stream['Codec'] ?? null;
            }
        }
        return null;
    }

    private function extractResolution(array $streams): ?string
    {
        foreach ($streams as $stream) {
            if (($stream['Type'] ?? '') === 'Video') {
                $h = (int) ($stream['Height'] ?? 0);
                if ($h >= 2160) return '2160p';
                if ($h >= 1080) return '1080p';
                if ($h >= 720) return '720p';
                if ($h >= 480) return '480p';
            }
        }
        return null;
    }

    private function extractHdrType(array $streams): ?string
    {
        foreach ($streams as $stream) {
            if (($stream['Type'] ?? '') === 'Video') {
                $videoRangeType = $stream['VideoRangeType'] ?? '';
                if ($videoRangeType !== '') {
                    return $videoRangeType;
                }
                $videoRange = strtolower($stream['VideoRange'] ?? '');
                if ($videoRange === 'hdr') {
                    return 'HDR10';
                }
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
