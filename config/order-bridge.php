<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Arithmetic tolerance
    |--------------------------------------------------------------------------
    |
    | Maximum allowed absolute difference when comparing computed values against
    | submitted values (§5.3). Expressed as a decimal string.
    |
    */

    'tolerance' => '0.0001',

    /*
    |--------------------------------------------------------------------------
    | Monetary scale
    |--------------------------------------------------------------------------
    |
    | Number of decimal places used for all monetary output fields (unit_price,
    | line_ex_vat, grand_total, etc.). The spec mandates 4 decimal places.
    |
    */

    'monetary_scale' => 4,

    /*
    |--------------------------------------------------------------------------
    | Discount scale
    |--------------------------------------------------------------------------
    |
    | Number of decimal places used for discount percentage strings (e.g.
    | "10.000000"). The spec mandates 6 decimal places.
    |
    */

    'discount_scale' => 6,

    /*
    |--------------------------------------------------------------------------
    | VAT rounding scale
    |--------------------------------------------------------------------------
    |
    | Number of decimal places to which VAT amounts are rounded before output.
    | 2 = cent-level rounding, matching most accounting systems.
    | Change to 4 to disable rounding (VAT will be expressed at full monetary
    | precision without any additional rounding step).
    |
    */

    'vat_rounding_scale' => 2,

    /*
    |--------------------------------------------------------------------------
    | VAT rounding mode
    |--------------------------------------------------------------------------
    |
    | Direction applied when rounding VAT amounts to vat_rounding_scale places.
    |
    |   'ceil'  — round toward positive infinity (always round up for positive
    |             VAT amounts). Recommended for most retail / ePOS contexts.
    |
    |   'floor' — round toward negative infinity (always round down for positive
    |             VAT amounts).
    |
    */

    'vat_rounding_mode' => 'ceil',

];
