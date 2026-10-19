<?php

use Amacraft\MarketingAutomation\Engine\FlowValidator;
use Amacraft\MarketingAutomation\Enums\FlowType;
use Amacraft\MarketingAutomation\Enums\MessageChannel;
use Amacraft\MarketingAutomation\Models\Flow;
use Amacraft\MarketingAutomation\Models\ProviderConnection;
use Amacraft\MarketingAutomation\Models\WhatsAppTemplate;
use Amacraft\MarketingAutomation\Repositories\ProviderConnectionRepository;

function makeFlowWithType(FlowType $type): Flow
{
    $flow = Flow::factory()->create(['type' => $type->value]);

    $flow->channels()->sync([core()->getAllChannels()->first()->id]);

    return $flow;
}

it('rejects a definition without any message step', function () {
    $flow = makeFlowWithType(FlowType::Marketing);

    $errors = app(FlowValidator::class)->validate($flow, [
        'entry' => 's1',
        'steps' => [
            's1' => ['type' => 'exit'],
        ],
    ]);

    expect($errors['steps'] ?? [])->not->toBeEmpty();
});

it('rejects a successor pointing at a missing step', function () {
    $flow = makeFlowWithType(FlowType::Marketing);

    $errors = app(FlowValidator::class)->validate($flow, [
        'entry' => 's1',
        'steps' => [
            's1' => ['type' => 'send_email', 'subject' => ['en' => 'Hi'], 'blocks' => ['en' => [['type' => 'text', 'text' => 'x']]], 'next' => 's9'],
            's2' => ['type' => 'exit'],
        ],
    ]);

    expect($errors['steps.s1'] ?? [])->not->toBeEmpty();
});

it('rejects a cycle without a wait step', function () {
    $flow = makeFlowWithType(FlowType::Marketing);

    $errors = app(FlowValidator::class)->validate($flow, [
        'entry' => 's1',
        'steps' => [
            's1' => ['type' => 'condition', 'rule' => ['all' => [['field' => 'order_count', 'op' => 'gte', 'value' => 1]]], 'yes' => 's2', 'no' => 's3'],
            's2' => ['type' => 'send_email', 'subject' => ['en' => 'Hi'], 'blocks' => ['en' => [['type' => 'text', 'text' => 'x']]], 'next' => 's1'],
            's3' => ['type' => 'exit'],
        ],
    ]);

    expect($errors['steps.s1'] ?? [])->not->toBeEmpty()
        ->and($errors['steps.s2'] ?? [])->not->toBeEmpty();
});

it('allows a cycle that contains a wait step', function () {
    $flow = makeFlowWithType(FlowType::Marketing);

    $errors = app(FlowValidator::class)->validate($flow, [
        'entry' => 's1',
        'steps' => [
            's1' => ['type' => 'condition', 'rule' => ['all' => [['field' => 'order_count', 'op' => 'gte', 'value' => 1]]], 'yes' => 's2', 'no' => 's4'],
            's2' => ['type' => 'wait', 'mode' => 'duration', 'minutes' => 60, 'next' => 's3'],
            's3' => ['type' => 'send_email', 'subject' => ['en' => 'Hi'], 'blocks' => ['en' => [['type' => 'text', 'text' => 'x']]], 'next' => 's1'],
            's4' => ['type' => 'exit'],
        ],
    ]);

    expect($errors)->toBeArray()
        ->and($errors['steps.s1'] ?? [])->toBeEmpty()
        ->and($errors['steps.s3'] ?? [])->toBeEmpty();
});

it('rejects a WhatsApp step whose template is not approved', function () {
    $flow = makeFlowWithType(FlowType::Marketing);

    $template = WhatsAppTemplate::factory()->create(['status' => 'paused']);

    $errors = app(FlowValidator::class)->validate($flow, [
        'entry' => 's1',
        'steps' => [
            's1' => ['type' => 'send_whatsapp', 'template_id' => $template->id, 'variables' => [], 'language' => ['en' => 'en_US'], 'next' => 's2'],
            's2' => ['type' => 'exit'],
        ],
    ]);

    expect($errors['steps.s1'] ?? [])->toContain(trans('marketing_automation::app.flows.validation.template-not-approved'));
});

