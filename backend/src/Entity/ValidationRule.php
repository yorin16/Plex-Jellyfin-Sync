<?php

namespace App\Entity;

use App\Repository\ValidationRuleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ValidationRuleRepository::class)]
#[ORM\Table(name: 'validation_rules')]
class ValidationRule
{
    public const TYPE_CODEC = 'codec';
    public const TYPE_HDR_TYPE = 'hdr_type';
    public const TYPE_RESOLUTION = 'resolution';
    public const TYPE_FILE_SIZE_MAX = 'file_size_max';

    public const OP_EQUALS = 'equals';
    public const OP_NOT_EQUALS = 'not_equals';
    public const OP_CONTAINS = 'contains';
    public const OP_GT = 'gt';
    public const OP_LT = 'lt';

    public const ACTION_REJECT = 'reject';
    public const ACTION_FLAG = 'flag';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Profile::class, inversedBy: 'validationRules')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Profile $profile;

    #[ORM\Column(length: 32)]
    private string $ruleType;

    #[ORM\Column(length: 16)]
    private string $operator;

    #[ORM\Column(length: 255)]
    private string $value;

    #[ORM\Column(length: 16)]
    private string $action;

    public function getId(): ?int { return $this->id; }
    public function getProfile(): Profile { return $this->profile; }
    public function setProfile(Profile $profile): static { $this->profile = $profile; return $this; }
    public function getRuleType(): string { return $this->ruleType; }
    public function setRuleType(string $ruleType): static { $this->ruleType = $ruleType; return $this; }
    public function getOperator(): string { return $this->operator; }
    public function setOperator(string $operator): static { $this->operator = $operator; return $this; }
    public function getValue(): string { return $this->value; }
    public function setValue(string $value): static { $this->value = $value; return $this; }
    public function getAction(): string { return $this->action; }
    public function setAction(string $action): static { $this->action = $action; return $this; }
}
