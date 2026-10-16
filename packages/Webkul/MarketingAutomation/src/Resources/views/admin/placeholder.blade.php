<x-admin::layouts>
    <x-slot:title>
        {{ $title }}
    </x-slot>

    <div class="flex justify-between gap-4 max-sm:flex-wrap">
        <p class="text-xl font-bold text-gray-800 dark:text-white">
            {{ $title }}
        </p>
    </div>
</x-admin::layouts>
