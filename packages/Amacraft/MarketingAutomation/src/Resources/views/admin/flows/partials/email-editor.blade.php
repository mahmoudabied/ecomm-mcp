<!--
    Block email editor (v-automation-email-editor).

    A left palette of block types, a sortable block list, a props form for the
    selected block, a variable picker and a live preview that posts the draft
    step to the preview endpoint. Mutates the `content` prop in place, like the
    other flow field components.
-->

<script
    type="text/x-template"
    id="v-automation-email-editor-template"
>
    <div class="grid gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <button
                type="button"
                v-for="locale in locales"
                :key="locale"
                class="rounded px-2.5 py-1.5 text-sm"
                :class="locale === activeLocale ? 'bg-blue-600 text-white' : 'bg-gray-100 dark:bg-gray-800'"
                @click="activeLocale = locale"
            >
                @{{ locale.toUpperCase() }}
            </button>

            <button
                type="button"
                class="rounded bg-gray-100 px-2.5 py-1.5 text-sm dark:bg-gray-800"
                @click="copyFromDefault"
            >
                @lang('marketing_automation::app.flows.editor.copy-default')
            </button>
        </div>

        <div class="grid grid-cols-[1fr_2fr] gap-3">
            <div class="grid content-start gap-1">
                <p class="text-xs font-semibold text-gray-500">@lang('marketing_automation::app.flows.editor.blocks')</p>

                <div
                    v-for="(block, index) in blocks"
                    :key="index"
                    class="flex items-center justify-between rounded px-2 py-1.5 text-sm"
                    :class="index === selectedIndex ? 'bg-blue-50 dark:bg-blue-900' : 'bg-gray-50 dark:bg-gray-800'"
                >
                    <button
                        type="button"
                        class="grow text-start"
                        @click="selectedIndex = index"
                    >
                        @{{ blockLabels[block.type] ?? block.type }}
                    </button>

                    <span class="flex shrink-0 items-center gap-1">
                        <button
                            type="button"
                            title="@lang('marketing_automation::app.flows.editor.move-up')"
                            :disabled="index === 0"
                            @click="move(index, -1)"
                        >↑</button>

                        <button
                            type="button"
                            title="@lang('marketing_automation::app.flows.editor.move-down')"
                            :disabled="index === blocks.length - 1"
                            @click="move(index, 1)"
                        >↓</button>

                        <button
                            type="button"
                            title="@lang('marketing_automation::app.flows.editor.remove')"
                            @click="blocks.splice(index, 1); selectedIndex = null"
                        >×</button>
                    </span>
                </div>

                <div class="mt-1 flex flex-wrap gap-1">
                    <button
                        type="button"
                        v-for="(label, type) in blockLabels"
                        :key="type"
                        class="rounded border px-2 py-1 text-xs"
                        @click="addBlock(type)"
                    >
                        + @{{ label }}
                    </button>
                </div>
            </div>

            <div class="grid content-start gap-2">
                <template v-if="selected">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-semibold text-gray-500">@{{ blockLabels[selected.type] ?? selected.type }}</p>

                        <select
                            v-if="hasTextField"
                            v-model="focusField"
                            class="rounded border bg-white px-2 py-1 text-xs dark:bg-gray-900"
                        >
                            <option value="">@lang('marketing_automation::app.flows.editor.insert-variable')</option>

                            <option
                                v-for="path in variables"
                                :key="path"
                                :value="path"
                            >
                                @{{ path }}
                            </option>
                        </select>
                    </div>

                    <p
                        v-if="hint"
                        class="rounded bg-amber-50 px-2 py-1.5 text-xs text-amber-700 dark:bg-amber-900 dark:text-amber-200"
                    >
                        @lang('marketing_automation::app.flows.hints.add-coupon')
                    </p>

                    <label
                        v-for="field in fields"
                        :key="field.key"
                        class="grid gap-1"
                    >
                        <span class="text-xs text-gray-500">@{{ field.label }}</span>

                        <textarea
                            v-if="field.type === 'textarea'"
                            rows="4"
                            class="w-full rounded border bg-white px-2.5 py-2 text-sm dark:bg-gray-900"
                            v-model="selected[field.key]"
                            @focus="focusField = field.key"
                        ></textarea>

                        <input
                            v-else-if="field.type === 'select'"
                            type="text"
                            class="w-full rounded border bg-white px-2.5 py-2 text-sm dark:bg-gray-900"
                            v-model="selected[field.key]"
                        >

                        <select
                            v-else-if="field.type === 'options'"
                            class="w-full rounded border bg-white px-2.5 py-2 text-sm dark:bg-gray-900"
                            v-model="selected[field.key]"
                        >
                            <option
                                v-for="option in field.options"
                                :key="option"
                                :value="option"
                            >
                                @{{ option }}
                            </option>
                        </select>

                        <input
                            v-else
                            :type="field.type"
                            class="w-full rounded border bg-white px-2.5 py-2 text-sm dark:bg-gray-900"
                            v-model="selected[field.key]"
                            @focus="focusField = field.key"
                        >
                    </label>
                </template>

                <p
                    v-else
                    class="text-sm text-gray-500"
                >
                    @lang('marketing_automation::app.flows.editor.pick-block')
                </p>

                <div
                    v-if="flowId"
                    class="grid gap-2 border-t pt-2 dark:border-gray-700"
                >
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="rounded bg-gray-100 px-2.5 py-1.5 text-sm dark:bg-gray-800"
                            @click="refreshPreview"
                        >
                            @lang('marketing_automation::app.flows.editor.preview')
                        </button>

                        <span
                            v-if="previewing"
                            class="text-xs text-gray-500"
                        >
                            @lang('marketing_automation::app.flows.editor.previewing')
                        </span>
                    </div>

                    <iframe
                        v-if="previewHtml"
                        :srcdoc="previewHtml"
                        class="h-96 w-full rounded border bg-white dark:border-gray-700"
                    ></iframe>
                </div>
            </div>
        </div>
    </div>
