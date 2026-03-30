<?php

declare(strict_types=1);

namespace Atlasflow\OrderBridge\Support;

use Atlasflow\OrderBridge\SchemaVersion;

/**
 * All monetary and percentage arithmetic in the package passes through this class.
 * Uses PHP's bcmath extension exclusively — no native float operations.
 */
final class DecimalMath
{
    /**
     * Multiply two decimal strings.
     *
     * @param int $scale Number of decimal places to retain.
     */
    public static function multiply(string $a, string $b, int $scale): string
    {
        return bcmul($a, $b, $scale);
    }

    /**
     * Add two decimal strings, returning a result at MONETARY_SCALE precision.
     */
    public static function add(string $a, string $b, int $scale = SchemaVersion::MONETARY_SCALE): string
    {
        return bcadd($a, $b, $scale);
    }

    /**
     * Subtract $b from $a, returning a result at MONETARY_SCALE precision.
     */
    public static function subtract(string $a, string $b, int $scale = SchemaVersion::MONETARY_SCALE): string
    {
        return bcsub($a, $b, $scale);
    }

    /**
     * Divide $a by $b.
     *
     * @param int $scale Number of decimal places to retain.
     */
    public static function divide(string $a, string $b, int $scale): string
    {
        return bcdiv($a, $b, $scale);
    }

    /**
     * Format a bcmath result string to exactly $decimals decimal places (truncating,
     * not rounding, to avoid introducing amounts not derived from the spec formulas).
     *
     * @param int $decimals Target decimal places (typically 4 for monetary, 6 for discount).
     */
    public static function format(string $value, int $decimals): string
    {
        $truncated = bcadd($value, '0', $decimals);

        if ($decimals === 0) {
            return $truncated;
        }

        if (!str_contains($truncated, '.')) {
            return $truncated . '.' . str_repeat('0', $decimals);
        }

        [$int, $dec] = explode('.', $truncated);
        $dec = str_pad(substr($dec, 0, $decimals), $decimals, '0');

        return $int . '.' . $dec;
    }

    /**
     * Return true if the absolute difference between $a and $b is within $tolerance.
     *
     * Used to implement the ±0.0001 tolerance checks defined in §5.3.
     */
    public static function withinTolerance(string $a, string $b, string $tolerance): bool
    {
        $scale = SchemaVersion::MONETARY_SCALE + 4;
        $diff = bcsub($a, $b, $scale);

        if (bccomp($diff, '0', $scale) < 0) {
            $diff = bcsub('0', $diff, $scale);
        }

        return bccomp($diff, $tolerance, $scale) <= 0;
    }
}
