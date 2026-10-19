<?php

namespace Amacraft\MarketingAutomation\Engine;

use Webkul\CartRule\Models\CartRule;
use Amacraft\MarketingAutomation\Enums\FlowType;
use Amacraft\MarketingAutomation\Enums\MessageChannel;
use Amacraft\MarketingAutomation\Enums\StepType;
use Amacraft\MarketingAutomation\Models\Flow;
use Amacraft\MarketingAutomation\Models\WhatsAppTemplate;
use Amacraft\MarketingAutomation\Services\ProviderManager;

class FlowValidator
{
    /**
     * The comparison operators accepted inside condition rules.
     *
     * @var array<int, string>
     */
    protected const RULE_OPERATORS = [
        'eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'in', 'not_in', 'is_true', 'is_false',
    ];

    /**
     * How deep condition groups may nest.
     */
    protected const MAX_RULE_DEPTH = 5;

    /**
     * The block types the email renderer has a Blade view for; anything else
     * would be silently dropped at send time.
     *
     * @var array<int, string>
     */
    protected const EMAIL_BLOCK_TYPES = [
        'header', 'text', 'image', 'button', 'product_grid', 'cart_items', 'order_summary', 'coupon', 'footer',
    ];

    /**
     * Create a new validator instance.
     */
    public function __construct(
        protected ProviderManager $providerManager,
    ) {}

    /**
     * Validate a draft definition against the activation rules.
     *
     * @return array<string, array<int, string>> errors keyed by "steps.<key>", "steps" or "trigger"
     */
    public function validate(Flow $flow, array $definition): array
    {
        if (! isset($definition['entry']) || ! isset($definition['steps']) || ! is_array($definition['steps']) || $definition['steps'] === []) {
            return ['steps' => [trans('marketing_automation::app.flows.validation.no-send-step')]];
        }

        try {
            $graph = new FlowDefinition($definition);
        } catch (\InvalidArgumentException) {
            return ['steps' => [trans('marketing_automation::app.flows.validation.no-send-step')]];
        }

        $errors = $this->validateStructure($graph);

        if (! $graph->keysAreWellFormed()) {
            $errors['steps'][] = trans('marketing_automation::app.flows.validation.malformed-keys');
        }

        foreach ($graph->keys() as $key) {
            $step = $graph->step($key);

            $stepErrors = match ($step['type'] ?? '') {
                StepType::Wait->value          => $this->validateWaitStep($step),
                StepType::Condition->value     => $this->validateConditionStep($step),
                StepType::SendEmail->value     => array_merge($this->validateEmailStep($flow, $step), $this->validateFallback($flow, $step)),
                StepType::SendSms->value       => array_merge($this->validateSmsStep($flow), $this->validateFallback($flow, $step)),
                StepType::SendWhatsapp->value  => array_merge($this->validateWhatsappStep($flow, $step), $this->validateFallback($flow, $step)),
                StepType::Exit->value          => [],
                default                        => [trans('marketing_automation::app.flows.validation.unknown-step')],
            };

            foreach ($stepErrors as $message) {
                $errors["steps.{$key}"][] = $message;
            }
        }

        return $errors;
    }

    /**
     * Entry existence, dangling successors, unknown types, wait tails and waitless cycles.
     *
     * @return array<string, array<int, string>>
     */
    protected function validateStructure(FlowDefinition $graph): array
    {
        $errors = [];

        if (! in_array($graph->entry(), $graph->keys(), true)) {
            $errors['trigger'][] = trans('marketing_automation::app.flows.validation.missing-entry');
        }

        $hasSendStep = false;

        foreach ($graph->keys() as $key) {
            if ($graph->isSendStep($key)) {
                $hasSendStep = true;
            }

            $step = $graph->step($key);

            if (($step['type'] ?? '') === StepType::Wait->value
                && ($step['next'] ?? null) === null
            ) {
                $errors["steps.{$key}"][] = trans('marketing_automation::app.flows.validation.wait-needs-next');
            }

            foreach ($graph->successorsOf($key) as $successor) {
                if (! in_array($successor, $graph->keys(), true)) {
                    $errors["steps.{$key}"][] = trans('marketing_automation::app.flows.validation.missing-step', ['key' => $successor]);
                }
            }
        }

        if (! $hasSendStep) {
            $errors['steps'][] = trans('marketing_automation::app.flows.validation.no-send-step');
        }

        foreach ($this->findWaitlessCycles($graph) as $key) {
            $errors["steps.{$key}"][] = trans('marketing_automation::app.flows.validation.cycle');
        }

        return $errors;
    }

