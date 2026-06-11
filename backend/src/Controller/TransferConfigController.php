<?php

namespace App\Controller;

use App\Entity\TransferConfig;
use App\Repository\ProfileRepository;
use App\Service\DiskUsageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/profiles/{id}/transfer-config')]
class TransferConfigController extends AbstractApiController
{
    public function __construct(
        private readonly ProfileRepository $profileRepo,
        private readonly EntityManagerInterface $em,
        private readonly DiskUsageService $diskUsage,
    ) {}

    #[Route('', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $profile = $this->profileRepo->find($id);
        if (!$profile) {
            return $this->notFound();
        }

        $config = $profile->getTransferConfig();
        if (!$config) {
            return $this->json(null);
        }

        return $this->json($this->configToArray($config));
    }

    #[Route('', methods: ['PUT', 'PATCH'])]
    public function upsert(int $id, Request $request): JsonResponse
    {
        $profile = $this->profileRepo->find($id);
        if (!$profile) {
            return $this->notFound();
        }

        $data   = json_decode($request->getContent(), true) ?? [];
        $config = $profile->getTransferConfig() ?? new TransferConfig();

        if ($config->getId() === null) {
            $config->setProfile($profile);
            $this->em->persist($config);
        }

        if (isset($data['method'])) {
            $config->setMethod($data['method']);
        }
        if (isset($data['config']) && is_array($data['config'])) {
            $config->setConfig($data['config']);
        }

        $this->em->flush();

        return $this->json($this->configToArray($config));
    }

    #[Route('/disk-usage', methods: ['GET'])]
    public function diskUsage(int $id): JsonResponse
    {
        $profile = $this->profileRepo->find($id);
        if (!$profile) {
            return $this->notFound();
        }

        $config = $profile->getTransferConfig();
        if (!$config) {
            return $this->json(null);
        }

        try {
            return $this->json($this->diskUsage->getUsage($config));
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    private function configToArray(TransferConfig $c): array
    {
        $cfg = $c->getConfig();
        // Mask password in response
        if (isset($cfg['password'])) {
            $cfg['password'] = '***';
        }
        return [
            'id'        => $c->getId(),
            'profileId' => $c->getProfile()->getId(),
            'method'    => $c->getMethod(),
            'config'    => $cfg,
        ];
    }
}
