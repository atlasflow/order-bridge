<?php

declare(strict_types=1);

use Atlasflow\OrderBridge\Dto\AddressDto;

it('constructs without an addressId, defaulting it to null', function () {
    $address = new AddressDto(
        line1: '12 High Street',
        line2: null,
        line3: null,
        city: 'London',
        postcode: 'SW1A 1AA',
        region: null,
        country: 'GB',
    );

    expect($address->addressId)->toBeNull();
    expect($address->line1)->toBe('12 High Street');
});

it('accepts an explicit addressId', function () {
    $address = new AddressDto(
        line1: '12 High Street',
        line2: null,
        line3: null,
        city: 'London',
        postcode: 'SW1A 1AA',
        region: null,
        country: 'GB',
        addressId: 7,
    );

    expect($address->addressId)->toBe(7);
});
