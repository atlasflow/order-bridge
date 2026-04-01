<?php

declare(strict_types=1);

namespace Atlasflow\OrderBridge\Support;

/**
 * Rounding strategy applied to VAT amounts before output.
 *
 * - Ceil  rounds toward positive infinity (always rounds up for positive values).
 * - Floor rounds toward negative infinity (always rounds down for positive values).
 */
enum RoundingMode: string
{
    case Ceil = 'ceil';
    case Floor = 'floor';
}
