<?php

use Webkul\MarketingAutomation\Services\PhoneNormalizer;

dataset('phone_numbers', [
    ['0501234567', 'SA', '+966501234567'],
    ['+20 100 123 4567', null, '+201001234567'],
    ['00971501234567', 'AE', '+971501234567'],
    ['123', 'SA', null],
    ['', null, null],
]);

it('normalizes phone numbers to E.164', function (string $raw, ?string $countryCode, ?string $expected) {
    expect(app(PhoneNormalizer::class)->normalize($raw, $countryCode))->toBe($expected);
})->with('phone_numbers');

it('treats a missing number as unusable', function () {
    expect(app(PhoneNormalizer::class)->normalize(null, 'SA'))->toBeNull();
});
