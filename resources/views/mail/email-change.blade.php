@php
    $brand = setting('appearance.brand_color') ?: '#0f766e';
    $siteName = site_name();
@endphp
{{-- جداول وأنماط داخلية: عملاء البريد لا يفهمون غيرهما --}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('تأكيد بريدك الجديد') }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f5f7;font-family:-apple-system,'Segoe UI',Tahoma,Arial,sans-serif;">
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
                        <td style="padding:28px 24px;color:#111827;font-size:15px;line-height:1.9;">
                            <p style="margin:0 0 12px;font-size:18px;font-weight:bold;">{{ __('تأكيد بريدك الجديد') }}</p>

                            <p style="margin:0 0 16px;">
                                {{ __('مرحباً :name، طلبتَ أن يصير بريد دخولك إلى :site هو هذا العنوان.', [
                                    'name' => $user->name,
                                    'site' => $siteName,
                                ]) }}
                            </p>

                            <p style="margin:0 0 24px;">
                                {{ __('اضغط الزرّ لإتمام التغيير. حتى تفعل، يبقى بريدك القديم صالحاً للدخول.') }}
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
                                <tr>
                                    <td style="background:{{ $brand }};border-radius:8px;">
                                        <a href="{{ $url }}"
                                           style="display:inline-block;padding:12px 24px;color:#ffffff;text-decoration:none;font-weight:bold;font-size:15px;">
                                            {{ __('أكّد البريد الجديد') }}
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 8px;color:#6b7280;font-size:13px;">
                                {{ __('ينتهي الرابط خلال ساعة واحدة.') }}
                            </p>
                            <p style="margin:0;color:#6b7280;font-size:13px;">
                                {{ __('إن لم تطلب هذا فتجاهل الرسالة — ولن يتغيّر شيء. ويُستحسن أن تغيّر كلمة مرورك.') }}
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 24px;background:#f9fafb;border-top:1px solid #e5e7eb;color:#6b7280;font-size:12px;">
                            <span style="direction:ltr;display:inline-block;">{{ $url }}</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
