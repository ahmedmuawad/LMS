@php
    $ga4 = setting('analytics.ga4_id');
    $gtm = setting('analytics.gtm_id');
    $ads = setting('analytics.google_ads_id');
    $pixel = setting('analytics.meta_pixel_id');
    $clarity = setting('analytics.clarity_id');
    $hotjar = setting('analytics.hotjar_id');
    $consentMode = (bool) setting('analytics.consent_mode', true);
    $anonymize = (bool) setting('analytics.anonymize_ip', true);
@endphp

@if($ga4 || $gtm || $ads)
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        @if($consentMode)
        /* الافتراضي رفض: القياس ينتظر الموافقة لا العكس — وهذا شرط
           GDPR وما يقابله، ومخالفته غرامة لا ملاحظة. */
        gtag('consent', 'default', {
            ad_storage: 'denied',
            ad_user_data: 'denied',
            ad_personalization: 'denied',
            analytics_storage: 'denied',
            functionality_storage: 'granted',
            security_storage: 'granted',
            wait_for_update: 500,
        });
        @endif
        gtag('js', new Date());
    </script>
@endif

@if($gtm)
    <script>
        (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});
        var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';
        j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','{{ $gtm }}');
    </script>
@elseif($ga4 || $ads)
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga4 ?: $ads }}"></script>
    <script>
        @if($ga4)gtag('config', @js($ga4), { anonymize_ip: {{ $anonymize ? 'true' : 'false' }} });@endif
        @if($ads)gtag('config', @js($ads));@endif
    </script>
@endif

@if($pixel)
    <script>
        !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
        n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', @js($pixel));
        fbq('track', 'PageView');
    </script>
    <noscript><img height="1" width="1" style="display:none" alt=""
        src="https://www.facebook.com/tr?id={{ $pixel }}&ev=PageView&noscript=1"></noscript>
@endif

@if($clarity)
    <script>
        (function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
        t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
        y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);})(window,document,"clarity","script",@js($clarity));
    </script>
@endif

@if($hotjar)
    <script>
        (function(h,o,t,j,a,r){h.hj=h.hj||function(){(h.hj.q=h.hj.q||[]).push(arguments)};
        h._hjSettings={hjid:{{ (int) $hotjar }},hjsv:6};a=o.getElementsByTagName('head')[0];
        r=o.createElement('script');r.async=1;r.src=t+h._hjSettings.hjid+j;a.appendChild(r);
        })(window,document,'https://static.hotjar.com/c/hotjar-','.js?sv=');
    </script>
@endif