it('rejects an SMS step without a connected default provider for a channel', function () {
    $flow = makeFlowWithType(FlowType::Marketing);

    $errors = app(FlowValidator::class)->validate($flow, [
        'entry' => 's1',
        'steps' => [
            's1' => ['type' => 'send_sms', 'body' => ['en' => 'Hi'], 'next' => 's2'],
            's2' => ['type' => 'exit'],
        ],
    ]);

    expect($errors['steps.s1'] ?? [])->not->toBeEmpty();
});

it('allows an SMS step once a connected provider is the channel default', function () {
    $flow = makeFlowWithType(FlowType::Marketing);

    $connection = ProviderConnection::factory()->sms()->connected()->create();

    app(ProviderConnectionRepository::class)->setDefault(
        core()->getAllChannels()->first()->id,
        MessageChannel::Sms,
        $connection->id,
    );

    $errors = app(FlowValidator::class)->validate($flow, [
        'entry' => 's1',
        'steps' => [
            's1' => ['type' => 'send_sms', 'body' => ['en' => 'Hi'], 'next' => 's2'],
            's2' => ['type' => 'exit'],
        ],
    ]);

    expect($errors['steps.s1'] ?? [])->toBeEmpty();
});

it('rejects transactional flows that use coupon or product grid blocks', function () {
    $flow = makeFlowWithType(FlowType::Transactional);

    $errors = app(FlowValidator::class)->validate($flow, [
        'entry' => 's1',
        'steps' => [
            's1' => [
                'type'    => 'send_email',
                'subject' => ['en' => 'Hi'],
                'blocks'  => ['en' => [
                    ['type' => 'text', 'text' => 'x'],
                    ['type' => 'coupon'],
                ]],
                'next' => 's2',
            ],
            's2' => ['type' => 'exit'],
        ],
    ]);

    expect($errors['steps.s1'] ?? [])->not->toBeEmpty();
});

it('rejects email steps carrying an unknown block type', function () {
    $flow = makeFlowWithType(FlowType::Marketing);

    $errors = app(FlowValidator::class)->validate($flow, [
        'entry' => 's1',
        'steps' => [
            's1' => [
                'type'    => 'send_email',
                'subject' => ['en' => 'Hi'],
                'blocks'  => ['en' => [
                    ['type' => 'text', 'text' => 'x'],
                    ['type' => 'typo_block'],
                ]],
                'next' => 's2',
            ],
            's2' => ['type' => 'exit'],
        ],
    ]);

    expect($errors['steps.s1'] ?? [])->not->toBeEmpty()
        ->and($errors['steps.s1'])->toContain(trans('marketing_automation::app.flows.validation.unknown-block', ['block' => 'typo_block']));
});

it('accepts a fully valid graph', function () {
    $flow = makeFlowWithType(FlowType::Marketing);

    $connection = ProviderConnection::factory()->sms()->connected()->create();

    app(ProviderConnectionRepository::class)->setDefault(
        core()->getAllChannels()->first()->id,
        MessageChannel::Sms,
        $connection->id,
    );

    $whatsappConnection = ProviderConnection::factory()->whatsapp()->connected()->create();

    app(ProviderConnectionRepository::class)->setDefault(
        core()->getAllChannels()->first()->id,
        MessageChannel::Whatsapp,
        $whatsappConnection->id,
    );

    $template = WhatsAppTemplate::factory()->create(['status' => 'approved']);

    $errors = app(FlowValidator::class)->validate($flow, [
        'entry' => 's1',
        'steps' => [
            's1' => ['type' => 'send_email', 'subject' => ['en' => 'Hi'], 'blocks' => ['en' => [['type' => 'text', 'text' => 'x']]], 'next' => 's2'],
            's2' => ['type' => 'wait', 'mode' => 'duration', 'minutes' => 60, 'next' => 's3'],
            's3' => ['type' => 'condition', 'rule' => ['all' => [['field' => 'order_count', 'op' => 'gte', 'value' => 1]]], 'yes' => 's4', 'no' => 's5'],
            's4' => ['type' => 'send_whatsapp', 'template_id' => $template->id, 'variables' => [], 'language' => ['en' => 'en_US'], 'next' => 's6'],
            's5' => ['type' => 'send_sms', 'body' => ['en' => 'Hi'], 'next' => 's6'],
            's6' => ['type' => 'exit'],
        ],
    ]);

    expect($errors)->toBe([]);
});

