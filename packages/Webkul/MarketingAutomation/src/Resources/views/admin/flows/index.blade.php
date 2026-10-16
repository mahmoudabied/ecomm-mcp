<x-admin::layouts>
    <x-slot:title>
        @lang('marketing_automation::app.flows.index.title')
    </x-slot>

    @php
        $triggerLabels = collect(\Webkul\MarketingAutomation\Enums\TriggerType::cases())
            ->mapWithKeys(fn ($case) => [$case->value => trans("marketing_automation::app.flows.triggers.{$case->value}")])
            ->all();

        $channelOptions = core()->getAllChannels()
            ->map(fn ($channel) => ['id' => $channel->id, 'name' => $channel->name])
            ->values()
            ->all();
    @endphp

    <v-flows>
        <div class="flex justify-between gap-4 max-sm:flex-wrap">
            <p class="text-xl font-bold text-gray-800 dark:text-white">
                @lang('marketing_automation::app.flows.index.title')
            </p>

            <div class="flex items-center gap-x-2.5">
                @if (bouncer()->hasPermission('marketing.automation.flows.manage'))
                    <div
                        class="primary-button"
                        @click="openCreate"
                    >
                        @lang('marketing_automation::app.flows.index.create-btn')
                    </div>
                @endif
            </div>
        </div>

        <x-admin::shimmer.datagrid />
    </v-flows>

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-flows-template"
        >
            <div>
                <div class="flex justify-between gap-4 max-sm:flex-wrap">
                    <p class="text-xl font-bold text-gray-800 dark:text-white">
                        @lang('marketing_automation::app.flows.index.title')
                    </p>

                    @if (bouncer()->hasPermission('marketing.automation.flows.manage'))
                        <div
                            class="primary-button"
                            @click="openCreate"
                        >
                            @lang('marketing_automation::app.flows.index.create-btn')
                        </div>
                    @endif
                </div>

                <x-admin::datagrid
                    src="{{ route('admin.marketing.automation.flows.index') }}"
                    ref="datagrid"
                >
                    <template #body="{ isLoading, available, applied, selectAll, sort, performAction }">
                        <template v-if="isLoading">
                            <x-admin::shimmer.datagrid.table.body />
                        </template>

                        <template v-else>
                            <div
                                v-for="record in available.records"
                                :key="record.id"
                                class="row grid items-center gap-2.5 border-b px-4 py-4 text-gray-600 transition-all hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-gray-950"
                                :style="`grid-template-columns: repeat(${gridsCount}, minmax(0, 1fr))`"
                            >
                                <p>@{{ record.id }}</p>

                                <p>@{{ record.name }}</p>

                                <p>@{{ record.trigger_type }}</p>

                                <p>@{{ record.type }}</p>

                                <p><span v-html="record.status"></span></p>

                                <p>@{{ record.channels }}</p>

                                <p>@{{ record.enrolled_30d }}</p>

                                <p>@{{ record.revenue_30d }}</p>

                                @if (bouncer()->hasPermission('marketing.automation.flows.manage'))
                                    <div class="flex gap-2.5">
                                        <span
                                            :title="@js(trans('marketing_automation::app.flows.datagrid.edit'))"
                                            class="icon-edit cursor-pointer rounded-md p-1.5 text-2xl transition-all hover:bg-gray-200 dark:hover:bg-gray-800"
                                            @click="edit(record)"
                                        ></span>

                                        <span
                                            :title="@js(trans('marketing_automation::app.flows.datagrid.duplicate'))"
                                            class="icon-copy cursor-pointer rounded-md p-1.5 text-2xl transition-all hover:bg-gray-200 dark:hover:bg-gray-800"
                                            @click="duplicate(record)"
                                        ></span>

                                        <span
                                            :title="@js(trans('marketing_automation::app.flows.datagrid.delete'))"
                                            class="icon-delete cursor-pointer rounded-md p-1.5 text-2xl transition-all hover:bg-gray-200 dark:hover:bg-gray-800"
                                            @click="remove(record)"
                                        ></span>
                                    </div>
                                @endif
                            </div>
                        </template>
                    </template>
                </x-admin::datagrid>

                <!-- Create flow drawer -->
                <x-admin::drawer ref="createDrawer">
                    <x-slot:header>
                        <p class="text-base font-semibold text-gray-800 dark:text-white">
                            @lang('marketing_automation::app.flows.index.create-btn')
                        </p>
                    </x-slot>

                    <x-slot:content>
                        <div class="grid gap-4">
                            <div>
                                <label class="mb-1 block text-sm">@lang('marketing_automation::app.flows.index.templates')</label>

                                <select
                                    class="flex w-full rounded border bg-white px-2.5 py-2 text-sm dark:bg-gray-900"
                                    v-model="form.template_key"
                                >
                                    <option value="">@lang('marketing_automation::app.flows.index.blank')</option>

                                    <option
                                        v-for="template in templates"
                                        :key="template.key"
                                        :value="template.key"
                                    >
                                        @{{ template.name }}
                                    </option>
                                </select>

                                <p
                                    v-if="selectedTemplate"
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    @{{ selectedTemplate.description }}
                                </p>
                            </div>

                            <div>
                                <label class="mb-1 block text-sm">@lang('marketing_automation::app.flows.form.name')</label>

                                <input
                                    type="text"
                                    class="flex w-full rounded border bg-white px-2.5 py-2 text-sm dark:bg-gray-900"
                                    v-model="form.name"
                                >
                            </div>

                            <div>
                                <label class="mb-1 block text-sm">@lang('marketing_automation::app.flows.form.trigger')</label>

                                <select
                                    class="flex w-full rounded border bg-white px-2.5 py-2 text-sm dark:bg-gray-900"
                                    v-model="form.trigger_type"
                                >
                                    <option
                                        v-for="(label, trigger) in triggerLabels"
                                        :key="trigger"
                                        :value="trigger"
                                    >
                                        @{{ label }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label class="mb-1 block text-sm">@lang('marketing_automation::app.flows.form.type')</label>

                                <select
                                    class="flex w-full rounded border bg-white px-2.5 py-2 text-sm dark:bg-gray-900"
                                    v-model="form.type"
                                >
                                    <option value="marketing">@lang('marketing_automation::app.flows.types.marketing')</option>

                                    <option value="transactional">@lang('marketing_automation::app.flows.types.transactional')</option>
                                </select>
                            </div>

                            <div>
                                <label class="mb-1 block text-sm">@lang('marketing_automation::app.flows.form.channels')</label>

                                <select
                                    multiple
                                    class="flex w-full rounded border bg-white px-2.5 py-2 text-sm dark:bg-gray-900"
                                    v-model="form.channel_ids"
                                >
                                    <option
                                        v-for="channel in channels"
                                        :key="channel.id"
                                        :value="channel.id"
                                    >
                                        @{{ channel.name }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label class="mb-1 block text-sm">@lang('marketing_automation::app.flows.form.reentry-window')</label>

                                <input
                                    type="number"
                                    class="flex w-full rounded border bg-white px-2.5 py-2 text-sm dark:bg-gray-900"
                                    v-model="form.reentry_window_hours"
                                >
                            </div>

                            <div class="flex gap-2.5">
                                <div
                                    class="primary-button"
                                    @click="create"
                                >
                                    @lang('marketing_automation::app.flows.form.save')
                                </div>
                            </div>
                        </div>
                    </x-slot>
                </x-admin::drawer>
            </div>
        </script>

        <script type="module">
            app.component('v-flows', {
                template: '#v-flows-template',

                data() {
                    return {
                        templates: [],

                        triggerLabels: @js($triggerLabels),

                        channels: @json($channelOptions),

                        form: {
                            template_key: '',
                            name: '',
                            trigger_type: 'customer_registered',
                            type: 'marketing',
                            channel_ids: @json(collect($channelOptions)->pluck('id')->values()),
                            reentry_window_hours: 168,
                        },

                        messages: {
                            deleted:    @js(trans('marketing_automation::app.flows.deleted')),
                            duplicated: @js(trans('marketing_automation::app.flows.duplicated')),
                        },
                    };
                },

                computed: {
                    gridsCount() {
                        let count = this.$refs.datagrid.available.columns.length;

                        if (this.$refs.datagrid.available.actions.length) {
                            ++count;
                        }

                        if (this.$refs.datagrid.available.massActions.length) {
                            ++count;
                        }

                        return count;
                    },

                    selectedTemplate() {
                        return this.templates.find((template) => template.key === this.form.template_key);
                    },
                },

                watch: {
                    /**
                     * A template brings its own trigger and type; the name follows unless typed by hand.
                     */
                    'form.template_key'(key, previousKey) {
                        const template = this.templates.find((candidate) => candidate.key === key);

                        if (! template) {
                            return;
                        }

                        const previous = this.templates.find((candidate) => candidate.key === previousKey);

                        this.form.trigger_type = template.trigger_type;

                        this.form.type = template.type;

                        if (! this.form.name || this.form.name === previous?.name) {
                            this.form.name = template.name;
                        }
                    },
                },

                created() {
                    this.$axios.get("{{ route('admin.marketing.automation.flows.templates') }}")
                        .then((response) => {
                            this.templates = response.data.data;
                        });
                },

                methods: {
                    openCreate() {
                        this.$refs.createDrawer.open();
                    },

                    create() {
                        this.$axios.post("{{ route('admin.marketing.automation.flows.store') }}", this.form)
                            .then((response) => {
                                this.$refs.createDrawer.close();

                                window.location.href = "{{ route('admin.marketing.automation.flows.edit', ':id') }}".replace(':id', response.data.data.id);
                            })
                            .catch((error) => {
                                this.$emitter.emit('add-flash', { type: 'error', message: this.firstError(error) });
                            });
                    },

                    edit(record) {
                        window.location.href = "{{ route('admin.marketing.automation.flows.edit', ':id') }}".replace(':id', record.id);
                    },

                    duplicate(record) {
                        this.$axios.post("{{ route('admin.marketing.automation.flows.duplicate', ':id') }}".replace(':id', record.id))
                            .then(() => {
                                this.$emitter.emit('add-flash', { type: 'success', message: this.messages.duplicated });

                                this.$refs.datagrid.get();
                            })
                            .catch((error) => {
                                this.$emitter.emit('add-flash', { type: 'error', message: this.firstError(error) });
                            });
                    },

                    remove(record) {
                        this.$emitter.emit('open-confirm-modal', {
                            agree: () => {
                                this.$axios.delete("{{ route('admin.marketing.automation.flows.delete', ':id') }}".replace(':id', record.id))
                                    .then(() => {
                                        this.$emitter.emit('add-flash', { type: 'success', message: this.messages.deleted });

                                        this.$refs.datagrid.get();
                                    })
                                    .catch((error) => {
                                        this.$emitter.emit('add-flash', { type: 'error', message: this.firstError(error) });
                                    });
                            },
                        });
                    },

                    firstError(error) {
                        const errors = error.response?.data?.errors;

                        return errors ? Object.values(errors)[0][0] : error.response?.data?.message;
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
