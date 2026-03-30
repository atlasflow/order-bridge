<?php

declare(strict_types=1);

namespace Atlasflow\OrderBridge\Validator;

/**
 * Describes a single validation rule failure within a payload.
 *
 * The field path uses dot notation to locate the offending value, e.g.
 * "orders.0.items.1.line_ex_vat" or "schema_version".
 */
final readonly class ValidationViolation
{
    public function __construct(
        /** Dot-notation path to the offending field. */
        public string $field,
        /** Short description of the rule that was violated. */
        public string $rule,
        /** Human-readable explanation of the failure. */
        public string $message,
        /** The value the validator expected, if applicable. */
        public mixed $expected = null,
        /** The value that was actually found. */
        public mixed $actual = null,
    ) {
    }
}