it('rejects an unknown step type', function () {
    $flow = makeFlowWithType(FlowType::Marketing);

    $errors = app(FlowValidator::class)->validate($flow, [
        'entry' => 's1',
        'steps' => [
            's1' => ['type' => 'send_fax', 'next' => 's2'],
            's2' => ['type' => 'exit'],
        ],
    ]);

    expect($errors['steps.s1'] ?? [])->toContain(trans('marketing_automation::app.flows.validation.unknown-step'));
});

it('rejects a wait step without a next step', function () {
    $flow = makeFlowWithType(FlowType::Marketing);

    $errors = app(FlowValidator::class)->validate($flow, [
        'entry' => 's1',
        'steps' => [
            's1' => ['type' => 'send_email', 'subject' => ['en' => 'Hi'], 'blocks' => ['en' => [['type' => 'text', 'text' => 'x']]], 'next' => 's2'],
            's2' => ['type' => 'wait', 'mode' => 'duration', 'minutes' => 60],
        ],
    ]);

    expect($errors['steps.s2'] ?? [])->toContain(trans('marketing_automation::app.flows.validation.wait-needs-next'));
});

it('rejects wait steps with missing scheduling parameters', function () {
    $flow = makeFlowWithType(FlowType::Marketing);

    $errors = app(FlowValidator::class)->validate($flow, [
        'entry' => 's1',
        'steps' => [
            's1' => ['type' => 'wait', 'mode' => 'duration', 'next' => 's2'],
            's2' => ['type' => 'exit'],
        ],
    ]);

    expect($errors['steps.s1'] ?? [])->toContain(trans('marketing_automation::app.flows.validation.bad-wait-params'));
});

it('rejects condition rules with an unknown operator or consent channel', function () {
    $flow = makeFlowWithType(FlowType::Marketing);

    $errors = app(FlowValidator::class)->validate($flow, [
        'entry' => 's1',
        'steps' => [
            's1' => ['type' => 'condition', 'rule' => ['all' => [['field' => 'order_count', 'op' => 'matches', 'value' => 1]]], 'yes' => 's2', 'no' => 's3'],
            's2' => ['type' => 'send_email', 'subject' => ['en' => 'Hi'], 'blocks' => ['en' => [['type' => 'text', 'text' => 'x']]], 'next' => 's4'],
            's3' => ['type' => 'send_email', 'subject' => ['en' => 'Hi'], 'blocks' => ['en' => [['type' => 'text', 'text' => 'x']]], 'next' => 's4'],
            's4' => ['type' => 'exit'],
        ],
    ]);

    expect($errors['steps.s1'] ?? [])->toContain(trans('marketing_automation::app.flows.validation.bad-rule'));

    $errors = app(FlowValidator::class)->validate($flow, [
        'entry' => 's1',
        'steps' => [
            's1' => ['type' => 'condition', 'rule' => ['all' => [['field' => 'has_consent.pigeon', 'op' => 'is_true']]]],
            's2' => ['type' => 'send_email', 'subject' => ['en' => 'Hi'], 'blocks' => ['en' => [['type' => 'text', 'text' => 'x']]], 'next' => 's1'],
        ],
    ]);

    expect($errors['steps.s1'] ?? [])->toContain(trans('marketing_automation::app.flows.validation.invalid-consent-channel', ['channel' => 'pigeon']));
});

it('rejects malformed step keys', function () {
    $flow = makeFlowWithType(FlowType::Marketing);

    $errors = app(FlowValidator::class)->validate($flow, [
        'entry' => 'step-one',
        'steps' => [
            'step-one' => ['type' => 'send_email', 'subject' => ['en' => 'Hi'], 'blocks' => ['en' => [['type' => 'text', 'text' => 'x']]], 'next' => 'end'],
            'end'      => ['type' => 'exit'],
        ],
    ]);

    expect($errors['steps'] ?? [])->toContain(trans('marketing_automation::app.flows.validation.malformed-keys'));
});

