<?php

namespace App\Controller;

use App\Entity\MediaConnection;
use App\Repository\MediaConnectionRepository;
use App\Repository\ProfileRepository;
use App\Service\Jellyfin\JellyfinClient;
use App\Service\Plex\PlexClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class MediaConnectionController extends AbstractApiController
{
    public function __construct(
        private readonly ProfileRepository $profileRepo,
        private readonly MediaConnectionRepository $connRepo,
        private readonly PlexClient $plexClient,
        private readonly JellyfinClient $jellyfinClient,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/profiles/{id}/connections', methods: ['GET'])]
    public function index(int $id): JsonResponse
    {
        $profile = $this->profileRepo->find($id);
        if (!$profile) {
            return $this->notFound();
        }
        return $this->json(array_map(fn($c) => $this->connToArray($c), $profile->getConnections()->toArray()));
    }

    #[Route('/profiles/{id}/connections', methods: ['POST'])]
    public function create(int $id, Request $request): JsonResponse
    {
        $profile = $this->profileRepo->find($id);
        if (!$profile) {
            return $this->notFound();
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $conn = $this->hydrateConnection(new MediaConnection(), $data);
        $conn->setProfile($profile);

        $this->em->persist($conn);
        $this->em->flush();

        return $this->json($this->connToArray($conn), 201);
    }

    #[Route('/connections/{id}', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $conn = $this->connRepo->find($id);
        if (!$conn) {
            return $this->notFound();
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $this->hydrateConnection($conn, $data);
        $this->em->flush();

        return $this->json($this->connToArray($conn));
    }

    #[Route('/connections/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $conn = $this->connRepo->find($id);
        if (!$conn) {
            return $this->notFound();
        }
        $this->em->remove($conn);
        $this->em->flush();

        return $this->json(null, 204);
    }

    #[Route('/connections/{id}/test', methods: ['POST'])]
    public function test(int $id): JsonResponse
    {
        $conn = $this->connRepo->find($id);
        if (!$conn) {
            return $this->notFound();
        }

        $ok = match ($conn->getType()) {
            MediaConnection::TYPE_PLEX     => $this->plexClient->testConnection($conn->getUrl(), $conn->getApiToken()),
            MediaConnection::TYPE_JELLYFIN => $this->jellyfinClient->testConnection($conn->getUrl(), $conn->getApiToken()),
            default => false,
        };

        return $this->json(['success' => $ok]);
    }

    #[Route('/connections/{id}/libraries', methods: ['GET'])]
    public function libraries(int $id): JsonResponse
    {
        $conn = $this->connRepo->find($id);
        if (!$conn) {
            return $this->notFound();
        }

        try {
            $libraries = match ($conn->getType()) {
                MediaConnection::TYPE_PLEX     => $this->plexClient->getLibraries($conn->getUrl(), $conn->getApiToken()),
                MediaConnection::TYPE_JELLYFIN => $this->jellyfinClient->getLibraries($conn->getUrl(), $conn->getApiToken()),
                default => [],
            };
            return $this->json($libraries);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 502);
        }
    }

    private function hydrateConnection(MediaConnection $conn, array $data): MediaConnection
    {
        if (isset($data['role']))       $conn->setRole($data['role']);
        if (isset($data['type']))       $conn->setType($data['type']);
        if (isset($data['url']))        $conn->setUrl($data['url']);
        if (isset($data['apiToken']))   $conn->setApiToken($data['apiToken']);
        if (isset($data['libraryId']))  $conn->setLibraryId($data['libraryId']);
        if (isset($data['libraryRoot'])) $conn->setLibraryRoot($data['libraryRoot']);
        return $conn;
    }

    private function connToArray(MediaConnection $c): array
    {
        return [
            'id'            => $c->getId(),
            'profileId'     => $c->getProfile()->getId(),
            'role'          => $c->getRole(),
            'type'          => $c->getType(),
            'url'           => $c->getUrl(),
            'libraryId'     => $c->getLibraryId(),
            'libraryRoot'   => $c->getLibraryRoot(),
            'lastScannedAt' => $c->getLastScannedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
