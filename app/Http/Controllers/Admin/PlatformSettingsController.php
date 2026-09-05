<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Audit\Audit;
use App\Core\Billing\PlatformSettings;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * إعدادات التحصيل — بيانات نستقبل بها أموال الاشتراكات.
 *
 * كانت في ملف البيئة، فتغييرُ رقم حساب يحتاج دخولاً على الخادم.
 * وهي بيانات تجارية يغيّرها صاحب المنصّة لا المبرمج.
 */
final class PlatformSettingsController extends Controller
{
    /**
     * حقول كل طريقة — مصدر واحد يبني الشاشة ويحرس المُدخل معاً،
     * فلا يُضاف حقل إلى النموذج وينسى التحقّق منه.
     *
     * @var array<string, array{label:string, hint:?string, fields:array<string, array{label:string, rules:list<string>, hint?:string, type?:string}>}>
     */
    private const METHODS = [
        'instapay' => [
            'label' => 'إنستاباي',
            'hint' => 'التحويل اللحظي — الأسرع وصولاً في مصر.',
            'fields' => [
                'address' => ['label' => 'عنوان إنستاباي', 'rules' => ['nullable', 'string', 'max:120'], 'hint' => 'مثل: a.muawad@instapay'],
                'name' => ['label' => 'اسم المستفيد', 'rules' => ['nullable', 'string', 'max:120']],
            ],
        ],
        'bank' => [
            'label' => 'تحويل بنكي',
            'hint' => 'للمبالغ الكبيرة وللشركات التي تطلب إيصالاً بنكياً.',
            'fields' => [
                'name' => ['label' => 'البنك', 'rules' => ['nullable', 'string', 'max:120']],
                'account_name' => ['label' => 'اسم الحساب', 'rules' => ['nullable', 'string', 'max:160']],
                'account_number' => ['label' => 'رقم الحساب', 'rules' => ['nullable', 'string', 'max:64']],
                'iban' => ['label' => 'الآيبان', 'rules' => ['nullable', 'string', 'max:64']],
                'swift' => ['label' => 'السويفت', 'rules' => ['nullable', 'string', 'max:32']],
            ],
        ],
        'wallet' => [
            'label' => 'محفظة إلكترونية',
            'hint' => 'فودافون كاش وأخواتها.',
            'fields' => [
                'number' => ['label' => 'رقم المحفظة', 'rules' => ['nullable', 'string', 'max:32']],
                'name' => ['label' => 'اسم المستفيد', 'rules' => ['nullable', 'string', 'max:120']],
                'provider' => ['label' => 'المشغّل', 'rules' => ['nullable', 'string', 'max:60'], 'hint' => 'فودافون كاش · اتصالات كاش · أورنج كاش'],
            ],
        ],
    ];

    /** حقول مشتركة بين كل الطرائق. */
    private const SHARED = [
        'title' => ['label' => 'الاسم كما يراه العميل', 'rules' => ['nullable', 'string', 'max:80']],
        'description' => ['label' => 'سطر التوضيح', 'rules' => ['nullable', 'string', 'max:200']],
        'instructions' => ['label' => 'تعليمات التحويل', 'rules' => ['nullable', 'string', 'max:600'], 'type' => 'textarea'],
    ];

    public function __construct(private readonly PlatformSettings $settings) {}

    public function edit(): View
    {
        return view('super-admin.billing-settings', [
            'methods' => self::METHODS,
            'shared' => self::SHARED,
            'values' => $this->settings->all(),
            'ready' => array_map(
                fn (string $key): bool => $this->settings->methodReady($key),
                array_combine(array_keys(self::METHODS), array_keys(self::METHODS)),
            ),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [];
        $names = [];

        foreach (self::METHODS as $method => $meta) {
            $rules[$method.'_enabled'] = ['nullable', 'boolean'];

            foreach ([...$meta['fields'], ...self::SHARED] as $field => $spec) {
                $rules[$method.'_'.$field] = $spec['rules'];
                $names[$method.'_'.$field] = $meta['label'].' — '.$spec['label'];
            }
        }

        $input = $request->validate($rules, [], $names);

        foreach (self::METHODS as $method => $meta) {
            $this->settings->set($method.'.enabled', $request->boolean($method.'_enabled') ? '1' : '');

            foreach (array_keys([...$meta['fields'], ...self::SHARED]) as $field) {
                $this->settings->set($method.'.'.$field, $input[$method.'_'.$field] ?? null);
            }
        }

        /*
         | تغيير بيانات التحصيل يُقيَّد: من غيّر رقم الحساب الذي تصل
         | إليه أموال المنصّة، ومتى — سؤالٌ يجب أن يكون له جواب.
         */
        Audit::record('platform.billing_settings_updated', null, null, [
            'enabled' => array_values(array_filter(
                array_keys(self::METHODS),
                fn (string $m): bool => $request->boolean($m.'_enabled'),
            )),
        ]);

        return back()->with('status', __('حُفظت بيانات التحصيل.'));
    }
}