it('rejects an unknown fallback channel', function () {
    $flow = makeFlowWithType(FlowType::Marketing);

    $errors = app(FlowValidator::class)->validate($flow, [
        'entry' => 's1',
        'steps' => [
            's1' => ['type' => 'send_email', 'subject' => ['en' => 'Hi'], 'blocks' => ['en' => [['type' => 'text', 'text' => 'x']]], 'fallback_channel' => 'fax', 'fallback_step' => ['body' => ['en' => 'x']], 'next' => 's2'],
            's2' => ['type' => 'exit'],
        ],
    ]);

    expect($errors['steps.s1'] ?? [])->toContain(trans('marketing_automation::app.flows.validation.invalid-fallback-channel'));
});

it('rejects an SMS fallback without a connected provider', function () {
    $flow = makeFlowWithType(FlowType::Marketing);

    $errors = app(FlowValidator::class)->validate($flow, [
        'entry' => 's1',
        'steps' => [
            's1' => [
                'type'             => 'send_email',
                'subject'          => ['en' => 'Hi'],
                'blocks'           => ['en' => [['type' => 'text', 'text' => 'x']]],
                'fallback_channel' => 'sms',
                'fallback_step'    => ['body' => ['en' => 'x']],
                'next'             => 's2',
            ],
            's2' => ['type' => 'exit'],
        ],
    ]);

    expect($errors['steps.s1'] ?? [])->toContain(trans('marketing_automation::app.flows.validation.fallback-no-provider', ['channel' => 'sms']));
});

it('allows an email fallback without a provider', function () {
    $flow = makeFlowWithType(FlowType::Marketing);

    $connection = ProviderConnection::factory()->sms()->connected()->create();

    app(ProviderConnectionRepository::class)->setDefault(
        core()->getAllChannels()->first()->id,
        MessageChannel::Sms,
        $connection->id,
    );

    $errors = app(FlowValidator::class)->validate($flow, [
        'entry' => 's1',
        'steps' => [
            's1' => [
                'type'             => 'send_sms',
                'body'             => ['en' => 'Hi'],
                'fallback_channel' => 'email',
                'fallback_step'    => ['subject' => ['en' => 'F'], 'blocks' => ['en' => [['type' => 'text', 'text' => 'x']]]],
                'next'             => 's2',
            ],
            's2' => ['type' => 'exit'],
        ],
    ]);

    expect($errors['steps.s1'] ?? [])->toBeEmpty();
});

it('rejects a cycle that starts after a wait step', function () {
    $flow = makeFlowWithType(FlowType::Marketing);

    $errors = app(FlowValidator::class)->validate($flow, [
        'entry' => 's1',
        'steps' => [
            's1' => ['type' => 'wait', 'mode' => 'duration', 'minutes' => 60, 'next' => 's2'],
            's2' => ['type' => 'send_email', 'subject' => ['en' => 'Hi'], 'blocks' => ['en' => [['type' => 'text', 'text' => 'x']]], 'next' => 's3'],
            's3' => ['type' => 'send_email', 'subject' => ['en' => 'Hi'], 'blocks' => ['en' => [['type' => 'text', 'text' => 'x']]], 'next' => 's2'],
        ],
    ]);

    expect($errors['steps.s2'] ?? [])->toContain(trans('marketing_automation::app.flows.validation.cycle'))
        ->and($errors['steps.s3'] ?? [])->toContain(trans('marketing_automation::app.flows.validation.cycle'));
});

it('accepts nested condition groups', function () {
    $flow = makeFlowWithType(FlowType::Marketing);

    $errors = app(FlowValidator::class)->validate($flow, [
        'entry' => 's1',
        'steps' => [
            's1' => [
                'type' => 'condition',
                'rule' => ['any' => [
                    ['all' => [
                        ['field' => 'order_count', 'op' => 'gte', 'value' => 1],
                        ['field' => 'lifetime_spend', 'op' => 'gt', 'value' => 100],
                    ]],
                    ['field' => 'locale', 'op' => 'eq', 'value' => 'ar'],
                ]],
                'yes' => 's2',
                'no'  => 's3',
            ],
            's2' => ['type' => 'send_email', 'subject' => ['en' => 'Hi'], 'blocks' => ['en' => [['type' => 'text', 'text' => 'x']]], 'next' => 's3'],
            's3' => ['type' => 'exit'],
        ],
    ]);

    expect($errors['steps.s1'] ?? [])->toBeEmpty();
});

