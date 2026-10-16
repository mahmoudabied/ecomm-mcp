@push('meta')
    <meta name="description" content="@lang('shop::app.customers.login-form.page-title')"/>
    <meta name="keywords" content="@lang('shop::app.customers.login-form.page-title')"/>
@endPush

<x-shop::layouts
    :has-header="false"
    :has-feature="false"
    :has-footer="false"
>
    <x-slot:title>
        @lang('shop::app.customers.login-form.page-title')
    </x-slot>

    <div class="flex min-h-screen">
        <div class="w-1/2 hidden lg:flex bg-[#CBE4E8] items-center justify-center relative overflow-hidden">
            <div class="absolute inset-0 opacity-10">
                <div class="absolute top-10 left-10 w-32 h-32 rounded-full bg-white"></div>
                <div class="absolute bottom-20 right-20 w-48 h-48 rounded-full bg-white"></div>
                <div class="absolute top-1/3 right-10 w-24 h-24 rounded-full bg-white"></div>
            </div>
            <div class="relative z-10 text-center px-16">
                <svg class="w-48 h-48 mx-auto mb-8 text-gray-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                </svg>
                <h2 class="text-3xl font-semibold text-gray-700 mb-3">Welcome Back</h2>
                <p class="text-gray-500 text-lg">Shop the latest trends and exclusive deals</p>
            </div>
        </div>

        <div class="w-full lg:w-1/2 flex items-center justify-center px-20 max-md:px-8">
            <div class="w-full max-w-[500px]">
                <a
                    href="{{ route('shop.home.index') }}"
                    class="inline-block mb-10"
                >
                    <img
                        src="{{ core()->getCurrentChannel()->logo_url ?? bagisto_asset('images/logo.svg') }}"
                        alt="{{ config('app.name') }}"
                        width="131"
                        height="29"
                    />
                </a>

                <h2 class="text-4xl font-medium mb-3">Log in to Exclusive</h2>
                <p class="text-base mb-10">Enter your details below</p>

                {!! view_render_event('bagisto.shop.customers.login.before') !!}

                <x-shop::form :action="route('shop.customer.session.create')">
                    {!! view_render_event('bagisto.shop.customers.login_form_controls.before') !!}

                    <x-shop::form.control-group>
                        <x-shop::form.control-group.control
                            type="email"
                            class="w-full border-0 border-b border-border-color rounded-none px-0 py-3 outline-none shadow-none bg-transparent text-base focus:border-black focus:ring-0"
                            name="email"
                            rules="required|email"
                            value=""
                            :label="trans('shop::app.customers.login-form.email')"
                            placeholder="Email or Phone Number"
                            :aria-label="trans('shop::app.customers.login-form.email')"
                            aria-required="true"
                        />

                        <x-shop::form.control-group.error control-name="email" />
                    </x-shop::form.control-group>

                    <x-shop::form.control-group>
                        <x-shop::form.control-group.control
                            type="password"
                            class="w-full border-0 border-b border-border-color rounded-none px-0 py-3 outline-none shadow-none bg-transparent text-base focus:border-black focus:ring-0"
                            id="password"
                            name="password"
                            rules="required|min:6"
                            value=""
                            :label="trans('shop::app.customers.login-form.password')"
                            :placeholder="trans('shop::app.customers.login-form.password')"
                            :aria-label="trans('shop::app.customers.login-form.password')"
                            aria-required="true"
                        />

                        <x-shop::form.control-group.error control-name="password" />
                    </x-shop::form.control-group>

                    <div class="flex justify-between mt-6">
                        <div class="flex select-none items-center gap-1.5">
                            <input
                                type="checkbox"
                                id="show-password"
                                class="peer hidden"
                                onchange="switchVisibility()"
                            />

                            <label
                                class="icon-uncheck peer-checked:icon-check-box cursor-pointer text-2xl text-navyBlue peer-checked:text-navyBlue max-sm:text-xl"
                                for="show-password"
                            ></label>

                            <label
                                class="cursor-pointer select-none text-base text-text-secondary ltr:pl-0 rtl:pr-0"
                                for="show-password"
                            >
                                Show Password
                            </label>
                        </div>
                    </div>

                    @if (core()->getConfigData('customer.captcha.credentials.status'))
                        <div class="mt-5 flex">
                            {!! \Webkul\Customer\Facades\Captcha::render() !!}
                        </div>
                    @endif

                    <div class="flex items-center justify-between mt-10">
                        <button
                            type="submit"
                            class="bg-primary text-white px-12 py-4 rounded text-base font-medium hover:bg-primary-hover transition"
                        >
                            Log In
                        </button>

                        <a
                            href="{{ route('shop.customers.forgot_password.create') }}"
                            class="text-primary font-medium hover:underline"
                        >
                            Forgot Password?
                        </a>
                    </div>

                    {!! view_render_event('bagisto.shop.customers.login_form_controls.after') !!}
                </x-shop::form>

                {!! view_render_event('bagisto.shop.customers.login.after') !!}

                <p class="mt-8 text-text-secondary">
                    New customer?
                    <a
                        href="{{ route('shop.customers.register.index') }}"
                        class="border-b border-border-color font-medium ml-2 hover:text-primary transition"
                    >
                        Create Your Account
                    </a>
                </p>
            </div>
        </div>
    </div>

    @push('scripts')
        {!! \Webkul\Customer\Facades\Captcha::renderJS() !!}

        <script>
            function switchVisibility() {
                let passwordField = document.getElementById("password");

                passwordField.type = passwordField.type === "password"
                    ? "text"
                    : "password";
            }
        </script>
    @endpush
</x-shop::layouts>
