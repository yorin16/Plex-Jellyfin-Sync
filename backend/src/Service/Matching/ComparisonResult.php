<?php

namespace App\Service\Matching;

use App\Entity\CachedMedia;

final class ComparisonResult
{
    /**
     * @param CachedMedia[] $onlyInSource
     * @param CachedMedia[] $onlyInDestination
     * @param array{source: CachedMedia, destination: CachedMedia}[] $potentialMatches
     * @param array{source: CachedMedia, destination: CachedMedia, matchType: string}[] $matched
     */
    public function __construct(
        public readonly array $onlyInSource,
        public readonly array $onlyInDestination,
        public readonly array $potentialMatches,
        public readonly array $matched,
    ) {}
}
