<?php

namespace App\Controller;

use App\Repository\ProfileRepository;
use App\Service\Matching\MatchingEngine;
use App\Service\Validation\ValidationEngine;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/profiles/{id}/comparison')]
class ComparisonController extends AbstractApiController
{
    public function __construct(
        private readonly ProfileRepository $profileRepo,
        private readonly MatchingEngine $matchingEngine,
        private readonly ValidationEngine $validationEngine,
    ) {}

    #[Route('', methods: ['GET'])]
    public function compare(int $id): JsonResponse
    {
        $profile = $this->profileRepo->find($id);
        if (!$profile) {
            return $this->notFound();
        }

        $result = $this->matchingEngine->compare($profile);

        $onlyInSource = array_map(function ($item) use ($profile) {
            $validation = $this->validationEngine->check($item, $profile);
            $arr = $this->mediaToArray($item);
            $arr['validation'] = [
                'status'       => $validation->status,
                'triggeredRules' => array_map(fn($r) => [
                    'id'       => $r->getId(),
                    'ruleType' => $r->getRuleType(),
                    'operator' => $r->getOperator(),
                    'value'    => $r->getValue(),
                    'action'   => $r->getAction(),
                ], $validation->triggeredRules),
            ];
            return $arr;
        }, $result->onlyInSource);

        return $this->json([
            'onlyInSource'      => $onlyInSource,
            'onlyInDestination' => array_map(fn($m) => $this->mediaToArray($m), $result->onlyInDestination),
            'potentialMatches'  => array_map(fn($pair) => [
                'source'      => $this->mediaToArray($pair['source']),
                'destination' => $this->mediaToArray($pair['destination']),
            ], $result->potentialMatches),
            'matched' => array_map(fn($pair) => [
                'source'      => $this->mediaToArray($pair['source']),
                'destination' => $this->mediaToArray($pair['destination']),
                'matchType'   => $pair['matchType'],
            ], $result->matched),
        ]);
    }
}
