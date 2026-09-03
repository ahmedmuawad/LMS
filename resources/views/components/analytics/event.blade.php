@props(['name', 'data' => []])
{{--
    حدث تجارة واحد يُرسل إلى كل ما هو مفعّل.

    نُرسله مرة في مكان واحد لا في كل قالب: الشراء الذي يُبلَّغ لجوجل
    ولا يُبلَّغ لميتا يجعل لوحتَي الإعلانات تختلفان، ولا يُكتشف
    الفرق إلا بعد شهر من الصرف على أرقام خاطئة.
--}}
@if(setting('analytics.ecommerce_tracking', true))
    @php
        $metaMap = [
            'view_item' => 'ViewContent',
            'add_to_cart' => 'AddToCart',
            'begin_checkout' => 'InitiateCheckout',
            'purchase' => 'Purchase',
            'sign_up' => 'CompleteRegistration',
            'generate_lead' => 'Lead',
        ];
        $metaEvent = $metaMap[$name] ?? null;
    @endphp

    @push('scripts')
        <script>
            (function () {
                var payload = {!! json_encode($data, JSON_UNESCAPED_UNICODE) !!};

                if (window.dataLayer) {
                    window.dataLayer.push(Object.assign({ event: @js($name) }, payload));
                }

                if (typeof gtag === 'function') {
                    gtag('event', @js($name), payload);
                }

                @if($metaEvent)
                if (typeof fbq === 'function') {
                    fbq('track', @js($metaEvent), {
                        value: payload.value,
                        currency: payload.currency,
                        content_ids: (payload.items || []).map(function (i) { return i.item_id; }),
                        content_type: 'product',
                    });
                }
                @endif
            })();
        </script>
    @endpush
@endif
