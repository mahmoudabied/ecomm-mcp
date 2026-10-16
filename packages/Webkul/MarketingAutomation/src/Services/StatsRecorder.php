<?php

namespace Webkul\MarketingAutomation\Services;

/**
 * Records per-step counters. The reporting story (T118) replaces this
 * stub with the real rollup writer; the call sites stay identical.
 */
class StatsRecorder
{
    /**
     * Increment a counter for a step.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function increment(string $metric, array $attributes = []): void
    {
        //
    }
}