it('rejects an empty or malformed nested condition group', function () {
    $flow = makeFlowWithType(FlowType::Marketing);

    $validate = fn (array $rule) => app(FlowValidator::class)->validate($flow, [
        'entry' => 's1',
        'steps' => [
            's1' => ['type' => 'condition', 'rule' => $rule, 'yes' => 's2', 'no' => 's2'],
            's2' => ['type' => 'send_email', 'subject' => ['en' => 'Hi'], 'blocks' => ['en' => [['type' => 'text', 'text' => 'x']]]],
        ],
    ]);

    expect($validate(['any' => [['all' => []]]])['steps.s1'] ?? [])->toContain(trans('marketing_automation::app.flows.validation.bad-rule'))
        ->and($validate(['any' => [['all' => [['field' => 'order_count', 'op' => 'bogus']]]]])['steps.s1'] ?? [])->toContain(trans('marketing_automation::app.flows.validation.bad-rule'));
});

it('rejects a waitless cycle hidden behind a branch that also has a wait', function () {
    $flow = makeFlowWithType(FlowType::Marketing);

    $errors = app(FlowValidator::class)->validate($flow, [
        'entry' => 's1',
        'steps' => [
            's1' => ['type' => 'condition', 'rule' => ['all' => [['field' => 'order_count', 'op' => 'gte', 'value' => 1]]], 'yes' => 's2', 'no' => 's3'],
            's2' => ['type' => 'wait', 'mode' => 'duration', 'minutes' => 60, 'next' => 's3'],
            's3' => ['type' => 'send_email', 'subject' => ['en' => 'Hi'], 'blocks' => ['en' => [['type' => 'text', 'text' => 'x']]], 'next' => 's1'],
        ],
    ]);

    expect($errors['steps.s1'] ?? [])->toContain(trans('marketing_automation::app.flows.validation.cycle'))
        ->and($errors['steps.s3'] ?? [])->toContain(trans('marketing_automation::app.flows.validation.cycle'))
        ->and($errors['steps.s2'] ?? [])->not->toContain(trans('marketing_automation::app.flows.validation.cycle'));
});

it('rejects an until-time wait with an impossible clock time', function (string $time) {
    $flow = makeFlowWithType(FlowType::Marketing);

    $errors = app(FlowValidator::class)->validate($flow, [
        'entry' => 's1',
        'steps' => [
            's1' => ['type' => 'wait', 'mode' => 'until_time', 'time' => $time, 'next' => 's2'],
            's2' => ['type' => 'send_email', 'subject' => ['en' => 'Hi'], 'blocks' => ['en' => [['type' => 'text', 'text' => 'x']]]],
        ],
    ]);

    expect($errors['steps.s1'] ?? [])->toContain(trans('marketing_automation::app.flows.validation.bad-wait-params'));
})->with(['25:00', '09:60', '9:30', '']);

it('accepts an until-time wait with a valid clock time', function () {
    $flow = makeFlowWithType(FlowType::Marketing);

    $errors = app(FlowValidator::class)->validate($flow, [
        'entry' => 's1',
        'steps' => [
            's1' => ['type' => 'wait', 'mode' => 'until_time', 'time' => '23:59', 'next' => 's2'],
            's2' => ['type' => 'send_email', 'subject' => ['en' => 'Hi'], 'blocks' => ['en' => [['type' => 'text', 'text' => 'x']]]],
        ],
    ]);

    expect($errors['steps.s1'] ?? [])->toBeEmpty();
});

