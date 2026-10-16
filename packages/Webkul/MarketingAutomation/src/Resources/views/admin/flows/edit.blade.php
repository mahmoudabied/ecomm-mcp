<x-admin::layouts>
    <x-slot:title>
        {{ $flow->name }}
    </x-slot>

    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-xl font-bold">
            {{ $flow->name }}
        </h1>

        @if (bouncer()->hasPermission('marketing.automation.reports'))
            <a
                href="{{ route('admin.marketing.automation.flows.report', $flow->id) }}"
                class="secondary-button"
            >
                @lang('marketing_automation::app.reports.title')
            </a>
        @endif
    </div>

    <v-flow-builder
        :flow='@json($builderFlow)'
        :definition='@json($builderDefinition)'
        :whatsapp-templates='@json($builderTemplates)'
        :channels='@json($builderChannels)'
        :trigger-labels='@json($triggerLabels)'
    >
        <x-admin::shimmer.datagrid />
    </v-flow-builder>

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-flow-builder-template"
        >
            <div class="grid gap-6">
                <!-- Settings panel -->
                <div class="rounded border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm">@lang('marketing_automation::app.flows.form.name')</label>

                            <input
                                type="text"
                                class="flex w-full rounded border bg-white px-2.5 py-2 text-sm dark:bg-gray-900"
                                v-model="flow.name"
                            >
                        </div>

                        <div>
                            <label class="mb-1 block text-sm">@lang('marketing_automation::app.flows.form.trigger')</label>

                            <select
                                class="flex w-full rounded border bg-white px-2.5 py-2 text-sm dark:bg-gray-900"
                                v-model="flow.trigger_type"
                                :disabled="flow.status === 'active'"
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
                                v-model="flow.type"
                                :disabled="flow.status === 'active'"
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
                                v-model="flow.channel_ids"
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
                                v-model="flow.reentry_window_hours"
                            >
                        </div>

                        <div class="flex items-end">
                            <label class="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    v-model="exitOnOrder"
                                >

                                @lang('marketing_automation::app.flows.form.exit-goal')
                            </label>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2.5">
                        <div
                            class="primary-button"
                            @click="save"
                        >
                            @lang('marketing_automation::app.flows.form.save')
                        </div>

                        <div
                            class="secondary-button"
                            @click="activate"
                        >
                            @lang('marketing_automation::app.flows.form.activate')
                        </div>

                        <div
                            v-if="flow.status === 'active'"
                            class="secondary-button"
                            @click="pause"
                        >
                            @lang('marketing_automation::app.flows.form.pause')
                        </div>

                        <div
                            v-if="flow.status === 'paused'"
                            class="secondary-button"
                            @click="resume"
                        >
                            @lang('marketing_automation::app.flows.form.resume')
                        </div>
                    </div>

                    <p
                        v-if="globalError"
                        class="mt-3 rounded bg-red-50 p-2 text-sm text-red-600 dark:bg-red-900/20"
                    >
                        @{{ globalError }}
                    </p>

                    <ul
                        v-if="generalErrors.length"
                        class="mt-2 list-disc text-sm text-red-600 ltr:pl-5 rtl:pr-5"
                    >
                        <li
                            v-for="message in generalErrors"
                            :key="message"
                        >
                            @{{ message }}
                        </li>
                    </ul>
                </div>

                <!-- Step canvas: vertical list -->
                <div class="rounded border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <v-flow-steps
                        :start-key="def.entry"
                        :definition="def"
                        :errors="errors"
                        :depth="0"
                        :trail="[]"
                        @edit="openStep"
                        @add="addStep"
                        @remove="removeStep"
                        @move-up="moveUp"
                        @move-down="moveDown"
                        @test="openTest"
                        @preview="preview"
                    ></v-flow-steps>
                </div>

                <!-- Step drawer -->
                <x-admin::drawer ref="stepDrawer">
                    <x-slot:header>
                        <p class="text-base font-semibold text-gray-800 dark:text-white">
                            @{{ editingKey }} · @{{ stepLabel(editingKey) }}
                        </p>
                    </x-slot>

                    <x-slot:content>
                        <div
                            v-if="editing"
                            class="grid gap-4"
                        >
                            <!-- wait -->
                            <template v-if="editing.type === 'wait'">
                                <div>
                                    <label class="mb-1 block text-sm">@lang('marketing_automation::app.flows.steps.wait')</label>

                                    <select
                                        class="flex w-full rounded border bg-white px-2.5 py-2 text-sm dark:bg-gray-900"
                                        v-model="editing.mode"
                                    >
                                        <option value="duration">@lang('marketing_automation::app.flows.steps.duration')</option>

                                        <option value="until_time">@lang('marketing_automation::app.flows.steps.until-time')</option>

                                        <option value="until_weekday">@lang('marketing_automation::app.flows.steps.until-weekday')</option>
                                    </select>
                                </div>

                                <div v-if="editing.mode === 'duration'">
                                    <label class="mb-1 block text-sm">@lang('marketing_automation::app.flows.steps.duration')</label>

                                    <input
                                        type="number"
                                        min="1"
                                        class="flex w-full rounded border bg-white px-2.5 py-2 text-sm dark:bg-gray-900"
                                        v-model.number="editing.minutes"
                                    >
                                </div>

                                <div v-else>
                                    <label class="mb-1 block text-sm">@lang('marketing_automation::app.flows.steps.until-time')</label>

                                    <input
                                        type="time"
                                        class="flex w-full rounded border bg-white px-2.5 py-2 text-sm dark:bg-gray-900"
                                        v-model="editing.time"
                                    >
                                </div>

                                <div
                                    v-if="editing.mode === 'until_weekday'"
                                    class="flex flex-wrap gap-3"
                                >
                                    <label
                                        v-for="day in weekdays"
                                        :key="day.value"
                                        class="flex items-center gap-1 text-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            :value="day.value"
                                            v-model="editing.weekdays"
                                        >

                                        @{{ day.label }}
                                    </label>
                                </div>
                            </template>

                            <!-- condition -->
                            <template v-if="editing.type === 'condition'">
                                <div>
                                    <label class="mb-1 block text-sm">@lang('marketing_automation::app.flows.steps.match')</label>

                                    <select
                                        class="flex w-full rounded border bg-white px-2.5 py-2 text-sm dark:bg-gray-900"
                                        v-model="editing.match"
                                    >
                                        <option value="all">@lang('marketing_automation::app.flows.steps.all')</option>

                                        <option value="any">@lang('marketing_automation::app.flows.steps.any')</option>
                                    </select>
                                </div>

                                <div
                                    v-for="(row, index) in editing.rows"
                                    :key="index"
                                    class="flex items-center gap-2"
                                >
                                    <template v-if="row.field !== undefined">
                                        <select
                                            class="flex w-full rounded border bg-white px-2.5 py-2 text-xs dark:bg-gray-900"
                                            v-model="row.field"
                                        >
                                            <option
                                                v-for="field in conditionFields"
                                                :key="field"
                                                :value="field"
                                            >
                                                @{{ field }}
                                            </option>
                                        </select>

                                        <select
                                            class="flex w-full rounded border bg-white px-2.5 py-2 text-xs dark:bg-gray-900"
                                            v-model="row.op"
                                        >
                                            <option
                                                v-for="op in operators"
                                                :key="op"
                                                :value="op"
                                            >
                                                @{{ op }}
                                            </option>
                                        </select>

                                        <input
                                            v-if="! ['is_true', 'is_false'].includes(row.op)"
                                            type="text"
                                            class="flex w-full rounded border bg-white px-2.5 py-2 text-xs dark:bg-gray-900"
                                            v-model="row.value"
                                        >
                                    </template>

                                    <code
                                        v-else
                                        class="w-full truncate rounded bg-gray-100 px-2 py-1 text-xs dark:bg-gray-800"
                                    >
                                        @{{ JSON.stringify(row) }}
                                    </code>

                                    <span
                                        class="icon-delete cursor-pointer rounded-md p-1 text-xl hover:bg-gray-200 dark:hover:bg-gray-800"
                                        @click="editing.rows.splice(index, 1)"
                                    ></span>
                                </div>

                                <div
                                    class="secondary-button"
                                    @click="editing.rows.push({ field: 'order_count', op: 'gte', value: 1 })"
                                >
                                    + @lang('marketing_automation::app.flows.steps.condition')
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div
                                        v-for="branch in ['yes', 'no']"
                                        :key="branch"
                                    >
                                        <label class="mb-1 block text-sm">
                                            @{{ branch === 'yes' ? branchLabels.yes : branchLabels.no }}
                                        </label>

                                        <select
                                            class="flex w-full rounded border bg-white px-2.5 py-2 text-sm dark:bg-gray-900"
                                            v-model="editing[branch]"
                                        >
                                            <option :value="null">—</option>

                                            <option
                                                v-for="key in branchTargets"
                                                :key="key"
                                                :value="key"
                                            >
                                                @{{ key }} · @{{ stepLabel(key) }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </template>

                            <!-- send_email -->
                            <template v-if="editing.type === 'send_email'">
                                <v-flow-email-fields
                                    :content="editing"
                                    :flow-id="flow.id"
                                    :step-key="editingKey"
                                    :template-key="flow.template_key"
                                ></v-flow-email-fields>
                            </template>

                            <!-- send_sms -->
                            <template v-if="editing.type === 'send_sms'">
                                <v-flow-sms-fields :content="editing"></v-flow-sms-fields>
                            </template>

                            <!-- send_whatsapp -->
                            <template v-if="editing.type === 'send_whatsapp'">
                                <v-flow-whatsapp-fields
                                    :content="editing"
                                    :templates="whatsappTemplates"
                                ></v-flow-whatsapp-fields>
                            </template>

                            <!-- common to all send steps -->
                            <template v-if="editingIsSend">
                                <div>
                                    <label class="mb-1 block text-sm">@lang('marketing_automation::app.flows.steps.validity')</label>

                                    <input
                                        type="number"
                                        min="1"
                                        class="flex w-full rounded border bg-white px-2.5 py-2 text-sm dark:bg-gray-900"
                                        v-model.number="editing.validity_hours"
                                    >
                                </div>

                                <div v-if="fallbackChannels.length">
                                    <label class="mb-1 block text-sm">@lang('marketing_automation::app.flows.steps.fallback')</label>

                                    <select
                                        class="flex w-full rounded border bg-white px-2.5 py-2 text-sm dark:bg-gray-900"
                                        v-model="editing.fallback_channel"
                                        @change="fallbackChannelChanged"
                                    >
                                        <option :value="null">@lang('marketing_automation::app.providers.defaults.none')</option>

                                        <option
                                            v-for="channel in fallbackChannels"
                                            :key="channel"
                                            :value="channel"
                                        >
                                            @{{ channelLabels[channel] }}
                                        </option>
                                    </select>
                                </div>

                                <div
                                    v-if="editing.fallback_channel && editing.fallback_step"
                                    class="grid gap-4 rounded border border-dashed p-3 dark:border-gray-700"
                                >
                                    <p class="text-sm font-semibold">@lang('marketing_automation::app.flows.steps.fallback-body')</p>

                                    <v-flow-email-fields
                                        v-if="editing.fallback_channel === 'email'"
                                        :content="editing.fallback_step"
                                    ></v-flow-email-fields>
                                    <v-flow-sms-fields
                                        v-if="editing.fallback_channel === 'sms'"
                                        :content="editing.fallback_step"
                                    ></v-flow-sms-fields>

                                    <v-flow-whatsapp-fields
                                        v-if="editing.fallback_channel === 'whatsapp'"
                                        :content="editing.fallback_step"
                                        :templates="whatsappTemplates"
                                    ></v-flow-whatsapp-fields>
                                </div>
                            </template>

                            <div
                                class="primary-button justify-center"
                                @click="saveStep"
                            >
                                @lang('marketing_automation::app.flows.form.save')
                            </div>
                        </div>
                    </x-slot>
                </x-admin::drawer>

                <!-- Send test modal -->
                <x-admin::modal ref="testModal">
                    <x-slot:header>
                        <p class="text-lg font-bold text-gray-800 dark:text-white">
                            @lang('marketing_automation::app.flows.form.send-test')
                        </p>
                    </x-slot>

                    <x-slot:content>
                        <div class="grid gap-3">
                            <div>
                                <label class="mb-1 block text-sm">@lang('marketing_automation::app.flows.form.send-test-recipient')</label>

                                <input
                                    type="text"
                                    class="flex w-full rounded border bg-white px-2.5 py-2 text-sm dark:bg-gray-900"
                                    v-model="testForm.recipient"
                                >
                            </div>

                            <div>
                                <label class="mb-1 block text-sm">@lang('marketing_automation::app.flows.form.send-test-locale')</label>

                                <select
                                    class="flex w-full rounded border bg-white px-2.5 py-2 text-sm dark:bg-gray-900"
                                    v-model="testForm.locale"
                                >
                                    <option value="en">en</option>

                                    <option value="ar">ar</option>
                                </select>
                            </div>

                            <div>
                                <label class="mb-1 block text-sm">@lang('marketing_automation::app.flows.form.send-test-sample')</label>

                                <select
                                    class="flex w-full rounded border bg-white px-2.5 py-2 text-sm dark:bg-gray-900"
                                    v-model="testForm.sample"
                                >
                                    <option value="sample">@lang('marketing_automation::app.flows.form.send-test-sample')</option>

                                    <option value="customer">@lang('marketing_automation::app.flows.form.send-test-customer')</option>
                                </select>
                            </div>

                            <div v-if="testForm.sample === 'customer'">
                                <input
                                    type="number"
                                    placeholder="ID"
                                    class="flex w-full rounded border bg-white px-2.5 py-2 text-sm dark:bg-gray-900"
                                    v-model="testForm.customer_id"
                                >
                            </div>
                        </div>
                    </x-slot>

                    <x-slot:footer>
                        <div
                            class="primary-button"
                            @click="sendTest"
                        >
                            @lang('marketing_automation::app.flows.form.send-test-send')
                        </div>
                    </x-slot>
                </x-admin::modal>
            </div>
        </script>

        <!-- Recursive step list -->
        <script
            type="text/x-template"
            id="v-flow-steps-template"
        >
            <div class="grid gap-3">
                <!-- A pointer back to a step already shown on this path: link instead of recursing -->
                <p
                    v-if="isLoop"
                    class="rounded border border-dashed px-3 py-2 text-xs text-gray-500 dark:border-gray-700"
                >
                    ↩ @{{ loopsToLabel.replace(':key', startKey) }}
                </p>

                <template v-else>
                    <div
                        class="rounded border p-3"
                        :class="errors && errors['steps.' + startKey] ? 'border-red-400' : 'border-gray-200 dark:border-gray-800'"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm font-medium text-gray-800 dark:text-white">
                                @{{ startKey }} · @{{ stepLabel(startKey) }}
                            </span>

                            <div class="flex items-center gap-1">
                                <span
                                    v-if="step && step.type.startsWith('send_')"
                                    :title="@js(trans('marketing_automation::app.flows.form.send-test'))"
                                    class="icon-mail cursor-pointer rounded-md p-1.5 text-xl transition-all hover:bg-gray-200 dark:hover:bg-gray-800"
                                    @click="$emit('test', startKey)"
                                ></span>

                                <span
                                    v-if="step && step.type.startsWith('send_')"
                                    :title="@js(trans('marketing_automation::app.flows.preview'))"
                                    class="icon-view cursor-pointer rounded-md p-1.5 text-xl transition-all hover:bg-gray-200 dark:hover:bg-gray-800"
                                    @click="$emit('preview', startKey)"
                                ></span>

                                <span
                                    v-if="step && step.type !== 'exit'"
                                    :title="@js(trans('marketing_automation::app.flows.datagrid.edit'))"
                                    class="icon-edit cursor-pointer rounded-md p-1.5 text-xl transition-all hover:bg-gray-200 dark:hover:bg-gray-800"
                                    @click="$emit('edit', startKey)"
                                ></span>

                                <span
                                    v-if="canMoveUp"
                                    :title="@js(trans('marketing_automation::app.flows.move-up'))"
                                    class="icon-sort-up cursor-pointer rounded-md p-1.5 text-xl transition-all hover:bg-gray-200 dark:hover:bg-gray-800"
                                    @click="$emit('move-up', startKey)"
                                ></span>

                                <span
                                    v-if="canMoveDown"
                                    :title="@js(trans('marketing_automation::app.flows.move-down'))"
                                    class="icon-sort-down cursor-pointer rounded-md p-1.5 text-xl transition-all hover:bg-gray-200 dark:hover:bg-gray-800"
                                    @click="$emit('move-down', startKey)"
                                ></span>

                                <span
                                    v-if="startKey !== definition.entry"
                                    :title="@js(trans('marketing_automation::app.flows.datagrid.delete'))"
                                    class="icon-delete cursor-pointer rounded-md p-1.5 text-xl transition-all hover:bg-gray-200 dark:hover:bg-gray-800"
                                    @click="$emit('remove', startKey)"
                                ></span>
                            </div>
                        </div>

                        <p
                            v-if="summary"
                            class="mt-1 truncate text-xs text-gray-500"
                        >
                            @{{ summary }}
                        </p>

                        <p
                            v-for="message in (errors && errors['steps.' + startKey]) || []"
                            :key="message"
                            class="mt-1 text-xs text-red-500"
                        >
                            @{{ message }}
                        </p>

                        <!-- Condition branches -->
                        <div
                            v-if="step && step.type === 'condition'"
                            class="mt-2 grid gap-3 md:grid-cols-2"
                        >
                            <div
                                v-for="branch in ['yes', 'no']"
                                :key="branch"
                                class="rounded p-2"
                                :class="branch === 'yes' ? 'bg-green-50 dark:bg-green-900/20' : 'bg-red-50 dark:bg-red-900/20'"
                            >
                                <p
                                    class="mb-1 text-xs font-semibold"
                                    :class="branch === 'yes' ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300'"
                                >
                                    @{{ branch === 'yes' ? branchLabels.yes : branchLabels.no }}
                                </p>

                                <v-flow-steps
                                    v-if="step[branch]"
                                    :start-key="step[branch]"
                                    :definition="definition"
                                    :errors="errors"
                                    :depth="depth + 1"
                                    :trail="childTrail"
                                    @edit="$emit('edit', $event)"
                                    @add="$emit('add', $event)"
                                    @remove="$emit('remove', $event)"
                                    @move-up="$emit('move-up', $event)"
                                    @move-down="$emit('move-down', $event)"
                                    @test="$emit('test', $event)"
                                    @preview="$emit('preview', $event)"
                                ></v-flow-steps>

                                <select
                                    v-else
                                    class="flex w-full rounded border bg-white px-2 py-1 text-xs dark:bg-gray-900"
                                    @change="$emit('add', { after: startKey, branch, type: $event.target.value }); $event.target.value = ''"
                                >
                                    <option value="">+ @lang('marketing_automation::app.flows.form.add-step')</option>

                                    <option
                                        v-for="(label, type) in stepTypeLabels"
                                        :key="type"
                                        :value="type"
                                    >
                                        @{{ label }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Add step after this one -->
                        <select
                            v-if="step && step.type !== 'condition' && step.type !== 'exit'"
                            class="mt-2 flex w-full rounded border bg-white px-2 py-1 text-xs dark:bg-gray-900"
                            @change="$emit('add', { after: startKey, type: $event.target.value }); $event.target.value = ''"
                        >
                            <option value="">+ @lang('marketing_automation::app.flows.form.add-step')</option>

                            <option
                                v-for="(label, type) in stepTypeLabels"
                                :key="type"
                                :value="type"
                            >
                                @{{ label }}
                            </option>
                        </select>
                    </div>

                    <v-flow-steps
                        v-if="nextKey && step.type !== 'condition'"
                        :start-key="nextKey"
                        :definition="definition"
                        :errors="errors"
                        :depth="depth"
                        :trail="childTrail"
                        @edit="$emit('edit', $event)"
                        @add="$emit('add', $event)"
                        @remove="$emit('remove', $event)"
                        @move-up="$emit('move-up', $event)"
                        @move-down="$emit('move-down', $event)"
                        @test="$emit('test', $event)"
                        @preview="$emit('preview', $event)"
                    ></v-flow-steps>
                </template>
            </div>
        </script>

        <!-- Email content fields (step or fallback) -->
        <script
            type="text/x-template"
            id="v-flow-email-fields-template"
        >
            <div class="grid gap-4">
                <div
                    v-for="locale in locales"
                    :key="locale"
                >
                    <label class="mb-1 block text-sm">@lang('marketing_automation::app.flows.steps.subject') (@{{ locale }})</label>

                    <input
                        type="text"
                        class="flex w-full rounded border bg-white px-2.5 py-2 text-sm dark:bg-gray-900"
                        v-model="content.subject[locale]"
                    >
                </div>

                <v-automation-email-editor
                    :content="content"
                    :flow-id="flowId"
                    :step-key="stepKey"
                    :template-key="templateKey"
                ></v-automation-email-editor>
            </div>
        </script>

        <!-- SMS content fields (step or fallback) -->
        <script
            type="text/x-template"
            id="v-flow-sms-fields-template"
        >
            <div class="grid gap-4">
                <div
                    v-for="locale in locales"
                    :key="locale"
                >
                    <label class="mb-1 block text-sm">@lang('marketing_automation::app.flows.steps.body') (@{{ locale }})</label>

                    <textarea
                        rows="3"
                        class="flex w-full rounded border bg-white px-2.5 py-2 text-sm dark:bg-gray-900"
                        v-model="content.body[locale]"
                    ></textarea>

                    <p class="mt-1 text-xs text-gray-500">
                        @{{ charCount(content.body[locale]) }}
                    </p>
                </div>
            </div>
        </script>

        <!-- WhatsApp content fields (step or fallback) -->
        <script
            type="text/x-template"
            id="v-flow-whatsapp-fields-template"
        >
            <div class="grid gap-4">
                <div>
                    <label class="mb-1 block text-sm">@lang('marketing_automation::app.flows.steps.template')</label>

                    <select
                        class="flex w-full rounded border bg-white px-2.5 py-2 text-sm dark:bg-gray-900"
                        v-model="content.template_id"
                    >
                        <option :value="null">@lang('marketing_automation::app.providers.defaults.none')</option>

                        <option
                            v-for="template in templates"
                            :key="template.id"
                            :value="template.id"
                        >
                            @{{ template.name }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm">@lang('marketing_automation::app.flows.steps.variables') (1)</label>

                    <input
                        type="text"
                        :placeholder="placeholder"
                        class="flex w-full rounded border bg-white px-2.5 py-2 text-sm dark:bg-gray-900"
                        v-model="content.variables[1]"
                    >
                </div>
            </div>
        </script>

        <script type="module">
            const flowLocales = ['en', 'ar'];

            const flowStepTypeLabels = @js([
                'wait'          => trans('marketing_automation::app.flows.steps.wait'),
                'condition'     => trans('marketing_automation::app.flows.steps.condition'),
                'send_email'    => trans('marketing_automation::app.flows.steps.send_email'),
                'send_sms'      => trans('marketing_automation::app.flows.steps.send_sms'),
                'send_whatsapp' => trans('marketing_automation::app.flows.steps.send_whatsapp'),
                'exit'          => trans('marketing_automation::app.flows.steps.exit'),
            ]);

            const flowBranchLabels = @js([
                'yes' => trans('marketing_automation::app.flows.form.yes-branch'),
                'no'  => trans('marketing_automation::app.flows.form.no-branch'),
            ]);

            /**
             * Content shapes per message channel, used for steps and their fallbacks.
             */
            const blankContent = {
                email:    () => ({ subject: { en: '', ar: '' }, blocks: { en: [{ type: 'text', text: '' }], ar: [{ type: 'text', text: '' }] }, validity_hours: 24 }),
                sms:      () => ({ body: { en: '', ar: '' }, validity_hours: 24 }),
                whatsapp: () => ({ template_id: null, variables: {}, language: { en: 'en_US', ar: 'ar' }, validity_hours: 24 }),
            };

            /**
             * Fill missing keys of an email, SMS or WhatsApp content object in place.
             */
            const normalizeContent = (channel, content) => {
                const blank = blankContent[channel]();

                for (const [key, value] of Object.entries(blank)) {
                    content[key] ??= value;
                }

                if (channel === 'email') {
                    for (const locale of flowLocales) {
                        content.subject[locale] ??= '';
                        content.blocks[locale] ??= [];

                        if (! content.blocks[locale].some((block) => block.type === 'text')) {
                            content.blocks[locale].unshift({ type: 'text', text: '' });
                        }
                    }
                }

                if (channel === 'sms') {
                    for (const locale of flowLocales) {
                        content.body[locale] ??= '';
                    }
                }

                if (channel === 'whatsapp' && Array.isArray(content.variables)) {
                    content.variables = { ...content.variables };
                }

                return content;
            };

            app.component('v-flow-email-fields', {
                template: '#v-flow-email-fields-template',

                props: ['content', 'flowId', 'stepKey', 'templateKey'],

                data() {
                    return { locales: flowLocales };
                },
            });

            app.component('v-flow-sms-fields', {
                template: '#v-flow-sms-fields-template',

                props: ['content'],

                data() {
                    return { locales: flowLocales };
                },

                methods: {
                    charCount(text) {
                        const value = text ?? '';

                        return /[؀-ۿ]/.test(value)
                            ? ''.concat(value.length, ' / 70 (UCS-2)')
                            : ''.concat(value.length, ' / 160 (GSM)');
                    },
                },
            });

            app.component('v-flow-whatsapp-fields', {
                template: '#v-flow-whatsapp-fields-template',

                props: ['content', 'templates'],

                data() {
                    return {
                        // Built by concatenation so Blade and Vue leave the Liquid braces alone.
                        placeholder: '{' + '{ customer.first_name }' + '}',
                    };
                },
            });

            app.component('v-flow-steps', {
                template: '#v-flow-steps-template',

                props: ['startKey', 'definition', 'errors', 'depth', 'trail'],

                data() {
                    return {
                        stepTypeLabels: flowStepTypeLabels,

                        branchLabels: flowBranchLabels,

                        loopsToLabel: @js(trans('marketing_automation::app.flows.loops-to')),
                    };
                },

                computed: {
                    step() {
                        return this.definition.steps[this.startKey] ?? null;
                    },

                    isLoop() {
                        return (this.trail ?? []).includes(this.startKey);
                    },

                    childTrail() {
                        return [...(this.trail ?? []), this.startKey];
                    },

                    nextKey() {
                        return this.step?.next ?? null;
                    },

                    summary() {
                        const step = this.step;

                        if (! step) {
                            return '';
                        }

                        switch (step.type) {
                            case 'wait':
                                if ((step.mode ?? 'duration') === 'duration') {
                                    return ''.concat(step.minutes ?? 0, ' min');
                                }

                                return [step.mode, step.time, (step.weekdays ?? []).join(',')].filter(Boolean).join(' · ');

                            case 'condition':
                                return Object.entries(step.rule ?? {})
                                    .map(([match, rows]) => match.concat(': ', rows.map((row) => [row.field, row.op, row.value].filter((part) => part !== undefined).join(' ')).join(', ')))
                                    .join(' ');

                            case 'send_email':
                                return step.subject?.en ?? '';

                            case 'send_sms':
                                return step.body?.en ?? '';

                            default:
                                return '';
                        }
                    },

                    canMoveUp() {
                        return this.depth === 0
                            && this.step
                            && this.step.type !== 'condition'
                            && this.findParentOf(this.startKey) !== null;
                    },

                    canMoveDown() {
                        return this.depth === 0
                            && this.step
                            && this.step.type !== 'condition'
                            && (this.step.next ?? null) !== null
                            && this.definition.steps[this.step.next]?.type !== 'condition';
                    },
                },

                methods: {
                    stepLabel(key) {
                        const type = this.definition.steps[key]?.type;

                        return type ? (this.stepTypeLabels[type] ?? type) : '';
                    },

                    findParentOf(key) {
                        for (const [candidateKey, step] of Object.entries(this.definition.steps)) {
                            if ((step.next ?? null) === key
                                || (step.yes ?? null) === key
                                || (step.no ?? null) === key
                            ) {
                                return candidateKey;
                            }
                        }

                        return null;
                    },
                },
            });

            app.component('v-flow-builder', {
                template: '#v-flow-builder-template',

                props: ['flow', 'definition', 'whatsappTemplates', 'channels', 'triggerLabels'],

                data() {
                    const weekdayFormat = new Intl.DateTimeFormat(document.documentElement.lang || 'en', { weekday: 'short' });

                    return {
                        def: JSON.parse(JSON.stringify(this.definition)),

                        editingKey: null,

                        editing: null,

                        errors: {},

                        globalError: '',

                        testForm: {
                            stepKey: null,
                            recipient: '',
                            locale: 'en',
                            sample: 'sample',
                            customer_id: null,
                        },

                        conditionFields: [
                            'order_count', 'lifetime_spend', 'last_order_total',
                            'has_ordered_since_enrolment', 'cart_value', 'locale',
                            'has_consent.email', 'has_consent.sms', 'has_consent.whatsapp',
                        ],

                        operators: ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'is_true', 'is_false'],

                        // ISO weekdays 1 (Monday) to 7 (Sunday); 2024-01-01 was a Monday.
                        weekdays: [1, 2, 3, 4, 5, 6, 7].map((value) => ({
                            value,
                            label: weekdayFormat.format(new Date(2024, 0, value)),
                        })),

                        stepTypeLabels: flowStepTypeLabels,

                        branchLabels: flowBranchLabels,

                        channelLabels: { email: 'Email', sms: 'SMS', whatsapp: 'WhatsApp' },

                        messages: {
                            saved:      @js(trans('marketing_automation::app.flows.saved')),
                            activated:  @js(trans('marketing_automation::app.flows.activated')),
                            paused:     @js(trans('marketing_automation::app.flows.paused')),
                            resumed:    @js(trans('marketing_automation::app.flows.resumed')),
                            testSent:   @js(trans('marketing_automation::app.flows.test.sent')),
                            updateFail: @js(trans('marketing_automation::app.flows.validation-failed')),
                        },
                    };
                },

                computed: {
                    exitOnOrder: {
                        get() {
                            return (this.flow.exit_goals ?? []).some((goal) => goal.type === 'order_placed');
                        },

                        set(value) {
                            const goals = (this.flow.exit_goals ?? []).filter((goal) => goal.type !== 'order_placed');

                            this.flow.exit_goals = value ? [...goals, { type: 'order_placed' }] : goals;
                        },
                    },

                    editingIsSend() {
                        return this.editing !== null && String(this.editing.type).startsWith('send_');
                    },

                    /**
                     * Errors not tied to a single step (shown above the canvas).
                     */
                    generalErrors() {
                        return Object.entries(this.errors)
                            .filter(([key]) => ! key.startsWith('steps.'))
                            .flatMap(([, messages]) => messages);
                    },

                    /**
                     * Branch targets that cannot create a loop: neither the step itself nor any step leading to it.
                     */
                    branchTargets() {
                        const ancestors = this.ancestorsOf(this.editingKey);

                        return Object.keys(this.def.steps)
                            .filter((key) => key !== this.editingKey && ! ancestors.has(key));
                    },

                    fallbackChannels() {
                        const fallbacks = {
                            send_sms:      ['email', 'whatsapp'],
                            send_whatsapp: ['sms', 'email'],
                        };

                        return fallbacks[this.editing?.type] ?? [];
                    },
                },

                methods: {
                    stepLabel(key) {
                        const type = this.def.steps[key]?.type ?? this.editing?.type;

                        return type ? (this.stepTypeLabels[type] ?? type) : '';
                    },

                    flowPayload() {
                        return {
                            name:                 this.flow.name,
                            trigger_type:         this.flow.trigger_type,
                            trigger_config:       this.flow.trigger_config ?? {},
                            type:                 this.flow.type,
                            channel_ids:          this.flow.channel_ids,
                            reentry_window_hours: this.flow.reentry_window_hours,
                            exit_goals:           this.flow.exit_goals ?? [],
                            draft_definition:     this.def,
                        };
                    },

                    requestSucceeded(response, message) {
                        this.globalError = '';

                        this.errors = {};

                        this.$emitter.emit('add-flash', { type: 'success', message });

                        this.flow.status = response.data.data?.status ?? this.flow.status;
                    },

                    requestFailed(error) {
                        if (error.response?.status === 422) {
                            this.errors = error.response.data.errors ?? {};

                            this.globalError = error.response.data.message ?? this.messages.updateFail;

                            return;
                        }

                        this.globalError = error.response?.data?.message ?? error.message;
                    },

                    saveDraft() {
                        return this.$axios.put("{{ route('admin.marketing.automation.flows.update', ':id') }}".replace(':id', this.flow.id), this.flowPayload());
                    },

                    save() {
                        this.saveDraft()
                            .then((response) => this.requestSucceeded(response, this.messages.saved))
                            .catch((error) => this.requestFailed(error));
                    },

                    activate() {
                        // Publish what is on screen, not the last saved draft.
                        this.saveDraft()
                            .then(() => this.$axios.post("{{ route('admin.marketing.automation.flows.activate', ':id') }}".replace(':id', this.flow.id)))
                            .then((response) => this.requestSucceeded(response, this.messages.activated))
                            .catch((error) => this.requestFailed(error));
                    },

                    pause() {
                        this.$axios.post("{{ route('admin.marketing.automation.flows.pause', ':id') }}".replace(':id', this.flow.id))
                            .then((response) => this.requestSucceeded(response, this.messages.paused))
                            .catch((error) => this.requestFailed(error));
                    },

                    resume() {
                        this.$axios.post("{{ route('admin.marketing.automation.flows.resume', ':id') }}".replace(':id', this.flow.id))
                            .then((response) => this.requestSucceeded(response, this.messages.resumed))
                            .catch((error) => this.requestFailed(error));
                    },

                    newKey() {
                        const numbers = Object.keys(this.def.steps)
                            .map((key) => parseInt((key.match(/^s(\d+)$/) ?? [])[1] ?? 0, 10));

                        return 's'.concat(String(Math.max(0, ...numbers) + 1));
                    },

                    blankStep(type) {
                        const steps = {
                            wait:          () => ({ type: 'wait', mode: 'duration', minutes: 60 }),
                            condition:     () => ({ type: 'condition', rule: { all: [{ field: 'order_count', op: 'gte', value: 1 }] }, yes: null, no: null }),
                            send_email:    () => ({ type: 'send_email', ...blankContent.email() }),
                            send_sms:      () => ({ type: 'send_sms', ...blankContent.sms(), fallback_channel: null, fallback_step: null }),
                            send_whatsapp: () => ({ type: 'send_whatsapp', ...blankContent.whatsapp(), fallback_channel: null, fallback_step: null }),
                            exit:          () => ({ type: 'exit' }),
                        };

                        return (steps[type] ?? steps.exit)();
                    },

                    addStep({ after, type, branch = null }) {
                        if (! type) {
                            return;
                        }

                        const key = this.newKey();

                        const step = this.blankStep(type);

                        const parent = this.def.steps[after];

                        if (branch) {
                            parent[branch] = key;
                        } else {
                            if (type !== 'exit' && type !== 'condition') {
                                step.next = parent?.next ?? null;
                            } else if (type === 'condition') {
                                step.yes = parent?.next ?? null;
                            }

                            if (parent && parent.type !== 'condition' && parent.type !== 'exit') {
                                parent.next = key;
                            }
                        }

                        this.def.steps[key] = step;
                    },

                    removeStep(key) {
                        const next = this.def.steps[key]?.next ?? null;

                        if (this.def.entry === key) {
                            this.def.entry = next ?? '';
                        }

                        for (const step of Object.values(this.def.steps)) {
                            for (const pointer of ['next', 'yes', 'no']) {
                                if (step[pointer] === key) {
                                    step[pointer] = next;
                                }
                            }
                        }

                        delete this.def.steps[key];

                        this.pruneUnreachable();
                    },

                    /**
                     * Drop steps nothing points to any more, e.g. the branches of a removed condition.
                     */
                    pruneUnreachable() {
                        const reachable = new Set();

                        const queue = [this.def.entry];

                        while (queue.length) {
                            const key = queue.shift();

                            if (! key || reachable.has(key) || ! this.def.steps[key]) {
                                continue;
                            }

                            reachable.add(key);

                            const step = this.def.steps[key];

                            queue.push(step.next, step.yes, step.no);
                        }

                        for (const key of Object.keys(this.def.steps)) {
                            if (! reachable.has(key)) {
                                delete this.def.steps[key];
                            }
                        }
                    },

                    /**
                     * Every step from which the given step can be reached.
                     */
                    ancestorsOf(key) {
                        const ancestors = new Set();

                        const queue = [key];

                        while (queue.length) {
                            const target = queue.shift();

                            for (const [candidateKey, step] of Object.entries(this.def.steps)) {
                                const pointsAtTarget = [step.next, step.yes, step.no].includes(target);

                                if (pointsAtTarget && ! ancestors.has(candidateKey)) {
                                    ancestors.add(candidateKey);

                                    queue.push(candidateKey);
                                }
                            }
                        }

                        return ancestors;
                    },

                    redirectPointer(stepKey, fromKey, toKey) {
                        const step = this.def.steps[stepKey];

                        for (const pointer of ['next', 'yes', 'no']) {
                            if (step[pointer] === fromKey) {
                                step[pointer] = toKey;

                                return;
                            }
                        }
                    },

                    moveUp(key) {
                        const parent = this.findParentOf(key);

                        if (parent === null || this.def.steps[key].type === 'condition' || this.def.steps[parent].type === 'condition') {
                            return;
                        }

                        const grandParent = this.findParentOf(parent);

                        this.def.steps[parent].next = this.def.steps[key].next ?? null;

                        if (grandParent === null) {
                            this.def.entry = key;
                        } else {
                            this.redirectPointer(grandParent, parent, key);
                        }

                        this.def.steps[key].next = parent;
                    },

                    moveDown(key) {
                        const nextKey = this.def.steps[key]?.next ?? null;

                        if (nextKey === null
                            || this.def.steps[key].type === 'condition'
                            || this.def.steps[nextKey].type === 'condition'
                        ) {
                            return;
                        }

                        this.moveUp(nextKey);
                    },

                    findParentOf(key) {
                        for (const [candidateKey, step] of Object.entries(this.def.steps)) {
                            if ([step.next, step.yes, step.no].includes(key)) {
                                return candidateKey;
                            }
                        }

                        return null;
                    },

                    channelOfType(type) {
                        return { send_email: 'email', send_sms: 'sms', send_whatsapp: 'whatsapp' }[type] ?? null;
                    },

                    /**
                     * Copy a step into an editable shape with every field the drawer binds to.
                     */
                    normalizeStep(key) {
                        const original = JSON.parse(JSON.stringify(this.def.steps[key]));

                        const step = { ...this.blankStep(original.type), ...original };

                        const channel = this.channelOfType(step.type);

                        if (channel) {
                            normalizeContent(channel, step);
                        }

                        if (step.type === 'wait') {
                            step.time ??= '09:00';
                            step.weekdays ??= [];
                        }

                        if (step.type === 'condition') {
                            step.match = step.rule?.any ? 'any' : 'all';
                            step.rows = step.rule?.[step.match] ?? [];
                        }

                        if (step.fallback_channel === '') {
                            step.fallback_channel = null;
                        }

                        if (step.fallback_channel) {
                            step.fallback_step = normalizeContent(step.fallback_channel, step.fallback_step ?? {});
                        }

                        return step;
                    },

                    fallbackChannelChanged() {
                        const channel = this.editing.fallback_channel;

                        this.editing.fallback_step = channel ? blankContent[channel]() : null;
                    },

                    openStep(key) {
                        this.editingKey = key;

                        this.editing = this.normalizeStep(key);

                        this.$refs.stepDrawer.open();
                    },

                    /**
                     * Write the edited step back, dropping fields its mode does not use.
                     */
                    saveStep() {
                        const step = JSON.parse(JSON.stringify(this.editing));

                        if (step.type === 'condition') {
                            step.rule = { [step.match]: step.rows };

                            delete step.match;
                            delete step.rows;
                        }

                        if (step.type === 'wait') {
                            if (step.mode === 'duration') {
                                delete step.time;
                            } else {
                                delete step.minutes;
                            }

                            if (step.mode !== 'until_weekday') {
                                delete step.weekdays;
                            }
                        }

                        if ('fallback_channel' in step && ! step.fallback_channel) {
                            step.fallback_channel = null;
                            step.fallback_step = null;
                        }

                        this.def.steps[this.editingKey] = step;

                        this.$refs.stepDrawer.close();
                    },

                    openTest(key) {
                        this.testForm = {
                            stepKey: key,
                            recipient: '',
                            locale: 'en',
                            sample: 'sample',
                            customer_id: null,
                        };

                        this.$refs.testModal.toggle();
                    },

                    sendTest() {
                        const payload = {
                            recipient: this.testForm.recipient,
                            locale:    this.testForm.locale,
                            sample:    this.testForm.sample,
                        };

                        if (this.testForm.sample === 'customer') {
                            payload.customer_id = this.testForm.customer_id;
                        }

                        const url = "{{ route('admin.marketing.automation.flows.test', [':id', ':key']) }}"
                            .replace(':id', this.flow.id)
                            .replace(':key', this.testForm.stepKey);

                        // The test sends the saved draft, so save what is on screen first.
                        this.saveDraft()
                            .then(() => this.$axios.post(url, payload))
                            .then(() => {
                                this.$refs.testModal.toggle();

                                this.$emitter.emit('add-flash', { type: 'success', message: this.messages.testSent });
                            })
                            .catch((error) => this.requestFailed(error));
                    },

                    preview(key) {
                        const url = "{{ route('admin.marketing.automation.flows.preview', [':id', ':key']) }}"
                            .replace(':id', this.flow.id)
                            .replace(':key', key);

                        window.open(url, '_blank');
                    },
                },
            });
        </script>

        @include('marketing_automation::admin.flows.partials.email-editor')
    @endPushOnce
</x-admin::layouts>
