<div class="ma-root">
    <div
        id="ma-contact-capture"
        class="mb-4 grid gap-2 text-sm"
    >
        <label class="flex items-center gap-2">
            <input
                type="checkbox"
                name="marketing_consent[email]"
                value="1"
                class="accent-black dark:accent-white"
            >

            <span>@lang('marketing_automation::app.shop.contact-capture.email')</span>
        </label>

        <label class="flex items-center gap-2">
            <input
                type="checkbox"
                name="marketing_consent[sms]"
                value="1"
                class="accent-black dark:accent-white"
            >

            <span>@lang('marketing_automation::app.shop.contact-capture.sms')</span>
        </label>

        <label class="flex items-center gap-2">
            <input
                type="checkbox"
                name="marketing_consent[whatsapp]"
                value="1"
                class="accent-black dark:accent-white"
            >

            <span>@lang('marketing_automation::app.shop.contact-capture.whatsapp')</span>
        </label>
    </div>

    {{--
        The checkout address form is drawn by Vue after the cart loads, so the listeners are delegated
        from the document. Core names its inputs "billing.email" and "billing.phone"; bracket names are
        matched too for themes that use them.
    --}}
    @pushOnce('scripts')
        <script>
            (function () {
                var endpoint = '{{ route('shop.automation.checkout-contact') }}';

                var lastSent = null;

                /**
                 * Whether the element is the checkout's email or phone input, never a consent checkbox.
                 */
                function isField(element, field) {
                    if (
                        ! element
                        || element.tagName !== 'INPUT'
                        || ! element.name
                        || element.type === 'checkbox'
                        || element.name.startsWith('marketing_consent')
                    ) {
                        return false;
                    }

                    return element.name.endsWith('.' + field)
                        || element.name.endsWith('[' + field + ']');
                }

                /**
                 * The first filled value of the given contact field.
                 */
                function valueOf(field) {
                    var input = Array.from(document.querySelectorAll('input[name]')).find(function (element) {
                        return isField(element, field)
                            && element.value.trim() !== '';
                    });

                    return input ? input.value.trim() : '';
                }

                /**
                 * The state of the consent checkboxes, keyed by channel.
                 */
                function consents() {
                    var result = {};

                    document.querySelectorAll('#ma-contact-capture input[type="checkbox"]').forEach(function (checkbox) {
                        result[checkbox.name.replace('marketing_consent[', '').replace(']', '')] = checkbox.checked;
                    });

                    return result;
                }

                /**
                 * The request body for the current contact, or null when there is no contact to send.
                 */
                function payload() {
                    var email = valueOf('email');

                    var phone = valueOf('phone');

                    if (
                        email === ''
                        && phone === ''
                    ) {
                        return null;
                    }

                    var body = { consents: consents() };

                    if (email !== '') {
                        body.email = email;
                    }

                    if (phone !== '') {
                        body.phone = phone;
                    }

                    return JSON.stringify(body);
                }

                /**
                 * Post the contact and consents, skipping a body identical to the last one sent.
                 */
                function send() {
                    var body = payload();

                    if (
                        body === null
                        || body === lastSent
                    ) {
                        return;
                    }

                    lastSent = body;

                    fetch(endpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                            'Accept': 'application/json',
                        },
                        body: body,
                    }).then(function (response) {
                        if (! response.ok) {
                            lastSent = null;
                        }
                    }).catch(function () {
                        lastSent = null;
                    });
                }

                document.addEventListener('focusout', function (event) {
                    if (
                        isField(event.target, 'email')
                        || isField(event.target, 'phone')
                    ) {
                        send();
                    }
                });

                document.addEventListener('change', function (event) {
                    if (event.target.closest?.('#ma-contact-capture')) {
                        send();
                    }
                });
            })();
        </script>
    @endPushOnce
</div>