it('rejects an email fallback without a subject or blocks', function (array $fallbackStep) {
    $flow = makeFlowWithType(FlowType::Marketing);

    $connection = ProviderConnection::factory()->sms()->connected()->create();

    app(ProviderConnectionRepository::class)->setDefault(core()->getAllChannels()->first()->id, MessageChannel::Sms, $connection->id);

    $errors = app(FlowValidator::class)->validate($flow, [
        'entry' => 's1',
        'steps' => [
            's1' => ['type' => 'send_sms', 'body' => ['en' => 'Hi'], 'fallback_channel' => 'email', 'fallback_step' => $fallbackStep, 'next' => 's2'],
            's2' => ['type' => 'exit'],
        ],
    ]);

    expect($errors['steps.s1'] ?? [])->toContain(trans('marketing_automation::app.flows.validation.fallback-content-missing'));
})->with([
    'no subject' => [['subject' => ['en' => ''], 'blocks' => ['en' => [['type' => 'text', 'text' => 'x']]]]],
    'no blocks'  => [['subject' => ['en' => 'F'], 'blocks' => ['en' => []]]],
    'empty'      => [[]],
]);

it('rejects an SMS fallback without a body', function () {
    $flow = makeFlowWithType(FlowType::Marketing);

    $connection = ProviderConnection::factory()->sms()->connected()->create();

    app(ProviderConnectionRepository::class)->setDefault(core()->getAllChannels()->first()->id, MessageChannel::Sms, $connection->id);

    $errors = app(FlowValidator::class)->validate($flow, [
        'entry' => 's1',
        'steps' => [
            's1' => ['type' => 'send_email', 'subject' => ['en' => 'Hi'], 'blocks' => ['en' => [['type' => 'text', 'text' => 'x']]], 'fallback_channel' => 'sms', 'fallback_step' => ['body' => ['en' => '  ']], 'next' => 's2'],
            's2' => ['type' => 'exit'],
        ],
    ]);

    expect($errors['steps.s1'] ?? [])->toContain(trans('marketing_automation::app.flows.validation.fallback-content-missing'))
        ->and($errors['steps.s1'] ?? [])->not->toContain(trans('marketing_automation::app.flows.validation.fallback-no-provider', ['channel' => 'sms']));
});

it('requires an approved template for a WhatsApp fallback', function () {
    $flow = makeFlowWithType(FlowType::Marketing);

    $connection = ProviderConnection::factory()->whatsapp()->connected()->create();

    app(ProviderConnectionRepository::class)->setDefault(core()->getAllChannels()->first()->id, MessageChannel::Whatsapp, $connection->id);

    $validate = fn (array $fallbackStep) => app(FlowValidator::class)->validate($flow, [
        'entry' => 's1',
        'steps' => [
            's1' => ['type' => 'send_email', 'subject' => ['en' => 'Hi'], 'blocks' => ['en' => [['type' => 'text', 'text' => 'x']]], 'fallback_channel' => 'whatsapp', 'fallback_step' => $fallbackStep, 'next' => 's2'],
            's2' => ['type' => 'exit'],
        ],
    ]);

    $pending = WhatsAppTemplate::factory()->create(['provider_connection_id' => $connection->id, 'name' => 'pending_offer', 'status' => 'pending']);
    $approved = WhatsAppTemplate::factory()->create(['provider_connection_id' => $connection->id, 'status' => 'approved']);

    $notApproved = trans('marketing_automation::app.flows.validation.fallback-template-not-approved');

    expect($validate([])['steps.s1'] ?? [])->toContain($notApproved)
        ->and($validate(['template_id' => $pending->id])['steps.s1'] ?? [])->toContain($notApproved)
        ->and($validate(['template_id' => $approved->id])['steps.s1'] ?? [])->toBeEmpty();
});

it('treats an empty-string fallback channel as no fallback', function () {
    $flow = makeFlowWithType(FlowType::Marketing);

    $errors = app(FlowValidator::class)->validate($flow, [
        'entry' => 's1',
        'steps' => [
            's1' => ['type' => 'send_email', 'subject' => ['en' => 'Hi'], 'blocks' => ['en' => [['type' => 'text', 'text' => 'x']]], 'fallback_channel' => '', 'next' => 's2'],
            's2' => ['type' => 'exit'],
        ],
    ]);

    expect($errors['steps.s1'] ?? [])->toBeEmpty();
});
