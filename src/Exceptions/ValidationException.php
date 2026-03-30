<?php

declare(strict_types=1);

namespace Atlasflow\OrderBridge\Exceptions;

use Atlasflow\OrderBridge\Validator\ValidationResult;
use RuntimeException;

final class ValidationException extends RuntimeException
{
    public function __construct(private readonly ValidationResult $result)
    {
        $count = count($result->getViolations());
        parent::__construct("Payload validation failed with {$count} violation(s).");
    }

    public function getResult(): ValidationResult
    {
        return $this->result;
    }
}
