@props([
    'hasHeader'  => true,
    'hasFeature' => true,
    'hasFooter'  => true,
])

<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ core()->getCurrentLocale()->direction }}">
    <head>
        {!! view_render_event('bagisto.shop.layout.head.before') !!}
        <title>{{ $title ?? 'Amacommerce Theme' }}</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="base-url" content="{{ url()->to('/') }}">
        <meta name="currency" content="{{ core()->getCurrentCurrency()->toJson() }}">
        
        @stack('meta')

        <link rel="icon" sizes="16x16" href="{{ core()->getCurrentChannel()->favicon_url ?? bagisto_asset('images/favicon.ico') }}" />

        <script>
            window._vueAppQueue = { components: [], directives: [], plugins: [], mounted: false };
            window.app = new Proxy({}, {
                get(_, prop) {
                    if (prop === 'component') return function() { window._vueAppQueue.components.push(arguments); return window.app; };
                    if (prop === 'directive') return function() { window._vueAppQueue.directives.push(arguments); return window.app; };
                    if (prop === 'use') return function() { window._vueAppQueue.plugins.push(arguments); return window.app; };
                    if (prop === 'mount') return function(el) { window._vueAppQueue.mountEl = el; };
                    if (prop === 'config') return { globalProperties: {} };
                }
            });
        </script>

        @bagistoVite(['src/Resources/assets/css/app.css', 'src/Resources/assets/js/app.js'], 'amacommerce')

        <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />

        @stack('styles')
        {!! view_render_event('bagisto.shop.layout.head.after') !!}
    </head>
    <body class="font-poppins antialiased text-text2 bg-bg">
        {!! view_render_event('bagisto.shop.layout.body.before') !!}

        <div id="app" class="flex flex-col min-h-screen">
            <x-shop::flash-group />
            <x-shop::modal.confirm />

            @if ($hasHeader)
                @include('shop::components.layouts.header')
            @endif

            {!! view_render_event('bagisto.shop.layout.content.before') !!}

            <main id="main" class="flex-grow">
                {{ $slot }}
            </main>

            {!! view_render_event('bagisto.shop.layout.content.after') !!}

            @if ($hasFooter)
                @include('shop::components.layouts.footer')
            @endif
        </div>

        {!! view_render_event('bagisto.shop.layout.body.after') !!}

        @stack('scripts')
    </body>
</html>
