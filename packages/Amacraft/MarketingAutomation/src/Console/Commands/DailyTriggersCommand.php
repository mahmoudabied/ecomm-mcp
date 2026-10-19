<?php

namespace Amacraft\MarketingAutomation\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Webkul\Core\Models\Channel;
use Amacraft\MarketingAutomation\DTOs\TriggerEvent;
use Amacraft\MarketingAutomation\Enums\FlowStatus;
use Amacraft\MarketingAutomation\Enums\TriggerType;
use Amacraft\MarketingAutomation\Jobs\IngestTrigger;
use Amacraft\MarketingAutomation\Services\ContactResolver;
use Amacraft\MarketingAutomation\Services\Settings;

class DailyTriggersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'automation:daily-triggers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fire the once-a-day triggers (birthdays and inactive customers)';

    /**
     * The local hour the scans run at, once per local day.
     */
    protected const RUN_HOUR = 9;

    /**
     * Create a new command instance.
     */
    public function __construct(
        protected ContactResolver $contactResolver,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(Settings $settings): int
    {
        foreach (Channel::query()->get() as $channel) {
            $nowLocal = CarbonImmutable::now($settings->timezone($channel));

            if ((int) $nowLocal->format('G') !== self::RUN_HOUR) {
                continue;
            }

            $guardKey = "marketing_automation.daily.{$channel->code}.".$nowLocal->format('Y-m-d');

            if (! Cache::add($guardKey, true, now()->addHours(2))) {
                continue;
            }

            $this->fireBirthdays($channel, $nowLocal);

            $this->fireInactive($channel, $nowLocal);
        }

        return self::SUCCESS;
    }

    /**
     * Enrol customers whose birthday is today in the channel timezone,
     * when an active birthday flow exists on the channel.
     */
    protected function fireBirthdays($channel, CarbonImmutable $nowLocal): void
    {
        if (! $this->hasActiveFlow($channel, TriggerType::CustomerBirthday)) {
            return;
        }

        DB::table('customers')
            ->where('channel_id', $channel->id)
            ->whereRaw('DAY(date_of_birth) = ? AND MONTH(date_of_birth) = ?', [$nowLocal->day, $nowLocal->month])
            ->orderBy('id')
            ->chunkById(500, function ($customers) use ($channel, $nowLocal) {
                foreach ($customers as $customer) {
                    IngestTrigger::dispatchDto(new TriggerEvent(
                        type: TriggerType::CustomerBirthday,
                        channelId: $channel->id,
                        customerId: $customer->id,
                        email: $customer->email,
                        phone: $customer->phone,
                        triggerKey: "birthday:{$customer->id}:".$nowLocal->format('Y'),
                        payload: [],
                        locale: $customer->locale ?? null,
                    ));
                }
            });
    }

    /**
     * Enrol customers whose last order is exactly N days ago, comparing
     * dates in the store timezone rather than the database server's.
     */
    protected function fireInactive($channel, CarbonImmutable $nowLocal): void
    {
        $inactiveDaysList = $this->activeFlowsQuery($channel, TriggerType::CustomerInactive)
            ->pluck('automation_flows.trigger_config')
            ->map(function ($config) {
                $config = is_string($config) ? json_decode($config, true) : (array) $config;

                return (int) ($config['inactive_days'] ?? 90);
            })
            ->unique()
            ->values();

        foreach ($inactiveDaysList as $inactiveDays) {
            $this->fireInactiveFor($channel, $nowLocal, $inactiveDays);
        }
    }

    /**
     * Enrol the customers whose latest non-canceled order on the channel fell
     * on the local calendar day N days ago. The day is turned into a UTC-side
     * [start, end) range and matched with HAVING, so only matching customers
     * are ever loaded.
     */
    protected function fireInactiveFor($channel, CarbonImmutable $nowLocal, int $inactiveDays): void
    {
        $dayStart = $nowLocal->subDays($inactiveDays)->startOfDay();

        $storageTimezone = config('app.timezone');

        $lastOrders = DB::table('orders')
            ->select('customer_id')
            ->where('channel_id', $channel->id)
            ->where('status', '!=', 'canceled')
            ->whereNotNull('customer_id')
            ->groupBy('customer_id')
            ->havingRaw('MAX(created_at) >= ? AND MAX(created_at) < ?', [
                $dayStart->setTimezone($storageTimezone)->format('Y-m-d H:i:s'),
                $dayStart->addDay()->setTimezone($storageTimezone)->format('Y-m-d H:i:s'),
            ]);

        DB::table('customers')
            ->joinSub($lastOrders, 'last_orders', 'last_orders.customer_id', '=', 'customers.id')
            ->select('customers.id', 'customers.email', 'customers.phone')
            ->orderBy('customers.id')
            ->chunkById(500, function ($customers) use ($channel, $inactiveDays, $nowLocal) {
                foreach ($customers as $customer) {
                    // Contacts are created lazily, so resolve (or create) one first.
                    $contact = $this->contactResolver->resolve(
                        channelId: $channel->id,
                        customerId: $customer->id,
                        email: $customer->email,
                        phone: $customer->phone,
                    );

                    IngestTrigger::dispatchDto(new TriggerEvent(
                        type: TriggerType::CustomerInactive,
                        channelId: $channel->id,
                        customerId: $customer->id,
                        email: $customer->email,
                        phone: $customer->phone,
                        triggerKey: "inactive:{$contact->id}:".$nowLocal->format('Y-m-d'),
                        payload: ['inactive_days' => $inactiveDays],
                        locale: $contact->locale,
                    ));
                }
            }, 'customers.id', 'id');
    }

    /**
     * Whether an active flow with the trigger exists on the channel.
     */
    protected function hasActiveFlow($channel, TriggerType $type): bool
    {
        return $this->activeFlowsQuery($channel, $type)->exists();
    }

    /**
     * Active flows with the trigger that are attached to the sales channel.
     */
    protected function activeFlowsQuery($channel, TriggerType $type): Builder
    {
        return DB::table('automation_flows')
            ->join('automation_flow_channels', 'automation_flow_channels.flow_id', '=', 'automation_flows.id')
            ->where('automation_flows.status', FlowStatus::Active->value)
            ->where('automation_flows.trigger_type', $type->value)
            ->where('automation_flow_channels.channel_id', $channel->id);
    }
}
