<?php

namespace App\Controller;

use App\Entity\TranscodeProfile;
use App\Repository\TranscodeProfileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/transcode-profiles')]
class TranscodeProfileController extends AbstractApiController
{
    public function __construct(
        private readonly TranscodeProfileRepository $repo,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $profiles = $this->repo->findAll();
        return $this->json(array_map(fn($p) => $this->profileToArray($p), $profiles));
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data    = json_decode($request->getContent(), true) ?? [];
        $profile = new TranscodeProfile();
        $this->applyData($profile, $data);
        $this->em->persist($profile);
        $this->em->flush();
        return $this->json($this->profileToArray($profile), 201);
    }

    #[Route('/{id}', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $profile = $this->repo->find($id);
        if (!$profile) {
            return $this->notFound();
        }
        $data = json_decode($request->getContent(), true) ?? [];
        $this->applyData($profile, $data);
        $this->em->flush();
        return $this->json($this->profileToArray($profile));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $profile = $this->repo->find($id);
        if (!$profile) {
            return $this->notFound();
        }
        $this->em->remove($profile);
        $this->em->flush();
        return $this->json(null, 204);
    }

    private function applyData(TranscodeProfile $profile, array $data): void
    {
        if (isset($data['name']))                    $profile->setName($data['name']);
        if (isset($data['videoCodec']))              $profile->setVideoCodec($data['videoCodec']);
        if (isset($data['videoBitrateKbps']))        $profile->setVideoBitrateKbps((int) $data['videoBitrateKbps']);
        if (array_key_exists('maxHeight', $data))    $profile->setMaxHeight($data['maxHeight'] !== null ? (int) $data['maxHeight'] : null);
        if (isset($data['hdrToSdr']))                $profile->setHdrToSdr((bool) $data['hdrToSdr']);
        if (isset($data['losslessAudioCodec']))      $profile->setLosslessAudioCodec($data['losslessAudioCodec']);
        if (isset($data['losslessAudioBitrateKbps'])) $profile->setLosslessAudioBitrateKbps((int) $data['losslessAudioBitrateKbps']);
    }

    private function profileToArray(TranscodeProfile $p): array
    {
        return [
            'id'                       => $p->getId(),
            'name'                     => $p->getName(),
            'videoCodec'               => $p->getVideoCodec(),
            'videoBitrateKbps'         => $p->getVideoBitrateKbps(),
            'maxHeight'                => $p->getMaxHeight(),
            'hdrToSdr'                 => $p->isHdrToSdr(),
            'losslessAudioCodec'       => $p->getLosslessAudioCodec(),
            'losslessAudioBitrateKbps' => $p->getLosslessAudioBitrateKbps(),
        ];
    }
}
