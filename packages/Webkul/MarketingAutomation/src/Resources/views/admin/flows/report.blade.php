<x-admin::layouts :title="trans('marketing_automation::app.reports.title')">
    <x-slot:title>
        @lang('marketing_automation::app.reports.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between">
            <p class="text-xl font-bold">
                {{ $flow->name }} · @lang('marketing_automation::app.reports.title')
            </p>

            <a
                href="{{ route('admin.marketing.automation.flows.edit', $flow->id) }}"
                class="primary-button"
            >
                @lang('marketing_automation::app.flows.datagrid.edit')
            </a>
        </div>

        <form
            method="GET"
            class="flex flex-wrap items-end gap-3 rounded bg-white p-4 shadow dark:bg-gray-900"
        >
            <div>
                <label class="mb-1 block text-sm">@lang('marketing_automation::app.reports.from')</label>

                <input
                    type="date"
                    name="from"
                    value="{{ $from->toDateString() }}"
                    class="rounded border bg-white px-2.5 py-2 text-sm dark:bg-gray-900"
                >
            </div>

            <div>
                <label class="mb-1 block text-sm">@lang('marketing_automation::app.reports.to')</label>

                <input
                    type="date"
                    name="to"
                    value="{{ $to->toDateString() }}"
                    class="rounded border bg-white px-2.5 py-2 text-sm dark:bg-gray-900"
                >
            </div>

            <div>
                <label class="mb-1 block text-sm">@lang('marketing_automation::app.reports.channel')</label>

                <select
                    name="channel_id"
                    class="rounded border bg-white px-2.5 py-2 text-sm dark:bg-gray-900"
                >
                    <option value="">@lang('marketing_automation::app.reports.all-channels')</option>

                    @foreach (core()->getAllChannels() as $channel)
                        <option
                            value="{{ $channel->id }}"
                            @if ((int) request('channel_id') === $channel->id) selected @endif
                        >
                            {{ $channel->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <button
                type="submit"
                class="secondary-button"
            >
                @lang('marketing_automation::app.reports.title')
            </button>
        </form>

        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            @foreach ([
                'enrolled'   => trans('marketing_automation::app.reports.enrolled'),
                'sent'       => trans('marketing_automation::app.reports.sent'),
                'delivered'  => trans('marketing_automation::app.reports.delivered'),
                'openRate'   => trans('marketing_automation::app.reports.open-rate'),
                'clickRate'  => trans('marketing_automation::app.reports.click-rate'),
                'converted'  => trans('marketing_automation::app.reports.converted'),
                'revenue'    => trans('marketing_automation::app.reports.revenue'),
            ] as $key => $label)
                <div class="rounded bg-white p-4 text-center shadow dark:bg-gray-900">
                    <p class="text-sm text-gray-500">{{ $label }}</p>

                    <p class="text-2xl font-bold">{{ $kpis[$key] }}</p>
                </div>
            @endforeach
        </div>

        <div class="rounded bg-white p-4 shadow dark:bg-gray-900">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-start text-gray-500 dark:border-gray-700">
                        <th class="p-2 text-start">@lang('marketing_automation::app.reports.step')</th>

                        <th class="p-2 text-start">@lang('marketing_automation::app.reports.type')</th>

                        <th class="p-2 text-start">@lang('marketing_automation::app.reports.sent')</th>

                        <th class="p-2 text-start">@lang('marketing_automation::app.reports.delivered')</th>

                        <th class="p-2 text-start">@lang('marketing_automation::app.reports.open-rate')</th>

                        <th class="p-2 text-start">@lang('marketing_automation::app.reports.click-rate')</th>

                        <th class="p-2 text-start">@lang('marketing_automation::app.reports.skipped')</th>

                        <th class="p-2 text-start">@lang('marketing_automation::app.reports.failed')</th>

                        <th class="p-2 text-start">@lang('marketing_automation::app.reports.revenue')</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($steps as $step)
                        <tr class="border-b dark:border-gray-700">
                            <td class="p-2 font-medium">{{ $step['step_key'] }}</td>

                            <td class="p-2">{{ $step['type_label'] }}</td>

                            <td class="p-2">{{ $step['sent'] }}</td>

                            <td class="p-2">{{ $step['delivered'] }}</td>

                            <td class="p-2">{{ $step['open_rate'] }}</td>

                            <td class="p-2">{{ $step['click_rate'] }}</td>

                            <td
                                class="p-2"
                                title="{{ $step['skipped_title'] }}"
                            >{{ $step['skipped'] }}</td>

                            <td class="p-2">{{ $step['failed'] }}</td>

                            <td class="p-2">{{ $step['revenue_formatted'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="9"
                                class="p-4 text-center text-gray-500"
                            >
                                @lang('marketing_automation::app.providers.defaults.none')
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="rounded bg-white p-4 shadow dark:bg-gray-900">
            <p class="mb-3 font-semibold">@lang('marketing_automation::app.reports.enrolments')</p>

            <x-admin::datagrid src="{{ route('admin.marketing.automation.flows.enrolments', $flow->id) }}"></x-admin::datagrid>
        </div>
    </div>
    @pushOnce('scripts')
        <script>
            document.addEventListener('click', function (event) {
                const journeyButton = event.target.closest('[data-journey-id]');

                if (! journeyButton) {
                    return;
                }

                fetch("{{ route('admin.marketing.automation.enrolments.journey', ':id') }}".replace(':id', journeyButton.dataset.journeyId), {
                    headers: { 'Accept': 'application/json' },
                })
                    .then((response) => response.json())
                    .then((payload) => {
                        const list = document.getElementById('ma-journey');

                        list.innerHTML = (payload.data?.events ?? []).map((entry) => (
                            '<li class="border-b py-2 dark:border-gray-700">'
                                + entry.created_at + ' · ' + entry.event
                                + (entry.reason ? ' (' + entry.reason + ')' : '')
                                + (entry.message ? ' · ' + entry.message.channel + ' · ' + entry.message.status
                                    + (entry.message.recipient_masked ? ' · ' + entry.message.recipient_masked : '')
                                    + (entry.message.error_message ? ' · ' + entry.message.error_message : '') : '')
                            + '</li>'
                        )).join('');

                        document.getElementById('ma-journey-drawer').classList.remove('hidden');
                    });
            });
        </script>
    @endPushOnce

    <div
        id="ma-journey-drawer"
        class="fixed inset-y-0 end-0 z-50 hidden w-96 overflow-y-auto bg-white p-4 shadow-lg dark:bg-gray-900"
    >
        <div class="mb-3 flex items-center justify-between">
            <p class="font-semibold">@lang('marketing_automation::app.reports.journey')</p>

            <button
                type="button"
                onclick="document.getElementById('ma-journey-drawer').classList.add('hidden')"
            >×</button>
        </div>

        <ul
            id="ma-journey"
            class="text-sm"
        ></ul>
    </div>
</x-admin::layouts>