    /**
     * Keys that sit on a cycle containing no wait step.
     *
     * Every edge leaving a wait step is dropped, so any cycle left in the
     * graph is one a contact could loop through without waiting. Tarjan's
     * strongly connected components then mark every step on such a cycle.
     *
     * @return array<int, string>
     */
    protected function findWaitlessCycles(FlowDefinition $graph): array
    {
        $keys = $graph->keys();

        $edges = [];

        foreach ($keys as $key) {
            $edges[$key] = ($graph->step($key)['type'] ?? '') === StepType::Wait->value
                ? []
                : array_values(array_filter(
                    $graph->successorsOf($key),
                    fn ($successor) => in_array($successor, $keys, true),
                ));
        }

        $index = 0;
        $indexes = [];
        $lowLinks = [];
        $stack = [];
        $onStack = [];
        $onCycle = [];

        $connect = function (string $key) use (&$connect, &$index, &$indexes, &$lowLinks, &$stack, &$onStack, &$onCycle, $edges): void {
            $indexes[$key] = $lowLinks[$key] = $index++;
            $stack[] = $key;
            $onStack[$key] = true;

            foreach ($edges[$key] as $successor) {
                if (! isset($indexes[$successor])) {
                    $connect($successor);

                    $lowLinks[$key] = min($lowLinks[$key], $lowLinks[$successor]);
                } elseif ($onStack[$successor] ?? false) {
                    $lowLinks[$key] = min($lowLinks[$key], $indexes[$successor]);
                }
            }

            if ($lowLinks[$key] !== $indexes[$key]) {
                return;
            }

            $component = [];

            do {
                $member = array_pop($stack);
                $onStack[$member] = false;
                $component[] = $member;
            } while ($member !== $key);

            if (count($component) > 1 || in_array($key, $edges[$key], true)) {
                foreach ($component as $member) {
                    $onCycle[$member] = true;
                }
            }
        };

        foreach ($keys as $key) {
            if (! isset($indexes[$key])) {
                $connect($key);
            }
        }

        return array_values(array_filter($keys, fn ($key) => isset($onCycle[$key])));
    }

