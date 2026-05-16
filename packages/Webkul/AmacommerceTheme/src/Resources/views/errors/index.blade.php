<x-shop::layouts
    :has-header="false"
    :has-feature="false"
    :has-footer="false"
>
    <x-slot:title>
        @lang("admin::app.errors.{$errorCode}.title")
    </x-slot>

    <div class="max-w-content mx-auto px-4 py-20">
        <nav class="text-sm text-text-secondary mb-20">
            <a href="{{ route('shop.home.index') }}">Home</a> / <span>404 Error</span>
        </nav>

        <div class="text-center">
            <h1 class="text-[110px] font-medium leading-none mb-10">404 Not Found</h1>

            <p class="text-base text-text-secondary mb-20">
                @if ($errorCode === 503 && core()->getCurrentChannel()->maintenance_mode_text != "")
                    {{ core()->getCurrentChannel()->maintenance_mode_text }}
                @else
                    {{ trans("admin::app.errors.{$errorCode}.description") }}
                @endif
            </p>

            <a
                href="{{ route('shop.home.index') }}"
                class="inline-block bg-primary text-white px-12 py-4 rounded text-base font-medium hover:bg-primary-hover transition"
            >
                Back to home page
            </a>
        </div>
    </div>
</x-shop::layouts>
