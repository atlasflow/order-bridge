<?php

declare(strict_types=1);

namespace Atlasflow\OrderBridge;

use Atlasflow\OrderBridge\Support\RoundingMode;

/**
 * Package-wide schema constants and runtime configuration.
 *
 * The class constants are compile-time defaults used as PHP parameter defaults and
 * for non-Laravel usage. When running inside Laravel the service provider calls
 * {@see self::configure()} so that the published config file takes precedence at runtime.
 *
 * Prefer the static accessor methods (e.g. {@see self::monetaryScale()}) over the raw
 * constants in application code so that configuration overrides are respected.
 */
final class SchemaVersion
{
    // -------------------------------------------------------------------------
    // Compile-time constants — do not reference these directly in internal code;
    // use the accessor methods below so that runtime config is honoured.
    // -------------------------------------------------------------------------

    /** Current schema version as declared in the spec title and §2.1. */
    public const string SCHEMA_VERSION = '1.4.2';

    /** Arithmetic tolerance used in §5.3 validation rules. */
    public const string TOLERANCE = '0.0001';

    /** bcmath scale for monetary values (prices, totals, VAT). */
    public const int MONETARY_SCALE = 4;

    /** bcmath scale for discount percentage strings. */
    public const int DISCOUNT_SCALE = 6;

    /** Decimal places to which VAT amounts are rounded. */
    public const int VAT_ROUNDING_SCALE = 2;

    // -------------------------------------------------------------------------
    // Runtime-configurable state — populated by the service provider in Laravel,
    // defaulting to the constants above for framework-agnostic usage.
    // -------------------------------------------------------------------------

    private static string $tolerance = self::TOLERANCE;

    private static int $monetaryScale = self::MONETARY_SCALE;

    private static int $discountScale = self::DISCOUNT_SCALE;

    private static int $vatRoundingScale = self::VAT_ROUNDING_SCALE;

    private static RoundingMode $vatRoundingMode = RoundingMode::Ceil;

    // -------------------------------------------------------------------------
    // Configuration entry-point
    // -------------------------------------------------------------------------

    /**
     * Override runtime configuration values.
     *
     * Typically called by {@see \Atlasflow\OrderBridge\Laravel\OrderBridgeServiceProvider}
     * after merging the published config file. Safe to call multiple times; each call
     * merges only the supplied keys.
     *
     * @param array{
     *     tolerance?: string,
     *     monetary_scale?: int,
     *     discount_scale?: int,
     *     vat_rounding_scale?: int,
     *     vat_rounding_mode?: string,
     * } $config
     */
    public static function configure(array $config): void
    {
        if (isset($config['tolerance'])) {
            self::$tolerance = (string) $config['tolerance'];
        }

        if (isset($config['monetary_scale'])) {
            self::$monetaryScale = (int) $config['monetary_scale'];
        }

        if (isset($config['discount_scale'])) {
            self::$discountScale = (int) $config['discount_scale'];
        }

        if (isset($config['vat_rounding_scale'])) {
            self::$vatRoundingScale = (int) $config['vat_rounding_scale'];
        }

        if (isset($config['vat_rounding_mode'])) {
            self::$vatRoundingMode = RoundingMode::from((string) $config['vat_rounding_mode']);
        }
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public static function tolerance(): string
    {
        return self::$tolerance;
    }

    public static function monetaryScale(): int
    {
        return self::$monetaryScale;
    }

    public static function discountScale(): int
    {
        return self::$discountScale;
    }

    public static function vatRoundingScale(): int
    {
        return self::$vatRoundingScale;
    }

    public static function vatRoundingMode(): RoundingMode
    {
        return self::$vatRoundingMode;
    }
}
