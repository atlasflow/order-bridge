<?php

declare(strict_types=1);

namespace Atlasflow\OrderBridge\Validator;

use Atlasflow\OrderBridge\Exceptions\ValidationException;

/**
 * Accumulates validation violations produced by PayloadValidator.
 *
 * Callers that prefer exceptions should call assertValid() after validation.
 * Callers that want to enumerate all violations (e.g. for structured error
 * responses) should call getViolations() instead.
 */
final class ValidationResult
{
    /** @var ValidationViolation[] */
    private array $violations = [];

    public function addViolation(ValidationViolation $violation): void
    {
        $this->violations[] = $violation;
    }

    public function isValid(): bool
    {
        return $this->violations === [];
    }

    /**
     * @return ValidationViolation[]
     */
    public function getViolations(): array
    {
        return $this->violations;
    }

    /**
     * Throw a ValidationException if any violations were recorded.
     *
     * @throws ValidationException
     */
    public function assertValid(): void
    {
        if (!$this->isValid()) {
            throw new ValidationException($this);
        }
    }
}
