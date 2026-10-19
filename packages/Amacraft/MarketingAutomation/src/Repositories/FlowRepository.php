<?php

namespace Amacraft\MarketingAutomation\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Webkul\Core\Eloquent\Repository;
use Amacraft\MarketingAutomation\Engine\FlowDefinition;
use Amacraft\MarketingAutomation\Engine\FlowValidator;
use Amacraft\MarketingAutomation\Enums\EnrolmentStatus;
use Amacraft\MarketingAutomation\Enums\FlowStatus;
use Amacraft\MarketingAutomation\Enums\TriggerType;
use Amacraft\MarketingAutomation\Models\Flow;
use Amacraft\MarketingAutomation\Models\FlowVersion;

class FlowRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return 'Amacraft\MarketingAutomation\Contracts\Flow';
    }

    /**
     * Validate the draft and publish it as the current version.
     *
     * @throws ValidationException
     */
    public function activate(Flow $flow): FlowVersion
    {
        if ($flow->draft_definition === null) {
            throw ValidationException::withMessages([
                'steps' => [trans('marketing_automation::app.flows.validation.no-send-step')],
            ]);
        }

        $errors = app(FlowValidator::class)->validate($flow, $flow->draft_definition);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($flow) {
            $draft = new FlowDefinition($flow->draft_definition);
            $current = $flow->currentVersion;

            if (
                $current === null
                || $current->definition_hash !== $draft->hash()
            ) {
                $version = $flow->versions()->create([
                    'version'         => ((int) $flow->versions()->max('version')) + 1,
                    'definition'      => $flow->draft_definition,
                    'definition_hash' => $draft->hash(),
                ]);
            } else {
                $version = $current;
            }

            $flow->update([
                'status'             => FlowStatus::Active->value,
                'current_version_id' => $version->id,
                'activated_at'       => now(),
                'paused_at'          => null,
                'resumed_at'         => $flow->status === FlowStatus::Paused ? now() : $flow->resumed_at,
            ]);

            Event::dispatch('marketing_automation.flow.activate.after', [$flow, $version]);

            return $version;
        });
    }

    /**
     * Stop scheduling steps for this flow.
     */
    public function pause(Flow $flow): void
    {
        $flow->update([
            'status'    => FlowStatus::Paused->value,
            'paused_at' => now(),
        ]);

        Event::dispatch('marketing_automation.flow.pause.after', [$flow]);
    }

    /**
     * Reactivate a paused flow, stamping the resume time for the overdue rule.
     */
    public function resume(Flow $flow): void
    {
        $flow->update([
            'status'     => FlowStatus::Active->value,
            'resumed_at' => now(),
        ]);

        Event::dispatch('marketing_automation.flow.resume.after', [$flow]);
    }

    /**
     * Copy a flow into a fresh draft named "<name> (copy)".
     */
    public function duplicate(Flow $flow): Flow
    {
        $copy = DB::transaction(function () use ($flow) {
            $duplicate = $flow->replicate(['current_version_id']);

            $duplicate->name = $flow->name.' (copy)';
            $duplicate->status = FlowStatus::Draft->value;
            $duplicate->activated_at = null;
            $duplicate->paused_at = null;
            $duplicate->resumed_at = null;
            $duplicate->current_version_id = null;
            $duplicate->save();

            $duplicate->channels()->sync($flow->channels->pluck('id')->all());

            return $duplicate;
        });

        Event::dispatch('marketing_automation.flow.duplicate.after', [$flow, $copy]);

        return $copy;
    }

    /**
     * Cancel the running enrolments, then remove the flow.
     */
    public function deleteFlow(Flow $flow): void
    {
        Event::dispatch('marketing_automation.flow.delete.before', [$flow]);

        DB::transaction(function () use ($flow) {
            $flow->enrolments()
                ->where('status', EnrolmentStatus::Active->value)
                ->update([
                    'status'       => EnrolmentStatus::Cancelled->value,
                    'exit_reason'  => 'flow_deleted',
                    'completed_at' => now(),
                    'next_run_at'  => null,
                ]);

            $flow->channels()->detach();

            $flow->delete();
        });
    }

    /**
     * The idle delays, in minutes, of the channel's active cart-abandoned flows.
     *
     * @return Collection<int, int>
     */
    public function activeCartAbandonedDelays(int $channelId, int $defaultMinutes): Collection
    {
        return $this->model
            ->where('trigger_type', TriggerType::CartAbandoned->value)
            ->where('status', FlowStatus::Active->value)
            ->whereHas('channels', fn ($query) => $query->where('channels.id', $channelId))
            ->get()
            ->map(fn (Flow $flow) => (int) ($flow->trigger_config['abandon_delay_minutes'] ?? $defaultMinutes))
            ->values()
            ->toBase();
    }
}
