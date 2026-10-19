<x-admin::layouts>
    <x-slot:title>
        @lang('marketing_automation::app.providers.index.title')
    </x-slot>

    <div class="ma-root">
        <v-providers
            :sms-drivers='@json($smsDrivers)'
            :whatsapp-drivers='@json($whatsappDrivers)'
            :channels='@json($channels)'
            :initial-defaults='@json($defaults)'
            :connections='@json($connections)'
        >
            <div class="flex justify-between gap-4 max-sm:flex-wrap">
                <p class="text-xl font-bold text-gray-800 dark:text-white">
                    @lang('marketing_automation::app.providers.index.title')
                </p>

                <div class="flex items-center gap-x-2.5">
                    <a
                        href="{{ route('admin.marketing.automation.health.index') }}"
                        class="secondary-button"
                    >
                        @lang('marketing_automation::app.providers.index.health')
                    </a>

                    <div
                        class="primary-button"
                        @click="openForm()"
                    >
                        @lang('marketing_automation::app.providers.index.create-btn')
                    </div>
                </div>
            </div>

            <x-admin::shimmer.datagrid />
        </v-providers>

        @pushOnce('scripts')
            <script
                type="text/x-template"
                id="v-providers-template"
            >
                <div>
                    <div class="flex justify-between gap-4 max-sm:flex-wrap">
                        <p class="text-xl font-bold text-gray-800 dark:text-white">
                            @lang('marketing_automation::app.providers.index.title')
                        </p>

                        <div class="flex items-center gap-x-2.5">
                            <a
                                href="{{ route('admin.marketing.automation.health.index') }}"
                                class="secondary-button"
                            >
                                @lang('marketing_automation::app.providers.index.health')
                            </a>

                            <div
                                class="primary-button"
                                @click="openForm()"
                            >
                                @lang('marketing_automation::app.providers.index.create-btn')
                            </div>
                        </div>
                    </div>

                    <x-admin::datagrid
                        src="{{ route('admin.marketing.automation.providers.index') }}"
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

                                    <p>@{{ channelLabel(record.message_channel) }}</p>

                                    <p>@{{ record.driver }}</p>

                                    <p>@{{ record.sender }}</p>

                                    <p><span v-html="record.status"></span></p>

                                    <p>@{{ record.last_tested_at }}</p>

                                    <div class="flex gap-2.5">
                                        <span
                                            :title="@js(trans('marketing_automation::app.providers.datagrid.test'))"
                                            class="icon-mail cursor-pointer rounded-md p-1.5 text-2xl transition-all hover:bg-gray-200 dark:hover:bg-gray-800"
                                            @click="openTestModal(record)"
                                        ></span>

                                        <span
                                            v-if="record.message_channel === 'whatsapp'"
                                            :title="@js(trans('marketing_automation::app.providers.datagrid.sync'))"
                                            class="icon-repeat cursor-pointer rounded-md p-1.5 text-2xl transition-all hover:bg-gray-200 dark:hover:bg-gray-800"
                                            @click="syncTemplates(record)"
                                        ></span>

                                        <span
                                            :title="@js(trans('marketing_automation::app.providers.datagrid.edit'))"
                                            class="icon-edit cursor-pointer rounded-md p-1.5 text-2xl transition-all hover:bg-gray-200 dark:hover:bg-gray-800"
                                            @click="edit(record)"
                                        ></span>

                                        <span
                                            :title="@js(trans('marketing_automation::app.providers.datagrid.delete'))"
                                            class="icon-delete cursor-pointer rounded-md p-1.5 text-2xl transition-all hover:bg-gray-200 dark:hover:bg-gray-800"
                                            @click="remove(record)"
                                        ></span>
                                    </div>
                                </div>
                            </template>
                        </template>
                    </x-admin::datagrid>

                    <!-- Defaults table -->
                    <div class="mt-6">
                        <p class="mb-2 text-base font-semibold text-gray-800 dark:text-white">
                            @lang('marketing_automation::app.providers.defaults.title')
                        </p>

                        <table class="w-full border rounded bg-white dark:bg-gray-900">
                            <thead>
                                <tr class="border-b dark:border-gray-800">
                                    <th class="p-3 text-start">@lang('marketing_automation::app.providers.defaults.channel')</th>
                                    <th class="p-3 text-start">@lang('marketing_automation::app.providers.channels.sms')</th>
                                    <th class="p-3 text-start">@lang('marketing_automation::app.providers.channels.whatsapp')</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="channel in channels"
                                    :key="channel.id"
                                    class="border-b last:border-0 dark:border-gray-800"
                                >
                                    <td class="p-3">@{{ channel.name }}</td>

                                    <td class="p-3">
                                        <select
                                            class="flex w-[160px] rounded border bg-white px-2 py-1.5 text-sm dark:bg-gray-900"
                                            v-model="defaults[channel.id + ':sms']"
                                        >
                                            <option value="">@lang('marketing_automation::app.providers.defaults.none')</option>

                                            <option
                                                v-for="connection in connectionsByChannel('sms')"
                                                :key="connection.id"
                                                :value="connection.id"
                                            >
                                                @{{ connection.name }}
                                            </option>
                                        </select>
                                    </td>

                                    <td class="p-3">
                                        <select
                                            class="flex w-[160px] rounded border bg-white px-2 py-1.5 text-sm dark:bg-gray-900"
                                            v-model="defaults[channel.id + ':whatsapp']"
                                        >
                                            <option value="">@lang('marketing_automation::app.providers.defaults.none')</option>

                                            <option
                                                v-for="connection in connectionsByChannel('whatsapp')"
                                                :key="connection.id"
                                                :value="connection.id"
                                            >
                                                @{{ connection.name }}
                                            </option>
                                        </select>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <div
                            class="primary-button mt-3"
                            @click="saveDefaults"
                        >
                            @lang('marketing_automation::app.providers.defaults.save')
                        </div>
                    </div>

                    <!-- Create / edit drawer -->
                    <x-admin::drawer ref="providerDrawer">
                        <x-slot:header>
                            <p class="text-base font-semibold">
                                @{{ editing ? messages.editTitle : messages.createTitle }}
                            </p>
                        </x-slot>

                        <x-slot:content>
                            <div class="grid gap-4">
                                <div>
                                    <label class="mb-1 block text-sm">@lang('marketing_automation::app.providers.form.name')</label>

                                    <input
                                        type="text"
                                        class="flex w-full rounded border bg-white px-2.5 py-2 text-sm dark:bg-gray-900"
                                        v-model="form.name"
                                    />
                                </div>

                                <div>
                                    <label class="mb-1 block text-sm">@lang('marketing_automation::app.providers.form.channel')</label>

                                    <select
                                        class="flex w-full rounded border bg-white px-2.5 py-2 text-sm dark:bg-gray-900"
                                        v-model="form.message_channel"
                                        :disabled="editing"
                                    >
                                        <option value="sms">@lang('marketing_automation::app.providers.channels.sms')</option>

                                        <option value="whatsapp">@lang('marketing_automation::app.providers.channels.whatsapp')</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="mb-1 block text-sm">@lang('marketing_automation::app.providers.form.driver')</label>

                                    <select
                                        class="flex w-full rounded border bg-white px-2.5 py-2 text-sm dark:bg-gray-900"
                                        v-model="form.driver"
                                        :disabled="editing"
                                    >
                                        <option
                                            v-for="driver in driversForChannel"
                                            :key="driver.driver"
                                            :value="driver.driver"
                                        >
                                            @{{ driver.label }}
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <label class="mb-1 block text-sm">@lang('marketing_automation::app.providers.form.sender')</label>

                                    <input
                                        type="text"
                                        class="flex w-full rounded border bg-white px-2.5 py-2 text-sm dark:bg-gray-900"
                                        v-model="form.sender"
                                    />
                                </div>

                                <div
                                    v-for="field in selectedDriverFields"
                                    :key="field.name"
                                >
                                    <label class="mb-1 block text-sm">
                                        @{{ field.label }}

                                        <span
                                            v-if="field.required"
                                            class="text-red-500"
                                        >*</span>
                                    </label>

                                    <input
                                        :type="field.type === 'password' ? 'password' : 'text'"
                                        class="flex w-full rounded border bg-white px-2.5 py-2 text-sm dark:bg-gray-900"
                                        :placeholder="field.secret && editing ? @js('••••••••') : ''"
                                        v-model="form.credentials[field.name]"
                                    />
                                </div>

                                <div v-if="editing && webhook.url">
                                    <label class="mb-1 block text-sm">@lang('marketing_automation::app.providers.form.webhook-url')</label>

                                    <div class="flex gap-2">
                                        <input
                                            type="text"
                                            class="flex w-full rounded border bg-gray-50 px-2.5 py-2 text-sm dark:bg-gray-800"
                                            :value="webhook.url"
                                            readonly
                                            @focus="$event.target.select()"
                                        />

                                        <span
                                            class="secondary-button"
                                            @click="copy(webhook.url)"
                                        >
                                            @lang('marketing_automation::app.providers.form.copy')
                                        </span>
                                    </div>

                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        @lang('marketing_automation::app.providers.form.webhook-url-info')
                                    </p>
                                </div>

                                <div v-if="editing && webhook.verifyToken">
                                    <label class="mb-1 block text-sm">@lang('marketing_automation::app.providers.form.verify-token')</label>

                                    <div class="flex gap-2">
                                        <input
                                            type="text"
                                            class="flex w-full rounded border bg-gray-50 px-2.5 py-2 text-sm dark:bg-gray-800"
                                            :value="webhook.verifyToken"
                                            readonly
                                            @focus="$event.target.select()"
                                        />

                                        <span
                                            class="secondary-button"
                                            @click="copy(webhook.verifyToken)"
                                        >
                                            @lang('marketing_automation::app.providers.form.copy')
                                        </span>
                                    </div>

                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        @lang('marketing_automation::app.providers.form.verify-token-info')
                                    </p>
                                </div>

                                <div class="flex gap-2.5">
                                    <div
                                        class="primary-button"
                                        @click="save"
                                    >
                                        @lang('marketing_automation::app.providers.form.save')
                                    </div>
                                </div>
                            </div>
                        </x-slot>
                    </x-admin::drawer>

                    <!-- Test connection modal -->
                    <x-admin::modal ref="testModal">
                        <x-slot:header>
                            <p class="text-base font-semibold">@lang('marketing_automation::app.providers.test.title')</p>
                        </x-slot>

                        <x-slot:content>
                            <div class="grid gap-3">
                                <p class="text-sm text-gray-600 dark:text-gray-300">
                                    @{{ testing ? testing.name : '' }}
                                </p>

                                <div>
                                    <label class="mb-1 block text-sm">@lang('marketing_automation::app.providers.test.recipient')</label>

                                    <input
                                        type="text"
                                        class="flex w-full rounded border bg-white px-2.5 py-2 text-sm dark:bg-gray-900"
                                        v-model="testRecipient"
                                    />
                                </div>
                            </div>
                        </x-slot>

                        <x-slot:footer>
                            <div
                                class="primary-button"
                                @click="test"
                            >
                                @lang('marketing_automation::app.providers.test.send')
                            </div>
                        </x-slot>
                    </x-admin::modal>
                </div>
            </script>

            <script type="module">
                app.component('v-providers', {
                    template: '#v-providers-template',

                    props: ['smsDrivers', 'whatsappDrivers', 'channels', 'connections', 'initialDefaults'],

                    data() {
                        return {
                            form: {
                                id: null,
                                name: '',
                                message_channel: 'sms',
                                driver: 'twilio',
                                sender: '',
                                credentials: {},
                            },

                            defaults: { ...this.initialDefaults },

                            testing: null,

                            testRecipient: '',

                            webhook: {
                                url: null,
                                verifyToken: null,
                            },

                            messages: {
                                createTitle:  @js(trans('marketing_automation::app.providers.index.create-btn')),
                                editTitle:    @js(trans('marketing_automation::app.providers.index.edit-btn')),
                                copied:       @js(trans('marketing_automation::app.providers.form.copied')),
                                saved:        @js(trans('marketing_automation::app.providers.saved')),
                                deleted:      @js(trans('marketing_automation::app.providers.deleted')),
                                defaultsUpd:  @js(trans('marketing_automation::app.providers.defaults-updated')),
                                sms:          @js(trans('marketing_automation::app.providers.channels.sms')),
                                whatsapp:     @js(trans('marketing_automation::app.providers.channels.whatsapp')),
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

                        editing() {
                            return this.form.id !== null;
                        },

                        driversForChannel() {
                            return this.form.message_channel === 'sms' ? this.smsDrivers : this.whatsappDrivers;
                        },

                        selectedDriverFields() {
                            const driver = this.driversForChannel.find((d) => d.driver === this.form.driver);

                            return driver ? driver.fields : [];
                        },
                    },
                    methods: {
                        channelLabel(channel) {
                            return channel === 'whatsapp' ? this.messages.whatsapp : this.messages.sms;
                        },
                        openForm() {
                            this.form = {
                                id: null,
                                name: '',
                                message_channel: 'sms',
                                driver: this.driversForChannel[0]?.driver,
                                sender: '',
                                credentials: {},
                            };

                            this.webhook = { url: null, verifyToken: null };

                            this.$refs.providerDrawer.open();
                        },

                        edit(record) {
                            this.$axios.get("{{ route('admin.marketing.automation.providers.show', ':id') }}".replace(':id', record.id))
                                .then((response) => {
                                    const data = response.data.data;

                                    const fields = (data.message_channel === 'sms' ? this.smsDrivers : this.whatsappDrivers)
                                        .find((driver) => driver.driver === data.driver)?.fields ?? [];

                                    const credentials = {};

                                    fields.forEach((field) => {
                                        credentials[field.name] = field.secret ? '' : (data.credentials[field.name] ?? '');
                                    });

                                    this.form = {
                                        id: data.id,
                                        name: data.name,
                                        message_channel: data.message_channel,
                                        driver: data.driver,
                                        sender: data.sender,
                                        credentials,
                                    };

                                    this.webhook = {
                                        url: data.webhook_url ?? null,
                                        verifyToken: data.verify_token ?? null,
                                    };

                                    this.$refs.providerDrawer.open();
                                })
                                .catch((error) => {
                                    this.$emitter.emit('add-flash', { type: 'error', message: this.firstError(error) });
                                });
                        },

                        save() {
                            const payload = { ...this.form };

                            const request = this.editing
                                ? this.$axios.put("{{ route('admin.marketing.automation.providers.update', ':id') }}".replace(':id', this.form.id), payload)
                                : this.$axios.post("{{ route('admin.marketing.automation.providers.store') }}", payload);

                            request.then(() => {
                                this.$emitter.emit('add-flash', { type: 'success', message: this.messages.saved });

                                this.$refs.providerDrawer.close();

                                this.$refs.datagrid.get();
                            }).catch((error) => {
                                this.$emitter.emit('add-flash', { type: 'error', message: this.firstError(error) });
                            });
                        },

                        openTestModal(record) {
                            this.testing = record;

                            this.testRecipient = '';

                            this.$refs.testModal.toggle();
                        },

                        test() {
                            this.$axios.post("{{ route('admin.marketing.automation.providers.test', ':id') }}".replace(':id', this.testing.id), { to: this.testRecipient })
                                .then((response) => {
                                    this.$refs.testModal.toggle();

                                    this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });

                                    this.$refs.datagrid.get();
                                })
                                .catch((error) => {
                                    this.$emitter.emit('add-flash', { type: 'error', message: this.firstError(error) });
                                });
                        },

                        syncTemplates(record) {
                            this.$axios.post("{{ route('admin.marketing.automation.providers.sync_templates', ':id') }}".replace(':id', record.id))
                                .then((response) => {
                                    this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                                })
                                .catch((error) => {
                                    this.$emitter.emit('add-flash', { type: 'error', message: this.firstError(error) });
                                });
                        },

                        remove(record) {
                            this.$emitter.emit('open-confirm-modal', {
                                agree: () => {
                                    this.$axios.delete("{{ route('admin.marketing.automation.providers.delete', ':id') }}".replace(':id', record.id))
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

                        saveDefaults() {
                            const keys = new Set([...Object.keys(this.initialDefaults ?? {}), ...Object.keys(this.defaults)]);

                            Promise.all([...keys].map((key) => {
                                const [channelId, messageChannel] = key.split(':');

                                const connectionId = this.defaults[key];

                                return this.$axios.put("{{ route('admin.marketing.automation.providers.defaults') }}", {
                                    channel_id: channelId,
                                    message_channel: messageChannel,
                                    provider_connection_id: connectionId ? connectionId : null,
                                });
                            })).then(() => {
                                this.$emitter.emit('add-flash', { type: 'success', message: this.messages.defaultsUpd });
                            }).catch((error) => {
                                this.$emitter.emit('add-flash', { type: 'error', message: this.firstError(error) });
                            });
                        },

                        copy(value) {
                            navigator.clipboard?.writeText(value).then(() => {
                                this.$emitter.emit('add-flash', { type: 'success', message: this.messages.copied });
                            });
                        },

                        connectionsByChannel(channel) {
                            return this.connections.filter((connection) => connection.message_channel === channel);
                        },

                        firstError(error) {
                            const errors = error.response?.data?.errors;

                            return errors ? Object.values(errors)[0][0] : error.response?.data?.message;
                        },
                    },
                });
            </script>
        @endPushOnce
    </div>
</x-admin::layouts>
