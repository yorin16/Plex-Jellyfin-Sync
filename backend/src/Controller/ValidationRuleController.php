<?php

namespace App\Controller;

use App\Entity\ValidationRule;
use App\Repository\ProfileRepository;
use App\Repository\ValidationRuleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class ValidationRuleController extends AbstractApiController
{
    public function __construct(
        private readonly ProfileRepository $profileRepo,
        private readonly ValidationRuleRepository $ruleRepo,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/profiles/{id}/rules', methods: ['GET'])]
    public function index(int $id): JsonResponse
    {
        $profile = $this->profileRepo->find($id);
        if (!$profile) {
            return $this->notFound();
        }
        return $this->json(array_map(fn($r) => $this->ruleToArray($r), $profile->getValidationRules()->toArray()));
    }

    #[Route('/profiles/{id}/rules', methods: ['POST'])]
    public function create(int $id, Request $request): JsonResponse
    {
        $profile = $this->profileRepo->find($id);
        if (!$profile) {
            return $this->notFound();
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $rule = new ValidationRule();
        $rule->setProfile($profile);
        $rule->setRuleType($data['ruleType'] ?? ValidationRule::TYPE_CODEC);
        $rule->setOperator($data['operator'] ?? ValidationRule::OP_EQUALS);
        $rule->setValue($data['value'] ?? '');
        $rule->setAction($data['action'] ?? ValidationRule::ACTION_REJECT);

        $this->em->persist($rule);
        $this->em->flush();

        return $this->json($this->ruleToArray($rule), 201);
    }

    #[Route('/rules/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $rule = $this->ruleRepo->find($id);
        if (!$rule) {
            return $this->notFound();
        }
        $this->em->remove($rule);
        $this->em->flush();

        return $this->json(null, 204);
    }

    private function ruleToArray(ValidationRule $r): array
    {
        return [
            'id'        => $r->getId(),
            'profileId' => $r->getProfile()->getId(),
            'ruleType'  => $r->getRuleType(),
            'operator'  => $r->getOperator(),
            'value'     => $r->getValue(),
            'action'    => $r->getAction(),
        ];
    }
}
