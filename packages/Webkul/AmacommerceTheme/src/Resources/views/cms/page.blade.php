@push('meta')
    <meta name="title" content="{{ $page->meta_title }}" />
    <meta name="description" content="{{ $page->meta_description }}" />
    <meta name="keywords" content="{{ $page->meta_keywords }}" />
@endPush

<x-shop::layouts>
    <x-slot:title>
        {{ $page->meta_title }}
    </x-slot>

    <div class="max-w-content mx-auto px-4 py-10">
        <nav class="text-sm text-text-secondary mb-8">
            <a href="{{ route('shop.home.index') }}">Home</a> / <span>{{ $page->page_title }}</span>
        </nav>

        <div class="prose max-w-none">
            {!! $page->html_content !!}
        </div>
    </div>

    @include('shop::components.layouts.services')
</x-shop::layouts>
