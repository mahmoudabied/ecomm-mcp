{!! view_render_event('bagisto.shop.components.layouts.header.desktop.top.before') !!}

<v-topbar>
    <div class="bg-black text-white text-sm">
        <div class="max-w-content mx-auto flex items-center justify-between py-3 px-4">
            <div></div>

            <p class="py-3 text-xs font-medium">
                {{ core()->getConfigData('general.content.header_offer.title') }}

                <a
                    href="{{ core()->getConfigData('general.content.header_offer.redirection_link') }}"
                    class="underline font-semibold ml-2"
                >
                    ShopNow
                </a>
            </p>

            <x-shop::dropdown position="bottom-{{ core()->getCurrentLocale()->direction === 'ltr' ? 'right' : 'left' }}">
                <x-slot:toggle>
                    <div
                        class="flex cursor-pointer items-center gap-2.5 py-3"
                        role="button"
                        tabindex="0"
                        @click="localeToggler = ! localeToggler"
                    >
                        <span>
                            {{ core()->getCurrentChannel()->locales()->orderBy('name')->where('code', app()->getLocale())->value('name') }}
                        </span>

                        <span
                            class="text-2xl"
                            :class="{'icon-arrow-up': localeToggler, 'icon-arrow-down': ! localeToggler}"
                            role="presentation"
                        ></span>
                    </div>
                </x-slot>

                <x-slot:content class="journal-scroll max-h-[500px] !p-0">
                    <v-locale-switcher></v-locale-switcher>
                </x-slot>
            </x-shop::dropdown>
        </div>
    </div>
</v-topbar>

{!! view_render_event('bagisto.shop.components.layouts.header.desktop.top.after') !!}

@pushOnce('scripts')
    <script type="text/x-template" id="v-topbar-template">
        <div>
            <slot></slot>
        </div>
    </script>

    <script type="text/x-template" id="v-locale-switcher-template">
        <div class="my-2.5 grid gap-1 overflow-auto max-md:my-0 sm:max-h-[500px]">
            <span
                class="flex cursor-pointer items-center gap-2.5 px-5 py-2 text-base hover:bg-gray-100"
                :class="{'bg-gray-100': locale.code == '{{ app()->getLocale() }}'}"
                v-for="locale in locales"
                @click="change(locale)"
            >
                <img
                    :src="locale.logo_url || '{{ bagisto_asset('images/default-language.svg') }}'"
                    width="24"
                    height="16"
                />

                @{{ locale.name }}
            </span>
        </div>
    </script>

    <script type="module">
        app.component('v-topbar', {
            template: '#v-topbar-template',
            data() {
                return {
                    localeToggler: '',
                };
            },
        });

        app.component('v-locale-switcher', {
            template: '#v-locale-switcher-template',
            data() {
                return {
                    locales: @json(core()->getCurrentChannel()->locales()->orderBy('name')->get()),
                };
            },
            methods: {
                change(locale) {
                    let url = new URL(window.location.href);
                    url.searchParams.set('locale', locale.code);
                    window.location.href = url.href;
                }
            }
        });
    </script>
@endPushOnce
