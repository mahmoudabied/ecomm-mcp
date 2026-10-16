<script>
    (function () {
        var productId = @js((int) $product->id);

        function sendBeacon() {
            var token = document.querySelector('meta[name=csrf-token]');

            if (! token) {
                return;
            }

            navigator.sendBeacon(
                '{{ route('klaviyo.track.viewed-product') }}',
                new Blob(
                    [JSON.stringify({ product_id: productId, _token: token.getAttribute('content') })],
                    { type: 'application/json' }
                )
            );
        }

        if (navigator.sendBeacon) {
            window.addEventListener('load', sendBeacon, { once: true });
        }
    })();
</script>
