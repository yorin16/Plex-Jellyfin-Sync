<?php

namespace App\Controller;

use App\Entity\MediaConnection;
use App\Repository\ProfileRepository;
use App\Service\Jellyfin\JellyfinScanner;
use App\Service\Plex\PlexScanner;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/profiles/{id}/scan')]
class ScanController extends AbstractApiController
{
    public function __construct(
        private readonly ProfileRepository $profileRepo,
        private readonly PlexScanner $plexScanner,
        private readonly JellyfinScanner $jellyfinScanner,
    ) {}

    #[Route('', methods: ['POST'])]
    public function scan(int $id): JsonResponse
    {
        $profile = $this->profileRepo->find($id);
        if (!$profile) {
            return $this->notFound();
        }

        $results = [];

        foreach ($profile->getConnections() as $conn) {
            try {
                $count = match ($conn->getType()) {
                    MediaConnection::TYPE_PLEX     => $this->plexScanner->scan($conn),
                    MediaConnection::TYPE_JELLYFIN => $this->jellyfinScanner->scan($conn),
                    default => 0,
                };
                $results[] = [
                    'connectionId' => $conn->getId(),
                    'role'         => $conn->getRole(),
                    'type'         => $conn->getType(),
                    'itemsScanned' => $count,
                    'success'      => true,
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'connectionId' => $conn->getId(),
                    'role'         => $conn->getRole(),
                    'type'         => $conn->getType(),
                    'success'      => false,
                    'error'        => $e->getMessage(),
                ];
            }
        }

        return $this->json(['results' => $results]);
    }

    #[Route('/status', methods: ['GET'])]
    public function status(int $id): JsonResponse
    {
        $profile = $this->profileRepo->find($id);
        if (!$profile) {
            return $this->notFound();
        }

        $connections = array_map(fn($c) => [
            'connectionId'  => $c->getId(),
            'role'          => $c->getRole(),
            'type'          => $c->getType(),
            'lastScannedAt' => $c->getLastScannedAt()?->format(\DateTimeInterface::ATOM),
        ], $profile->getConnections()->toArray());

        return $this->json(['connections' => $connections]);
    }
}
