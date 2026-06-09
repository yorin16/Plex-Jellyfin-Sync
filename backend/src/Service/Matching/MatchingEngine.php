<?php

namespace App\Service\Matching;

use App\Entity\CachedMedia;
use App\Entity\MediaConnection;
use App\Entity\Profile;
use App\Repository\CachedMediaRepository;
use App\Repository\IgnoredItemRepository;
use App\Repository\MatchOverrideRepository;

class MatchingEngine
{
    private const FUZZY_THRESHOLD = 85;

    public function __construct(
        private readonly CachedMediaRepository $cachedMediaRepo,
        private readonly MatchOverrideRepository $overrideRepo,
        private readonly IgnoredItemRepository $ignoredRepo,
    ) {}

    public function compare(Profile $profile): ComparisonResult
    {
        [$sourceConn, $destConn] = $this->resolveConnections($profile);

        if ($sourceConn === null || $destConn === null) {
            return new ComparisonResult([], [], [], []);
        }

        $sourceItems = $this->cachedMediaRepo->findByConnection($sourceConn);
        $destItems   = $this->cachedMediaRepo->findByConnection($destConn);

        $overrideMap = $this->overrideRepo->findOverrideMapForProfile($profile);
        $ignoredIds  = array_flip($this->ignoredRepo->findIgnoredSourceIds($profile));

        $destByTmdb  = [];
        $destByImdb  = [];
        $destByTitle = [];
        $matchedDestIds = [];

        foreach ($destItems as $d) {
            if ($d->getTmdbId()) {
                $destByTmdb[$d->getTmdbId()] = $d;
            }
            if ($d->getImdbId()) {
                $destByImdb[$d->getImdbId()] = $d;
            }
            $key = $this->normalizeTitle($d->getTitle()) . '|' . ($d->getYear() ?? '');
            $destByTitle[$key] = $d;
        }

        $matched        = [];
        $onlyInSource   = [];
        $potentialMatches = [];
        $matchedSourceIds = [];

        foreach ($sourceItems as $source) {
            if (isset($ignoredIds[$source->getId()])) {
                continue;
            }

            // Manual override
            if (isset($overrideMap[$source->getId()])) {
                $destId = $overrideMap[$source->getId()];
                $destMatch = $this->findDestById($destItems, $destId);
                if ($destMatch !== null) {
                    $matched[] = ['source' => $source, 'destination' => $destMatch, 'matchType' => 'override'];
                    $matchedDestIds[$destMatch->getId()] = true;
                    $matchedSourceIds[$source->getId()] = true;
                    continue;
                }
            }

            // TMDB ID
            if ($source->getTmdbId() && isset($destByTmdb[$source->getTmdbId()])) {
                $d = $destByTmdb[$source->getTmdbId()];
                $matched[] = ['source' => $source, 'destination' => $d, 'matchType' => 'tmdb'];
                $matchedDestIds[$d->getId()] = true;
                $matchedSourceIds[$source->getId()] = true;
                continue;
            }

            // IMDB ID
            if ($source->getImdbId() && isset($destByImdb[$source->getImdbId()])) {
                $d = $destByImdb[$source->getImdbId()];
                $matched[] = ['source' => $source, 'destination' => $d, 'matchType' => 'imdb'];
                $matchedDestIds[$d->getId()] = true;
                $matchedSourceIds[$source->getId()] = true;
                continue;
            }

            // Title + year
            $key = $this->normalizeTitle($source->getTitle()) . '|' . ($source->getYear() ?? '');
            if (isset($destByTitle[$key])) {
                $d = $destByTitle[$key];
                $matched[] = ['source' => $source, 'destination' => $d, 'matchType' => 'title_year'];
                $matchedDestIds[$d->getId()] = true;
                $matchedSourceIds[$source->getId()] = true;
                continue;
            }

            // Fuzzy match — flag only, never auto-accept
            $fuzzyMatch = $this->findFuzzyMatch($source, $destItems);
            if ($fuzzyMatch !== null) {
                $potentialMatches[] = ['source' => $source, 'destination' => $fuzzyMatch];
                continue;
            }

            $onlyInSource[] = $source;
        }

        $onlyInDest = array_filter($destItems, fn($d) => !isset($matchedDestIds[$d->getId()]));

        return new ComparisonResult(
            onlyInSource: $onlyInSource,
            onlyInDestination: array_values($onlyInDest),
            potentialMatches: $potentialMatches,
            matched: $matched,
        );
    }

    private function normalizeTitle(string $title): string
    {
        $title = mb_strtolower($title);
        $title = preg_replace('/^(the|a|an)\s+/', '', $title);
        $title = preg_replace('/[^\w\s]/', '', $title);
        $title = preg_replace('/\s+/', ' ', trim($title));
        return $title;
    }

    private function findFuzzyMatch(CachedMedia $source, array $candidates): ?CachedMedia
    {
        $sourceNorm = $this->normalizeTitle($source->getTitle());
        $sourceYear = $source->getYear();

        foreach ($candidates as $candidate) {
            if ($sourceYear !== null && $candidate->getYear() !== null && abs($candidate->getYear() - $sourceYear) > 1) {
                continue;
            }
            $candidateNorm = $this->normalizeTitle($candidate->getTitle());
            similar_text($sourceNorm, $candidateNorm, $percent);
            if ($percent >= self::FUZZY_THRESHOLD) {
                return $candidate;
            }
        }
        return null;
    }

    /** @param CachedMedia[] $items */
    private function findDestById(array $items, int $id): ?CachedMedia
    {
        foreach ($items as $item) {
            if ($item->getId() === $id) {
                return $item;
            }
        }
        return null;
    }

    private function resolveConnections(Profile $profile): array
    {
        $source = null;
        $dest = null;
        foreach ($profile->getConnections() as $conn) {
            if ($conn->getRole() === MediaConnection::ROLE_SOURCE) {
                $source = $conn;
            } elseif ($conn->getRole() === MediaConnection::ROLE_DESTINATION) {
                $dest = $conn;
            }
        }
        return [$source, $dest];
    }
}
