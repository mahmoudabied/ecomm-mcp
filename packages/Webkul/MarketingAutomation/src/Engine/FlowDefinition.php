<?php

namespace Webkul\MarketingAutomation\Engine;

use InvalidArgumentException;
use Webkul\MarketingAutomation\Enums\StepType;

class FlowDefinition
{
    /**
     * Create a definition wrapper around the stored step graph.
     */
    public function __construct(
        protected array $definition,
    ) {
        if (! isset($definition['entry'], $definition['steps']) || ! is_array($definition['steps'])) {
            throw new InvalidArgumentException('A flow definition needs an "entry" key and a "steps" map.');
        }
    }

    /**
     * The key of the first step.
     */
    public function entry(): string
    {
        return (string) $this->definition['entry'];
    }

    /**
     * Get one step by key.
     *
     * @return array<string, mixed>
     */
    public function step(string $key): array
    {
        if (! isset($this->definition['steps'][$key])) {
            throw new InvalidArgumentException("Unknown flow step [{$key}].");
        }

        return $this->definition['steps'][$key];
    }

    /**
     * All step keys.
     *
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->definition['steps']);
    }

    /**
     * All steps.
     *
     * @return array<string, array<string, mixed>>
     */
    public function steps(): array
    {
        return $this->definition['steps'];
    }

    /**
     * Resolve the successor key of a step, honoring condition branches.
     */
    public function nextOf(string $key, ?bool $branch = null): ?string
    {
        $step = $this->step($key);

        if (($step['type'] ?? '') === StepType::Condition->value) {
            return $branch ? ($step['yes'] ?? null) : ($step['no'] ?? null);
        }

        return $step['next'] ?? null;
    }

    /**
     * The successors referenced by a step (next plus both branches).
     *
     * @return array<int, string>
     */
    public function successorsOf(string $key): array
    {
        $step = $this->step($key);

        return array_values(array_filter([
            $step['next'] ?? null,
            $step['yes'] ?? null,
            $step['no'] ?? null,
        ]));
    }

    /**
     * Whether a step is a message step.
     */
    public function isSendStep(string $key): bool
    {
        return in_array(
            $this->step($key)['type'] ?? '',
            [StepType::SendEmail->value, StepType::SendSms->value, StepType::SendWhatsapp->value],
            true,
        );
    }

    /**
     * A stable fingerprint of the definition, used to detect real edits.
     */
    public function hash(): string
    {
        return hash('sha256', json_encode($this->sortRecursively($this->definition)));
    }

    /**
     * Whether every step key matches the s<n> convention.
     */
    public function keysAreWellFormed(): bool
    {
        foreach ($this->keys() as $key) {
            if (! preg_match('/^s\d+$/', (string) $key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sort an array tree by key so hashing is order-insensitive.
     */
    protected function sortRecursively(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $sorted = $value;

        ksort($sorted);

        return array_map(fn ($item) => $this->sortRecursively($item), $sorted);
    }
}
