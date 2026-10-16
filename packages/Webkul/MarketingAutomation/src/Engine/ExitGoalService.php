<?php

namespace Webkul\MarketingAutomation\Engine;

use Illuminate\Support\Facades\Event;
use Webkul\MarketingAutomation\Contracts\Enrolment;
use Webkul\MarketingAutomation\Contracts\Flow;
use Webkul\MarketingAutomation\Enums\EnrolmentStatus;
use Webkul\MarketingAutomation\Enums\JourneyEvent;
use Webkul\MarketingAutomation\Repositories\ContactRepository;
use Webkul\MarketingAutomation\Repositories\EnrolmentEventRepository;
use Webkul\MarketingAutomation\Repositories\EnrolmentRepository;
use Webkul\MarketingAutomation\Services\PhoneNormalizer;
use Webkul\MarketingAutomation\Services\StatsRecorder;
use Webkul\Sales\Contracts\Order;

class ExitGoalService
{
    /**
     * Create a new exit goal service instance.
     */
    public function __construct(
        protected ContactRepository $contactRepository,
        protected EnrolmentRepository $enrolmentRepository,
        protected EnrolmentEventRepository $enrolmentEventRepository,
        protected PhoneNormalizer $phoneNormalizer,
        protected StatsRecorder $statsRecorder,
    ) {}

    /**
     * Convert the active enrolments of the order's contacts whose flow exits on an order.
     */
    public function orderPlaced(Order $order): void
    {
        $contactIds = $this->contactIdsFor($order);

        if ($contactIds === []) {
            return;
        }

        $this->enrolmentRepository
            ->activeForContactsSince($contactIds, $order->created_at)
            ->filter(fn (Enrolment $enrolment) => $this->hasOrderPlacedGoal($enrolment->flow))
            ->each(fn (Enrolment $enrolment) => $this->convert($enrolment));
    }

    /**
     * Mark the enrolment converted and record why it left the flow.
     */
    protected function convert(Enrolment $enrolment): void
    {
        $enrolment = $this->enrolmentRepository->update([
            'status'       => EnrolmentStatus::Converted->value,
            'completed_at' => now(),
            'next_run_at'  => null,
        ], $enrolment->id);

        $this->enrolmentEventRepository->create([
            'enrolment_id' => $enrolment->id,
            'event'        => JourneyEvent::Exited->value,
            'reason'       => 'goal_met',
        ]);

        $this->statsRecorder->increment('converted', [
            'flow_id'    => $enrolment->flow_id,
            'step_key'   => (string) $enrolment->current_step_key,
            'channel_id' => $enrolment->channel_id,
        ]);

        Event::dispatch('marketing_automation.enrolment.finish.after', [$enrolment]);
    }

    /**
     * The order's contacts: the customer, an unmerged guest of the email, or a phone-only guest.
     *
     * @return array<int, int>
     */
    protected function contactIdsFor(Order $order): array
    {
        $billing = $order->billing_address;

        return $this->contactRepository->idsMatching(
            (int) $order->channel_id,
            $order->customer_id ? (int) $order->customer_id : null,
            filled($order->customer_email) ? (string) $order->customer_email : null,
            $this->phoneNormalizer->normalize($billing?->phone, $billing?->country),
        );
    }

    /**
     * Whether the flow exits when an order is placed.
     */
    protected function hasOrderPlacedGoal(?Flow $flow): bool
    {
        return collect((array) ($flow?->exit_goals ?? []))
            ->contains(fn ($goal) => ($goal['type'] ?? null) === 'order_placed');
    }
}
