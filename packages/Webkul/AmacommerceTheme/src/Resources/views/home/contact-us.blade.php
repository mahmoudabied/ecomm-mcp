<x-shop::layouts>
    <x-slot:title>
        @lang('shop::app.home.contact.title')
    </x-slot>

    <div class="max-w-content mx-auto px-4 py-10">
        <nav class="text-sm text-text-secondary mb-8">
            <a href="{{ route('shop.home.index') }}" class="hover:text-black">Home</a> / <span>Contact</span>
        </nav>

        <div class="flex gap-[30px]">
            <div class="w-[30%] hidden lg:block">
                <div class="shadow-sm rounded p-8">
                    <div class="w-10 h-10 bg-primary rounded-full flex items-center justify-center mb-4">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                        </svg>
                    </div>
                    <h3 class="font-medium text-base mb-2">Call To Us</h3>
                    <p class="text-sm text-text-secondary mb-2">We are available 24/7, 7 days a week.</p>
                    <p class="text-sm">Phone: +8801611112222</p>
                </div>

                <div class="shadow-sm rounded p-8 mt-[30px]">
                    <div class="w-10 h-10 bg-primary rounded-full flex items-center justify-center mb-4">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>
                    </div>
                    <h3 class="font-medium text-base mb-2">Write To Us</h3>
                    <p class="text-sm text-text-secondary mb-2">Fill out our form and we will contact you within 24 hours.</p>
                    <p class="text-sm mb-1">customer@exclusive.com</p>
                    <p class="text-sm">support@exclusive.com</p>
                </div>
            </div>

            <div class="flex-1">
                <x-shop::form :action="route('shop.home.contact_us.send_mail')">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                        <x-shop::form.control-group>
                            <x-shop::form.control-group.control
                                type="text"
                                class="bg-bg-secondary rounded px-4 py-3 w-full border-0"
                                name="name"
                                rules="required"
                                :value="old('name')"
                                :label="trans('shop::app.home.contact.name')"
                                :placeholder="trans('shop::app.home.contact.name')"
                                :aria-label="trans('shop::app.home.contact.name')"
                                aria-required="true"
                            />
                            <x-shop::form.control-group.error control-name="name" />
                        </x-shop::form.control-group>

                        <x-shop::form.control-group>
                            <x-shop::form.control-group.control
                                type="email"
                                class="bg-bg-secondary rounded px-4 py-3 w-full border-0"
                                name="email"
                                rules="required|email"
                                :value="old('email')"
                                :label="trans('shop::app.home.contact.email')"
                                :placeholder="trans('shop::app.home.contact.email')"
                                :aria-label="trans('shop::app.home.contact.email')"
                                aria-required="true"
                            />
                            <x-shop::form.control-group.error control-name="email" />
                        </x-shop::form.control-group>

                        <x-shop::form.control-group>
                            <x-shop::form.control-group.control
                                type="text"
                                class="bg-bg-secondary rounded px-4 py-3 w-full border-0"
                                name="contact"
                                rules="phone"
                                :value="old('contact')"
                                :label="trans('shop::app.home.contact.phone-number')"
                                :placeholder="trans('shop::app.home.contact.phone-number')"
                                :aria-label="trans('shop::app.home.contact.phone-number')"
                            />
                            <x-shop::form.control-group.error control-name="contact" />
                        </x-shop::form.control-group>
                    </div>

                    <x-shop::form.control-group class="mt-8">
                        <x-shop::form.control-group.control
                            type="textarea"
                            class="bg-bg-secondary rounded px-4 py-3 w-full border-0 h-48"
                            name="message"
                            rules="required"
                            :label="trans('shop::app.home.contact.message')"
                            :placeholder="trans('shop::app.home.contact.describe-here')"
                            :aria-label="trans('shop::app.home.contact.message')"
                            aria-required="true"
                        />
                        <x-shop::form.control-group.error control-name="message" />
                    </x-shop::form.control-group>

                    @if (core()->getConfigData('customer.captcha.credentials.status'))
                        <div class="mb-5 flex">
                            {!! \Webkul\Customer\Facades\Captcha::render() !!}
                        </div>
                    @endif

                    <div class="flex justify-end mt-8">
                        <button
                            type="submit"
                            class="bg-primary text-white px-12 py-4 rounded text-base font-medium hover:bg-primary-hover transition"
                        >
                            Send Message
                        </button>
                    </div>
                </x-shop::form>
            </div>
        </div>
    </div>

    @push('scripts')
        {!! \Webkul\Customer\Facades\Captcha::renderJS() !!}
    @endpush
</x-shop::layouts>
