@php
    $locale = $delivery->locale;
    $dir = in_array($locale, ['ar', 'fa', 'ur', 'he'], true) ? 'rtl' : 'ltr';
    $brand = setting('appearance.brand_color') ?: '#0f766e';
    $siteName = site_name();
@endphp
{{-- قالب البريد بجداول وأنماط داخلية: عملاء البريد لا يفهمون غيرهما،
     ولا شبكة CSS ولا ملف خارجي يصل إلى Outlook. --}}
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $delivery->subject }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f5f7;font-family:-apple-system,'Segoe UI',Tahoma,Arial,sans-serif;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ \Illuminate\Support\Str::limit(strip_tags($delivery->body), 120) }}</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                       style="max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;">

                    <tr>
                        <td style="background:{{ $brand }};padding:20px 24px;">
                            <span style="color:#ffffff;font-size:18px;font-weight:bold;">{{ $siteName }}</span>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px 24px;">
                            <h1 style="margin:0 0 16px;font-size:20px;line-height:1.4;color:#111827;">{{ $delivery->subject }}</h1>
                            <div style="font-size:15px;line-height:1.9;color:#374151;white-space:pre-line;">{{ $delivery->body }}</div>

                            @if($delivery->url())
                                <table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0 0;">
                                    <tr>
                                        <td style="background:{{ $brand }};border-radius:8px;">
                                            <a href="{{ $delivery->url() }}"
                                               style="display:inline-block;padding:12px 24px;color:#ffffff;font-size:15px;font-weight:bold;text-decoration:none;">
                                                {{ __('افتح', [], $locale) }}
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 24px;background:#f9fafb;border-top:1px solid #e5e7eb;">
                            <p style="margin:0;font-size:12px;line-height:1.7;color:#6b7280;">
                                {{ $siteName }} · <a href="{{ url('/') }}" style="color:#6b7280;">{{ url('/') }}</a>
                                <br>
                                <a href="{{ url('/account/notifications') }}" style="color:#6b7280;">{{ __('إدارة إشعاراتك', [], $locale) }}</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