    /**
     * A wait step must carry usable scheduling parameters.
     *
     * @param  array<string, mixed>  $step
     * @return array<int, string>
     */
    protected function validateWaitStep(array $step): array
    {
        $mode = $step['mode'] ?? 'duration';

        if ($mode === 'until_time') {
            return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) ($step['time'] ?? ''))
                ? []
                : [trans('marketing_automation::app.flows.validation.bad-wait-params')];
        }

        if ($mode === 'until_weekday') {
            $weekdays = array_map('intval', (array) ($step['weekdays'] ?? []));

            $valid = $weekdays !== []
                && $weekdays === array_values(array_unique(array_filter($weekdays, fn ($d) => $d >= 1 && $d <= 7)));

            return $valid
                ? []
                : [trans('marketing_automation::app.flows.validation.bad-wait-params')];
        }

        return (int) ($step['minutes'] ?? 0) > 0
            ? []
            : [trans('marketing_automation::app.flows.validation.bad-wait-params')];
    }

    /**
     * A condition step needs a well-formed rule tree.
     *
     * @param  array<string, mixed>  $step
     * @return array<int, string>
     */
    protected function validateConditionStep(array $step): array
    {
        $rule = $step['rule'] ?? [];

        if (! is_array($rule) || ! $this->isRuleGroup($rule)) {
            return [trans('marketing_automation::app.flows.validation.bad-rule')];
        }

        return $this->ruleErrors($rule, 1);
    }

    /**
     * Whether a rule node is an {all|any: [...]} group.
     *
     * @param  array<string, mixed>  $rule
     */
    protected function isRuleGroup(array $rule): bool
    {
        return isset($rule['all']) || isset($rule['any']);
    }

    /**
     * Validate a rule node recursively: a non-empty group of nested groups
     * or {field, op, value} rows, mirroring what ConditionEvaluator runs.
     *
     * @param  array<string, mixed>  $rule
     * @return array<int, string>
     */
    protected function ruleErrors(array $rule, int $depth): array
    {
        if ($this->isRuleGroup($rule)) {
            $rows = $rule['all'] ?? $rule['any'];

            if ($depth > self::MAX_RULE_DEPTH || ! is_array($rows) || $rows === []) {
                return [trans('marketing_automation::app.flows.validation.bad-rule')];
            }

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    return [trans('marketing_automation::app.flows.validation.bad-rule')];
                }

                if ($errors = $this->ruleErrors($row, $depth + 1)) {
                    return $errors;
                }
            }

            return [];
        }

        $field = (string) ($rule['field'] ?? '');

        if ($field === '') {
            return [trans('marketing_automation::app.flows.validation.bad-rule')];
        }

        if (! in_array((string) ($rule['op'] ?? ''), self::RULE_OPERATORS, true)) {
            return [trans('marketing_automation::app.flows.validation.bad-rule')];
        }

        if (str_starts_with($field, 'has_consent.')
            && MessageChannel::tryFrom(substr($field, strlen('has_consent.'))) === null
        ) {
            return [trans('marketing_automation::app.flows.validation.invalid-consent-channel', ['channel' => substr($field, strlen('has_consent.'))])];
        }

        return [];
    }

    /**
     * The fallback channel, when set, needs content and a working provider.
     *
     * @param  array<string, mixed>  $step
     * @return array<int, string>
     */
    protected function validateFallback(Flow $flow, array $step): array
    {
        $fallbackChannelValue = $step['fallback_channel'] ?? null;

        if (blank($fallbackChannelValue)) {
            return [];
        }

        if (MessageChannel::tryFrom((string) $fallbackChannelValue) === null) {
            return [trans('marketing_automation::app.flows.validation.invalid-fallback-channel')];
        }

        if (! isset($step['fallback_step']) || ! is_array($step['fallback_step'])) {
            return [trans('marketing_automation::app.flows.validation.invalid-fallback-channel')];
        }

        $fallbackChannel = MessageChannel::from((string) $fallbackChannelValue);

        $errors = [];

        if ($fallbackChannel !== MessageChannel::Email
            && $this->providerCoverageErrors($flow, $fallbackChannel) !== []
        ) {
            $errors[] = trans('marketing_automation::app.flows.validation.fallback-no-provider', ['channel' => $fallbackChannel->value]);
        }

        if (! $this->fallbackHasContent($fallbackChannel, $step['fallback_step'])) {
            $errors[] = trans('marketing_automation::app.flows.validation.fallback-content-missing');
        }

        if ($fallbackChannel === MessageChannel::Whatsapp) {
            $templateId = $step['fallback_step']['template_id'] ?? null;

            $template = $templateId !== null
                ? WhatsAppTemplate::query()->find($templateId)
                : null;

            if ($template === null || $template->status !== 'approved') {
                $errors[] = trans('marketing_automation::app.flows.validation.fallback-template-not-approved');
            }
        }

        return $errors;
    }

    /**
     * Whether the fallback step carries the content its channel sends:
     * a subject and blocks for email, a body for SMS. WhatsApp content is the
     * approved template, which the template rule below checks.
     *
     * @param  array<string, mixed>  $fallbackStep
     */
    protected function fallbackHasContent(MessageChannel $channel, array $fallbackStep): bool
    {
        $hasLocalizedValue = fn (mixed $map): bool => is_array($map) && collect($map)->contains(
            fn ($value) => is_array($value) ? $value !== [] : trim((string) $value) !== '',
        );

        return match ($channel) {
            MessageChannel::Email    => $hasLocalizedValue($fallbackStep['subject'] ?? null) && $hasLocalizedValue($fallbackStep['blocks'] ?? null),
            MessageChannel::Sms      => $hasLocalizedValue($fallbackStep['body'] ?? null),
            MessageChannel::Whatsapp => true,
        };
    }

    /**
     * Email steps need known block types, valid coupon rules and, when
     * transactional, no rich blocks.
     *
     * @param  array<string, mixed>  $step
     * @return array<int, string>
     */
    protected function validateEmailStep(Flow $flow, array $step): array
    {
        $errors = [];

        foreach ($step['blocks'] ?? [] as $localeBlocks) {
            foreach ((array) $localeBlocks as $block) {
                if (! is_array($block)) {
                    continue;
                }

                if ($error = $this->unknownBlockError($block)) {
                    $errors[] = $error;
                }

                if (
                    $flow->type === FlowType::Transactional
                    && in_array($block['type'] ?? '', ['coupon', 'product_grid'], true)
                ) {
                    $errors[] = trans('marketing_automation::app.flows.validation.transactional-block', ['block' => $block['type']]);
                }

                if (($block['type'] ?? '') === 'coupon') {
                    if ($message = $this->couponRuleError((int) ($block['cart_rule_id'] ?? 0))) {
                        $errors[] = $message;
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * The error for a coupon block pointing at a missing or non-generating rule.
     */
    protected function couponRuleError(int $cartRuleId): string
    {
        $rule = CartRule::query()->find($cartRuleId);

        if (
            $rule === null
            || ! $rule->coupon_type
            || ! $rule->use_auto_generation
        ) {
            return trans('marketing_automation::app.flows.validation.coupon-rule');
        }

        return '';
    }

    /**
     * The error for a block the renderer has no view for; it would be
     * silently dropped at send time, so activation refuses it instead.
     *
     * @param  array<string, mixed>  $block
     */
    protected function unknownBlockError(array $block): ?string
    {
        $type = (string) ($block['type'] ?? '');

        if (in_array($type, self::EMAIL_BLOCK_TYPES, true)) {
            return null;
        }

        return trans('marketing_automation::app.flows.validation.unknown-block', ['block' => $type !== '' ? $type : '?']);
    }

    /**
     * SMS steps need a connected default provider on every flow channel.
     *
     * @return array<int, string>
     */
    protected function validateSmsStep(Flow $flow): array
    {
        return $this->providerCoverageErrors($flow, MessageChannel::Sms);
    }

    /**
     * WhatsApp steps need an approved template and provider coverage.
     *
     * @param  array<string, mixed>  $step
     * @return array<int, string>
     */
    protected function validateWhatsappStep(Flow $flow, array $step): array
    {
        $errors = $this->providerCoverageErrors($flow, MessageChannel::Whatsapp);

        $templateId = $step['template_id'] ?? null;

        $template = $templateId !== null
            ? WhatsAppTemplate::query()->find($templateId)
            : null;

        if ($template === null || $template->status !== 'approved') {
            $errors[] = trans('marketing_automation::app.flows.validation.template-not-approved');
        }

        return $errors;
    }

    /**
     * Every sales channel of the flow needs a connected default provider.
     *
     * @return array<int, string>
     */
    protected function providerCoverageErrors(Flow $flow, MessageChannel $messageChannel): array
    {
        foreach ($flow->channels as $channel) {
            $connection = $this->providerManager->defaultFor($channel->id, $messageChannel);

            if ($connection === null
                || $connection->status->value !== 'connected'
            ) {
                return [
                    trans('marketing_automation::app.flows.validation.no-provider', ['channel' => $messageChannel->value]),
                ];
            }
        }

        return [];
    }
}
