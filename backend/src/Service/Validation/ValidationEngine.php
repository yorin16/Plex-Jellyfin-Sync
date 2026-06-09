<?php

namespace App\Service\Validation;

use App\Entity\CachedMedia;
use App\Entity\Profile;
use App\Entity\ValidationRule;

class ValidationEngine
{
    public function check(CachedMedia $item, Profile $profile): ValidationResult
    {
        $triggered = [];

        foreach ($profile->getValidationRules() as $rule) {
            if ($this->evaluate($item, $rule)) {
                $triggered[] = $rule;
            }
        }

        if (empty($triggered)) {
            return new ValidationResult(ValidationResult::STATUS_ALLOWED, []);
        }

        $isRejected = false;
        foreach ($triggered as $rule) {
            if ($rule->getAction() === ValidationRule::ACTION_REJECT) {
                $isRejected = true;
                break;
            }
        }

        return new ValidationResult(
            $isRejected ? ValidationResult::STATUS_REJECTED : ValidationResult::STATUS_FLAGGED,
            $triggered,
        );
    }

    private function evaluate(CachedMedia $item, ValidationRule $rule): bool
    {
        $actual = match ($rule->getRuleType()) {
            ValidationRule::TYPE_CODEC        => $item->getCodec() ?? '',
            ValidationRule::TYPE_HDR_TYPE     => $item->getHdrType() ?? '',
            ValidationRule::TYPE_RESOLUTION   => $item->getResolution() ?? '',
            ValidationRule::TYPE_FILE_SIZE_MAX => (string) ($item->getFileSize() ?? 0),
            default => '',
        };

        $expected = $rule->getValue();

        return match ($rule->getOperator()) {
            ValidationRule::OP_EQUALS      => strtolower($actual) === strtolower($expected),
            ValidationRule::OP_NOT_EQUALS  => strtolower($actual) !== strtolower($expected),
            ValidationRule::OP_CONTAINS    => str_contains(strtolower($actual), strtolower($expected)),
            ValidationRule::OP_GT          => (float) $actual > (float) $expected,
            ValidationRule::OP_LT          => (float) $actual < (float) $expected,
            default => false,
        };
    }
}
