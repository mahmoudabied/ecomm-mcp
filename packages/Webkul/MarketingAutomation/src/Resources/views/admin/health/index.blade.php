<x-admin::layouts>
    <x-slot:title>
        @lang('marketing_automation::app.health.title')
    </x-slot>

    <div class="flex justify-between gap-4 max-sm:flex-wrap">
        <p class="text-xl font-bold text-gray-800 dark:text-white">
            @lang('marketing_automation::app.health.title')
        </p>
    </div>

    <div class="mt-4 grid gap-4">
        <div class="flex items-center justify-between rounded bg-white p-4 shadow dark:bg-gray-900">
            <div>
                <p class="font-semibold text-gray-800 dark:text-white">
                    @lang('marketing_automation::app.health.queue')
                </p>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    @lang('marketing_automation::app.health.queue-hint', ['driver' => $report['queue_driver']])
                </p>
            </div>

            <span class="rounded px-3 py-1 text-sm font-semibold {{ $report['queue_ok'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                @if ($report['queue_ok'])
                    @lang('marketing_automation::app.health.ok')
                @else
                    @lang('marketing_automation::app.health.warning')
                @endif
            </span>
        </div>

        <div class="flex items-center justify-between rounded bg-white p-4 shadow dark:bg-gray-900">
            <div>
                <p class="font-semibold text-gray-800 dark:text-white">
                    @lang('marketing_automation::app.health.scheduler')
                </p>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    @if ($report['scheduler_last_seen_at'])
                        @lang('marketing_automation::app.health.scheduler-last', ['time' => $report['scheduler_last_seen_at']])
                    @else
                        @lang('marketing_automation::app.health.scheduler-never')
                    @endif
                </p>
            </div>

            <span class="rounded px-3 py-1 text-sm font-semibold {{ $report['scheduler_ok'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                @if ($report['scheduler_ok'])
                    @lang('marketing_automation::app.health.ok')
                @else
                    @lang('marketing_automation::app.health.warning')
                @endif
            </span>
        </div>

        <div class="flex items-center justify-between rounded bg-white p-4 shadow dark:bg-gray-900">
            <div>
                <p class="font-semibold text-gray-800 dark:text-white">
                    @lang('marketing_automation::app.health.mail')
                </p>
                @unless ($report['mail_configured'])
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        @lang('marketing_automation::app.health.mail-hint')
                    </p>
                @endunless
            </div>

            <span class="rounded px-3 py-1 text-sm font-semibold {{ $report['mail_configured'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                @if ($report['mail_configured'])
                    @lang('marketing_automation::app.health.ok')
                @else
                    @lang('marketing_automation::app.health.warning')
                @endif
            </span>
        </div>
    </div>
</x-admin::layouts>
