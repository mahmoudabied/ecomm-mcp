<?php

use Amacraft\MarketingAutomation\Services\RecipientMasker;

it('masks recipients without leaking the middle', function (?string $recipient, ?string $masked) {
    expect(app(RecipientMasker::class)->mask($recipient))->toBe($masked);
})->with([
    'email'            => ['sara@example.com', 's***@example.com'],
    'long local part'  => ['stephanie@example.com', 's***@example.com'],
    'saudi phone'      => ['+966501234567', '+96650****567'],
    'short phone'      => ['0501234567', '050****567'],
    'very short phone' => ['12345', '**345'],
    'empty'            => ['', null],
    'null'             => [null, null],
]);
