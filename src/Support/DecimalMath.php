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
     * Round a bcmath string to exactly $decimals decimal places using the given mode.
     *
     * - RoundingMode::Ceil  — toward positive infinity: positive values with a remainder
     *   beyond $decimals are incremented by one unit; negative values are truncated toward zero.
     * - RoundingMode::Floor — toward negative infinity: positive values are truncated toward zero;
     *   negative values with a remainder beyond $decimals are decremented by one unit.
     *
     * @param int         $decimals Target decimal places (e.g. 2 for cent-level rounding).
     * @param RoundingMode $mode    Rounding direction.
     */
    public static function round(string $value, int $decimals, RoundingMode $mode): string
    {
        $truncated = bcadd($value, '0', $decimals);
        $unit = $decimals > 0 ? '0.' . str_repeat('0', $decimals - 1) . '1' : '1';
        $lookScale = $decimals + 4;

        return match ($mode) {
            RoundingMode::Ceil => (
                bccomp($value, '0', $lookScale) > 0 && bccomp($value, $truncated, $lookScale) > 0
                    ? bcadd($truncated, $unit, $decimals)
                    : $truncated
            ),
            RoundingMode::Floor => (
                bccomp($value, '0', $lookScale) < 0 && bccomp($value, $truncated, $lookScale) < 0
                    ? bcsub($truncated, $unit, $decimals)
                    : $truncated
            ),
        };
    }

    /**
     * Return true if the absolute difference between $a and $b is within $tolerance.
     *
     * Used to implement the ±0.0001 tolerance checks defined in §5.3.
     */
    public static function withinTolerance(string $a, string $b, string $tolerance): bool
    {
        $scale = SchemaVersion::monetaryScale() + 4;
        $diff = bcsub($a, $b, $scale);

        if (bccomp($diff, '0', $scale) < 0) {
            $diff = bcsub('0', $diff, $scale);
        }

        return bccomp($diff, $tolerance, $scale) <= 0;
    }
}
