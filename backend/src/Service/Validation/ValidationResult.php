<?php

namespace App\Service\Validation;

use App\Entity\ValidationRule;

final class ValidationResult
{
    public const STATUS_ALLOWED  = 'allowed';
    public const STATUS_FLAGGED  = 'flagged';
    public const STATUS_REJECTED = 'rejected';

    /** @param ValidationRule[] $triggeredRules */
    public function __construct(
        public readonly string $status,
        public readonly array $triggeredRules,
    ) {}

    public function isAllowed(): bool  { return $this->status === self::STATUS_ALLOWED; }
    public function isFlagged(): bool  { return $this->status === self::STATUS_FLAGGED; }
    public function isRejected(): bool { return $this->status === self::STATUS_REJECTED; }
}
