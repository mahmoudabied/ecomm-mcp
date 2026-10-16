{!! view_render_event('bagisto.shop.components.layouts.header.before') !!}

<div class="hidden lg:block">
    @include('shop::components.layouts.header.desktop.top')
    @include('shop::components.layouts.header.desktop.bottom')
</div>

<div class="lg:hidden">
    @include('shop::components.layouts.header.mobile.index')
</div>

{!! view_render_event('bagisto.shop.components.layouts.header.after') !!}