</script>

<script type="module">
    const emailEditorLocales = ['en', 'ar'];

    const emailEditorBlockLabels = @js([
        'header'       => trans('marketing_automation::app.flows.editor.blocks-type.header'),
        'text'         => trans('marketing_automation::app.flows.editor.blocks-type.text'),
        'image'        => trans('marketing_automation::app.flows.editor.blocks-type.image'),
        'button'       => trans('marketing_automation::app.flows.editor.blocks-type.button'),
        'product_grid' => trans('marketing_automation::app.flows.editor.blocks-type.product_grid'),
        'cart_items'   => trans('marketing_automation::app.flows.editor.blocks-type.cart_items'),
        'order_summary' => trans('marketing_automation::app.flows.editor.blocks-type.order_summary'),
        'coupon'       => trans('marketing_automation::app.flows.editor.blocks-type.coupon'),
        'footer'       => trans('marketing_automation::app.flows.editor.blocks-type.footer'),
    ]);

    const emailEditorVariables = @js(app(\Amacraft\MarketingAutomation\Rendering\TemplateRenderer::class)->variables());

    const emailEditorFields = {
        header: [
            { key: 'title', label: trans('marketing_automation::app.flows.editor.fields.title') },
            { key: 'logo_url', label: trans('marketing_automation::app.flows.editor.fields.logo-url') },
        ],
        text: [
            { key: 'text', label: trans('marketing_automation::app.flows.editor.fields.content'), type: 'textarea' },
        ],
        image: [
            { key: 'src', label: trans('marketing_automation::app.flows.editor.fields.image-url') },
            { key: 'alt', label: trans('marketing_automation::app.flows.editor.fields.alt') },
            { key: 'href', label: trans('marketing_automation::app.flows.editor.fields.link') },
        ],
        button: [
            { key: 'label', label: trans('marketing_automation::app.flows.editor.fields.label') },
            { key: 'href', label: trans('marketing_automation::app.flows.editor.fields.link') },
            { key: 'color', label: trans('marketing_automation::app.flows.editor.fields.color') },
        ],
        product_grid: [
            { key: 'source', label: trans('marketing_automation::app.flows.editor.fields.source'), type: 'options', options: ['bestsellers', 'recommended', 'related'] },
            { key: 'count', label: trans('marketing_automation::app.flows.editor.fields.count'), type: 'number' },
        ],
        coupon: [
            { key: 'label', label: trans('marketing_automation::app.flows.editor.fields.label') },
            { key: 'cart_rule_id', label: 'Cart rule ID', type: 'number' },
            { key: 'expires_days', label: trans('marketing_automation::app.flows.editor.fields.expires-days'), type: 'number' },
        ],
        footer: [
            { key: 'text', label: trans('marketing_automation::app.flows.editor.fields.footer-text'), type: 'textarea' },
        ],
        cart_items: [],
        order_summary: [],
    };

    const emailEditorBlankBlocks = {
        header:       () => ({ type: 'header', title: '' }),
        text:         () => ({ type: 'text', text: '' }),
        image:        () => ({ type: 'image', src: 'https://', alt: '' }),
        button:       () => ({ type: 'button', label: '', href: 'https://', color: '#0f172a' }),
        product_grid: () => ({ type: 'product_grid', source: 'bestsellers', count: 3 }),
        cart_items:   () => ({ type: 'cart_items' }),
        order_summary: () => ({ type: 'order_summary' }),
        coupon:       () => ({ type: 'coupon', label: '', cart_rule_id: null }),
        footer:       () => ({ type: 'footer', text: '' }),
    };

    app.component('v-automation-email-editor', {
        template: '#v-automation-email-editor-template',

        props: {
            content: { type: Object, required: true },

            flowId: { type: Number, default: null },

            stepKey: { type: String, default: '' },

            templateKey: { type: String, default: '' },
        },

        data() {
            return {
                locales: emailEditorLocales,

                activeLocale: emailEditorLocales[0],

                blockLabels: emailEditorBlockLabels,

                variables: emailEditorVariables,

                selectedIndex: null,

                focusField: '',

                previewHtml: '',

                previewing: false,
            };
        },

        computed: {
            blocks() {
                this.content.blocks[this.activeLocale] ??= [];

                return this.content.blocks[this.activeLocale];
            },

            selected() {
                return this.selectedIndex === null ? null : this.blocks[this.selectedIndex] ?? null;
            },

            fields() {
                return emailEditorFields[this.selected?.type] ?? [];
            },

            hasTextField() {
                return this.fields.some((field) => ['text', 'textarea', 'input'].includes(field.type))
                    && this.focusField !== '';
            },

            hint() {
                return this.templateKey === 'abandoned_cart'
                    && this.stepKey === 's5'
                    && ! this.blocks.some((block) => block.type === 'coupon');
            },
        },

        methods: {
            addBlock(type) {
                this.blocks.push(emailEditorBlankBlocks[type]());

                this.selectedIndex = this.blocks.length - 1;
            },

            move(index, offset) {
                const target = index + offset;

                if (target < 0 || target >= this.blocks.length) {
                    return;
                }

                [this.blocks[index], this.blocks[target]] = [this.blocks[target], this.blocks[index]];

                this.selectedIndex = target;
            },

            copyFromDefault() {
                const source = JSON.parse(JSON.stringify(this.content.blocks[emailEditorLocales[0]] ?? []));

                this.content.blocks[this.activeLocale] = source;

                this.content.subject[this.activeLocale] = this.content.subject[emailEditorLocales[0]] ?? '';

                this.selectedIndex = null;
            },

            insertVariable(path) {
                if (! this.selected || ! this.focusField) {
                    return;
                }

                // Built by concatenation so Blade and Vue leave the Liquid braces alone.
                const token = '{' + '{ ' + path + ' | default: "" ' + '}';

                this.selected[this.focusField] = ''.concat(this.selected[this.focusField] ?? '', token);

                this.focusField = '';
            },

            refreshPreview() {
                this.previewing = true;

                const url = "{{ route('admin.marketing.automation.flows.preview_draft', [':id']) }}".replace(':id', this.flowId);

                fetch(url, {
                    method: 'POST',

                    headers: {
                        'Content-Type': 'application/json',

                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                    },

                    body: JSON.stringify({
                        step: {
                            type: 'send_email',

                            subject: this.content.subject,

                            blocks: this.content.blocks,
                        },

                        locale: this.activeLocale,
                    }),
                })
                    .then((response) => (response.ok ? response.text() : ''))
                    .then((html) => {
                        this.previewHtml = html;

                        this.previewing = false;
                    });
            },
        },

        watch: {
            focusField(path) {
                if (path === '') {
                    return;
                }

                this.insertVariable(path);
            },
        },
    });
</script>
