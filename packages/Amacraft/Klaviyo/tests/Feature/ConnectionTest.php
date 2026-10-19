<?php

use Illuminate\Support\Facades\Http;
use Amacraft\Klaviyo\Models\Connection;
use Amacraft\Klaviyo\Tests\KlaviyoFixtures;

beforeEach(function () {
    $this->loginAsAdmin();
});

it('connects with a valid api key and stores the connection', function () {
    Http::fake([
        'a.klaviyo.com/api/accounts' => Http::response(KlaviyoFixtures::body('accounts_ok'), 200),
    ]);

    $channelId = core()->getAllChannels()->first()->id;

    $this->postJson(route('admin.marketing.klaviyo.connect'), [
        'channel_id' => $channelId,
        'api_key'    => 'pk_test_valid_key',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'connected');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://a.klaviyo.com/api/accounts'
            && $request->hasHeader('Authorization', 'Klaviyo-API-Key pk_test_valid_key')
            && $request->hasHeader('revision', '2024-10-15');
    });

    expect(Connection::query()->where('channel_id', $channelId)->first()->status)->toBe('connected');
});

it('rejects a bad api key with 422 and a message', function () {
    Http::fake([
        'a.klaviyo.com/api/accounts' => Http::response(KlaviyoFixtures::body('accounts_401'), 401),
    ]);

    $this->postJson(route('admin.marketing.klaviyo.connect'), [
        'channel_id' => core()->getAllChannels()->first()->id,
        'api_key'    => 'pk_test_bad_key',
    ])
        ->assertStatus(422)
        ->assertJsonMissing(['api_key' => null]);

    expect(Connection::query()->count())->toBe(0);
});

it('never echoes the api key in the index response', function () {
    $connection = Connection::factory()->create([
        'channel_id' => core()->getAllChannels()->first()->id,
        'api_key'    => 'pk_secret_super_key',
    ]);

    $this->getJson(route('admin.marketing.klaviyo.index'))
        ->assertOk()
        ->assertDontSee('pk_secret_super_key')
        ->assertDontSee('super_key');

    expect($connection->fresh()->status)->toBe('connected');
});
