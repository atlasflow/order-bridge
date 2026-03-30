<?php

declare(strict_types=1);

namespace Atlasflow\OrderBridge;

/**
 * Package-wide constants derived from the Atlas Core Order Bridge API specification.
 * Never hardcode these values elsewhere in the package.
 */
final class SchemaVersion
{
    /** Current schema version as declared in the spec title and §2.1. */
    public const string SCHEMA_VERSION = '1.3.4';

    /** Arithmetic tolerance used in §5.3 validation rules. */
    public const string TOLERANCE = '0.0001';

    /** bcmath scale for monetary values (prices, totals, VAT). */
    public const int MONETARY_SCALE = 4;

    /** bcmath scale for discount percentage strings. */
    public const int DISCOUNT_SCALE = 6;
}
