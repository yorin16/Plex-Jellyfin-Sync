<?php

namespace App\Service\Jellyfin;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class JellyfinClient
{
    public function __construct(private readonly HttpClientInterface $httpClient) {}

    public function testConnection(string $url, string $token): bool
    {
        try {
            $response = $this->httpClient->request('GET', rtrim($url, '/') . '/System/Info/Public', [
                'query' => ['api_key' => $token],
                'timeout' => 10,
            ]);
            return $response->getStatusCode() === 200;
        } catch (\Throwable) {
            return false;
        }
    }

    public function getLibraries(string $url, string $token): array
    {
        $response = $this->httpClient->request('GET', rtrim($url, '/') . '/Library/VirtualFolders', [
            'query' => ['api_key' => $token],
        ]);

        $folders = $response->toArray();

        return array_map(fn($f) => [
            'id' => $f['ItemId'] ?? '',
            'title' => $f['Name'] ?? '',
            'type' => $f['CollectionType'] ?? '',
        ], array_filter($folders, fn($f) => strtolower($f['CollectionType'] ?? '') === 'movies'));
    }

    public function getMovies(string $url, string $token, string $libraryId): array
    {
        $response = $this->httpClient->request('GET', rtrim($url, '/') . '/Items', [
            'query' => [
                'api_key' => $token,
                'IncludeItemTypes' => 'Movie',
                'Recursive' => 'true',
                'Fields' => 'ProviderIds,MediaStreams,Path',
                'ParentId' => $libraryId,
                'Limit' => 5000,
            ],
        ]);

        $data = $response->toArray();
        return $data['Items'] ?? [];
    }

    public function triggerLibraryScan(string $url, string $token): void
    {
        try {
            $this->httpClient->request('POST', rtrim($url, '/') . '/Library/Refresh', [
                'query' => ['api_key' => $token],
            ])->getStatusCode();
        } catch (\Throwable) {
            // non-fatal: log in handler
        }
    }
}
