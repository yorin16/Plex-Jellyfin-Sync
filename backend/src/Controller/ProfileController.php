<?php

namespace App\Controller;

use App\Entity\Profile;
use App\Repository\ProfileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/profiles')]
class ProfileController extends AbstractApiController
{
    public function __construct(
        private readonly ProfileRepository $profileRepo,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $profiles = $this->profileRepo->findAll();
        return $this->json(array_map(fn($p) => $this->profileToArray($p), $profiles));
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        if (empty($data['name'])) {
            return $this->badRequest('name is required');
        }

        $profile = new Profile();
        $profile->setName(trim($data['name']));
        $this->em->persist($profile);
        $this->em->flush();

        return $this->json($this->profileToArray($profile), 201);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $profile = $this->profileRepo->find($id);
        if (!$profile) {
            return $this->notFound();
        }
        return $this->json($this->profileToArray($profile));
    }

    #[Route('/{id}', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $profile = $this->profileRepo->find($id);
        if (!$profile) {
            return $this->notFound();
        }

        $data = json_decode($request->getContent(), true) ?? [];
        if (!empty($data['name'])) {
            $profile->setName(trim($data['name']));
        }
        $this->em->flush();

        return $this->json($this->profileToArray($profile));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $profile = $this->profileRepo->find($id);
        if (!$profile) {
            return $this->notFound();
        }
        $this->em->remove($profile);
        $this->em->flush();

        return $this->json(null, 204);
    }

    private function profileToArray(Profile $p): array
    {
        return [
            'id'        => $p->getId(),
            'name'      => $p->getName(),
            'createdAt' => $p->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
