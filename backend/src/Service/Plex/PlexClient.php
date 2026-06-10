<?php

namespace App\Service\Plex;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class PlexClient
{
    public function __construct(private readonly HttpClientInterface $httpClient) {}

    public function testConnection(string $url, string $token): bool
    {
        try {
            $response = $this->httpClient->request('GET', rtrim($url, '/') . '/identity', [
                'headers' => ['X-Plex-Token' => $token, 'Accept' => 'application/json'],
                'timeout' => 10,
            ]);
            return $response->getStatusCode() === 200;
        } catch (\Throwable) {
            return false;
        }
    }

    public function getLibraries(string $url, string $token): array
    {
        $response = $this->httpClient->request('GET', rtrim($url, '/') . '/library/sections', [
            'headers' => ['X-Plex-Token' => $token, 'Accept' => 'application/json'],
        ]);

        $data = $response->toArray();
        $sections = $data['MediaContainer']['Directory'] ?? [];

        return array_map(fn($s) => [
            'id' => (string) $s['key'],
            'title' => $s['title'],
            'type' => $s['type'],
        ], array_filter($sections, fn($s) => $s['type'] === 'movie'));
    }

    public function getMovies(string $url, string $token, string $libraryId): array
    {
        $response = $this->httpClient->request('GET', rtrim($url, '/') . "/library/sections/{$libraryId}/all", [
            'query' => ['type' => 1, 'includeGuids' => 1],
            'headers' => ['X-Plex-Token' => $token, 'Accept' => 'application/json'],
        ]);

        $data = $response->toArray();
        return $data['MediaContainer']['Metadata'] ?? [];
    }
}
