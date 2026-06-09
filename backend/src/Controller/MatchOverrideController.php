<?php

namespace App\Controller;

use App\Entity\IgnoredItem;
use App\Entity\MatchOverride;
use App\Repository\CachedMediaRepository;
use App\Repository\IgnoredItemRepository;
use App\Repository\MatchOverrideRepository;
use App\Repository\ProfileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class MatchOverrideController extends AbstractApiController
{
    public function __construct(
        private readonly ProfileRepository $profileRepo,
        private readonly CachedMediaRepository $mediaRepo,
        private readonly MatchOverrideRepository $overrideRepo,
        private readonly IgnoredItemRepository $ignoredRepo,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/profiles/{id}/overrides', methods: ['POST'])]
    public function createOverride(int $id, Request $request): JsonResponse
    {
        $profile = $this->profileRepo->find($id);
        if (!$profile) {
            return $this->notFound();
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $sourceMedia = $this->mediaRepo->find((int) ($data['sourceMediaId'] ?? 0));
        $destMedia   = $this->mediaRepo->find((int) ($data['destinationMediaId'] ?? 0));

        if (!$sourceMedia || !$destMedia) {
            return $this->badRequest('sourceMediaId and destinationMediaId are required');
        }

        $existing = $this->overrideRepo->findOneBy(['profile' => $profile, 'sourceMedia' => $sourceMedia]);
        if ($existing) {
            $existing->setDestinationMedia($destMedia);
            $this->em->flush();
            return $this->json($this->overrideToArray($existing));
        }

        $override = new MatchOverride();
        $override->setProfile($profile);
        $override->setSourceMedia($sourceMedia);
        $override->setDestinationMedia($destMedia);
        $this->em->persist($override);
        $this->em->flush();

        return $this->json($this->overrideToArray($override), 201);
    }

    #[Route('/overrides/{id}', methods: ['DELETE'])]
    public function deleteOverride(int $id): JsonResponse
    {
        $override = $this->overrideRepo->find($id);
        if (!$override) {
            return $this->notFound();
        }
        $this->em->remove($override);
        $this->em->flush();

        return $this->json(null, 204);
    }

    #[Route('/profiles/{id}/ignore', methods: ['POST'])]
    public function createIgnore(int $id, Request $request): JsonResponse
    {
        $profile = $this->profileRepo->find($id);
        if (!$profile) {
            return $this->notFound();
        }

        $data        = json_decode($request->getContent(), true) ?? [];
        $sourceMedia = $this->mediaRepo->find((int) ($data['sourceMediaId'] ?? 0));
        if (!$sourceMedia) {
            return $this->badRequest('sourceMediaId is required');
        }

        $existing = $this->ignoredRepo->findOneBy(['profile' => $profile, 'sourceMedia' => $sourceMedia]);
        if ($existing) {
            return $this->json($this->ignoredToArray($existing));
        }

        $ignored = new IgnoredItem();
        $ignored->setProfile($profile);
        $ignored->setSourceMedia($sourceMedia);
        $this->em->persist($ignored);
        $this->em->flush();

        return $this->json($this->ignoredToArray($ignored), 201);
    }

    #[Route('/ignored/{id}', methods: ['DELETE'])]
    public function deleteIgnore(int $id): JsonResponse
    {
        $ignored = $this->ignoredRepo->find($id);
        if (!$ignored) {
            return $this->notFound();
        }
        $this->em->remove($ignored);
        $this->em->flush();

        return $this->json(null, 204);
    }

    private function overrideToArray(MatchOverride $o): array
    {
        return [
            'id'                => $o->getId(),
            'profileId'         => $o->getProfile()->getId(),
            'sourceMediaId'     => $o->getSourceMedia()->getId(),
            'destinationMediaId' => $o->getDestinationMedia()->getId(),
            'createdAt'         => $o->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function ignoredToArray(IgnoredItem $i): array
    {
        return [
            'id'            => $i->getId(),
            'profileId'     => $i->getProfile()->getId(),
            'sourceMediaId' => $i->getSourceMedia()->getId(),
            'createdAt'     => $i->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
