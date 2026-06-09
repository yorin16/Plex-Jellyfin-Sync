<?php

namespace App\Controller;

use App\Repository\TransferJobRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/dashboard')]
class DashboardController extends AbstractApiController
{
    public function __construct(private readonly TransferJobRepository $jobRepo) {}

    #[Route('', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $counts = $this->jobRepo->getDashboardCounts();

        $active = $this->jobRepo->findBy(['status' => 'running']);

        return $this->json([
            'counts'     => $counts,
            'activeJobs' => array_map(fn($j) => $this->jobToArray($j), $active),
        ]);
    }
}
