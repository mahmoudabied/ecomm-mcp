<x-admin::layouts>
    <x-slot:title>
        @lang('klaviyo::app.menu.klaviyo')
    </x-slot>

    <div class="flex flex-col gap-4" id="klaviyo-page">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white">
                @lang('klaviyo::app.admin.title')
            </h1>

            <button
                type="button"
                class="primary-button"
                @click="retryFailed()"
            >
                @lang('klaviyo::app.admin.retry-failed')
            </button>
        </div>

        @foreach ($connections as $connection)
            <div class="rounded border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-medium text-gray-800 dark:text-white">
                            {{ $connection['channel'] }}
                        </h2>

                        <span class="text-sm {{ $connection['status'] === 'connected' ? 'text-green-600' : 'text-red-500' }}">
                            {{ $connection['status'] }}
                        </span>

                        @if ($connection['last_error'])
                            <p class="text-sm text-red-500">{{ $connection['last_error'] }}</p>
                        @endif
                    </div>

                    <div class="flex items-center gap-4 text-sm text-gray-600 dark:text-gray-300">
                        <span>@lang('klaviyo::app.admin.outbox-pending'): {{ $connection['outbox']['pending'] }}</span>
                        <span>@lang('klaviyo::app.admin.outbox-failed'): {{ $connection['outbox']['failed'] }}</span>
                        <span>@lang('klaviyo::app.admin.outbox-sent-24h'): {{ $connection['outbox']['sent_24h'] }}</span>
                    </div>
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <div class="grid gap-2">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            @lang('klaviyo::app.admin.api-key')
                        </label>

                        <input
                            type="password"
                            class="flex h-9 w-full rounded border border-gray-200 px-2 text-sm dark:border-gray-800 dark:bg-gray-950"
                            :value="connections[{{ $loop->index }}].api_key"
                            placeholder="pk_..."
                        >
                    </div>

                    <div class="grid gap-2">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            @lang('klaviyo::app.admin.public-key')
                        </label>

                        <input
                            type="text"
                            class="flex h-9 w-full rounded border border-gray-200 px-2 text-sm dark:border-gray-800 dark:bg-gray-950"
                            :value="connections[{{ $loop->index }}].public_key"
                        >
                    </div>
                </div>

                <div class="mt-4 flex items-center gap-2">
                    <button
                        type="button"
                        class="primary-button"
                        @click="connect({{ $loop->index }})"
                    >
                        @lang('klaviyo::app.admin.connect')
                    </button>

                    <button
                        type="button"
                        class="secondary-button"
                        @click="disconnect({{ $loop->index }})"
                    >
                        @lang('klaviyo::app.admin.disconnect')
                    </button>

                    <button
                        type="button"
                        class="secondary-button"
                        @click="sync({{ $loop->index }})"
                    >
                        @lang('klaviyo::app.admin.start-sync')
                    </button>
                </div>
            </div>
        @endforeach

        <div class="rounded border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-lg font-medium text-gray-800 dark:text-white">
                @lang('klaviyo::app.admin.sync-title')
            </h2>

            <table class="mt-3 w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500">
                        <th class="py-2">@lang('klaviyo::app.admin.entity')</th>
                        <th class="py-2">@lang('klaviyo::app.admin.status')</th>
                        <th class="py-2">@lang('klaviyo::app.admin.progress')</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($syncJobs as $job)
                        <tr class="border-t border-gray-100 dark:border-gray-800">
                            <td class="py-2 capitalize">{{ $job['entity'] }}</td>
                            <td class="py-2">{{ $job['status'] }}</td>
                            <td class="py-2">{{ $job['processed'] }} / {{ $job['total'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="py-2 text-gray-500">
                                @lang('klaviyo::app.admin.no-sync-jobs')
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <a
            href="{{ route('admin.configuration.index', ['marketing_automation' => 'routing']) }}"
            class="text-sm text-blue-600 hover:underline"
        >
            @lang('klaviyo::app.admin.routing-link')
        </a>
    </div>

    @pushOnce('scripts')
        <script>
            Vue.createApp({
                data() {
                    return {
                        connections: @json($connections),
                        syncJobs: @json($syncJobs),
                    };
                },

                methods: {
                    async connect(index) {
                        const key = this.$el.querySelector(`input[type=password]`);

                        const response = await fetch('{{ route('admin.marketing.klaviyo.connect') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content'),
                            },
                            body: JSON.stringify({
                                channel_id: this.connections[index].channel_id,
                                api_key: key.value,
                            }),
                        });

                        window.flashMessages = [{ type: response.ok ? 'success' : 'error', message: (await response.json()).message }];

                        window.flashDataMessenger();

                        if (response.ok) {
                            window.location.reload();
                        }
                    },

                    async disconnect(index) {
                        await fetch('{{ route('admin.marketing.klaviyo.disconnect') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content'),
                            },
                            body: JSON.stringify({ channel_id: this.connections[index].channel_id }),
                        });

                        window.location.reload();
                    },

                    async sync(index) {
                        const response = await fetch('{{ route('admin.marketing.klaviyo.sync') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content'),
                            },
                            body: JSON.stringify({
                                channel_id: this.connections[index].channel_id,
                                entities: ['customers', 'orders', 'catalog'],
                            }),
                        });

                        window.flashMessages = [{ type: response.ok ? 'success' : 'error', message: (await response.json()).message }];

                        window.flashDataMessenger();

                        this.pollSyncStatus();
                    },

                    async retryFailed() {
                        const response = await fetch('{{ route('admin.marketing.klaviyo.retry_failed') }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content'),
                            },
                        });

                        window.flashMessages = [{ type: 'success', message: (await response.json()).message }];

                        window.flashDataMessenger();
                    },

                    async pollSyncStatus() {
                        const response = await fetch('{{ route('admin.marketing.klaviyo.sync_status') }}');

                        const body = await response.json();

                        this.syncJobs = body.data;

                        if (this.syncJobs.some((job) => ['queued', 'running'].includes(job.status))) {
                            setTimeout(this.pollSyncStatus, 3000);
                        }
                    },
                },
            }).mount('#klaviyo-page');
        </script>
    @endPushOnce
</x-admin::layouts>
