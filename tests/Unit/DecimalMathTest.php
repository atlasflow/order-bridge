<?php

declare(strict_types=1);

use Atlasflow\OrderBridge\Support\DecimalMath;

describe('DecimalMath::multiply', function () {
    it('multiplies two positive decimals', function () {
        expect(DecimalMath::multiply('2', '42.6100', 4))->toBe('85.2200');
    });

    it('handles zero multiplicand', function () {
        expect(DecimalMath::multiply('0', '99.9999', 4))->toBe('0.0000');
    });

    it('handles fractional quantity', function () {
        expect(DecimalMath::multiply('0.5', '10.0000', 4))->toBe('5.0000');
    });

    it('uses the given scale', function () {
        expect(DecimalMath::multiply('1', '3', 6))->toBe('3.000000');
    });
});

describe('DecimalMath::add', function () {
    it('adds two decimals at monetary scale', function () {
        expect(DecimalMath::add('76.6980', '34.9900'))->toBe('111.6880');
    });

    it('adds zero without changing value', function () {
        expect(DecimalMath::add('42.0000', '0'))->toBe('42.0000');
    });

    it('respects custom scale', function () {
        expect(DecimalMath::add('1.1', '2.2', 6))->toBe('3.300000');
    });
});

describe('DecimalMath::subtract', function () {
    it('subtracts the discount factor', function () {
        expect(DecimalMath::subtract('100', '10.000000', 6))->toBe('90.000000');
    });

    it('returns zero when values are equal', function () {
        expect(DecimalMath::subtract('5.0000', '5.0000'))->toBe('0.0000');
    });
});

describe('DecimalMath::divide', function () {
    it('divides to given scale', function () {
        expect(DecimalMath::divide('90', '100', 6))->toBe('0.900000');
    });

    it('computes percentage factor', function () {
        expect(DecimalMath::divide('21', '100', 6))->toBe('0.210000');
    });
});

describe('DecimalMath::format', function () {
    it('pads to 4 decimal places', function () {
        expect(DecimalMath::format('76.698', 4))->toBe('76.6980');
    });

    it('pads integer to 4 decimal places', function () {
        expect(DecimalMath::format('0', 4))->toBe('0.0000');
    });

    it('truncates to 4 decimal places', function () {
        // bcmath truncates, not rounds
        expect(DecimalMath::format('1.23456789', 4))->toBe('1.2345');
    });

    it('formats to 6 decimal places for discounts', function () {
        expect(DecimalMath::format('10', 6))->toBe('10.000000');
    });

    it('handles exact 4-decimal input unchanged', function () {
        expect(DecimalMath::format('7.3479', 4))->toBe('7.3479');
    });
});

describe('DecimalMath::withinTolerance', function () {
    it('returns true when values are identical', function () {
        expect(DecimalMath::withinTolerance('76.6980', '76.6980', '0.0001'))->toBeTrue();
    });

    it('returns true when difference equals the tolerance', function () {
        expect(DecimalMath::withinTolerance('76.6981', '76.6980', '0.0001'))->toBeTrue();
    });

    it('returns false when difference exceeds tolerance', function () {
        expect(DecimalMath::withinTolerance('76.6982', '76.6980', '0.0001'))->toBeFalse();
    });

    it('handles zero VAT comparison', function () {
        expect(DecimalMath::withinTolerance('0.0000', '0', '0.0001'))->toBeTrue();
    });

    it('is symmetric (a,b) equals (b,a)', function () {
        expect(DecimalMath::withinTolerance('76.6980', '76.6981', '0.0001'))->toBeTrue();
    });
});
