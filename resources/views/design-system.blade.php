<x-layouts.app :title="__('نظام تصميم أُسُس')">
<div class="grid grid-cols-1 lg:grid-cols-[236px_minmax(0,1fr)] min-h-screen">

    {{-- الشريط الجانبي --}}
    <aside class="min-w-0 lg:sticky lg:top-0 lg:h-screen lg:overflow-y-auto bg-surface border-b lg:border-b-0 lg:border-e border-line p-4 sm:p-5 flex flex-col gap-4 lg:gap-6">
        <div class="flex items-center gap-3">
            <div class="size-10 rounded-md grid place-items-center text-primary-on font-bold text-lg shadow-sm shrink-0"
                 style="background-color: var(--sem-primary-hover); background-image: linear-gradient(140deg, var(--color-primary), var(--sem-primary-hover));"
                 aria-hidden="true">أ</div>
            <div>
                <div class="font-display font-extrabold text-[17px] leading-tight">أُسُس</div>
                <div class="text-2xs text-subtle">{{ __('نظام تصميم المنصّة') }} · v0.1</div>
            </div>
        </div>
        <nav class="flex lg:flex-col gap-1 lg:gap-0.5 overflow-x-auto lg:overflow-x-visible -mx-4 px-4 lg:mx-0 lg:px-0 pb-1 lg:pb-0" aria-label="{{ __('أقسام النظام') }}">
            @foreach ([
                'الأسس'      => ['colors' => 'الألوان', 'type' => 'الطباعة', 'space' => 'المسافات والأشكال'],
                'المكوّنات'  => ['buttons' => 'الأزرار والشارات', 'forms' => 'النماذج', 'feedback' => 'التنبيهات', 'data' => 'الجداول والإحصاء'],
                'المنتج'     => ['learning' => 'الكورسات والدروس', 'center' => 'السنتر والحضور'],
                'الأنماط'    => ['states' => 'الحالات', 'rules' => 'القواعد المفروضة'],
            ] as $group => $links)
                <div class="hidden lg:block text-2xs tracking-wider text-subtle font-semibold px-3 pt-3 pb-1">{{ $group }}</div>
                @foreach ($links as $id => $label)
                    <a href="#{{ $id }}" class="group text-sm text-muted hover:text-content hover:bg-surface-sunken bg-surface-sunken lg:bg-transparent px-3 py-2 lg:py-1.5 rounded-full lg:rounded-sm flex items-center gap-2 whitespace-nowrap shrink-0 transition-colors min-h-9">
                        <span class="hidden lg:block size-1.5 rounded-full bg-line-strong group-hover:bg-primary transition-colors" aria-hidden="true"></span>
                        {{ $label }}
                    </a>
                @endforeach
            @endforeach
        </nav>
    </aside>

    <main id="main" class="min-w-0 p-4 sm:p-6 lg:p-8 pb-16 max-w-[1180px]">

        <header class="flex flex-wrap items-end justify-between gap-4 mb-10">
            <div>
                <p class="text-xs text-accent-text font-semibold tracking-wide mb-1.5">{{ __('منصّة الكورسات والخدمات والسناتر') }}</p>
                <h1 class="text-2xl sm:text-3xl font-extrabold">{{ __('نظام تصميم أُسُس') }}</h1>
                <p class="text-muted mt-1.5 max-w-[56ch] text-sm">
                    {{ __('ثلاث طبقات من الـ Tokens، مكتبة مكوّنات موحّدة، وعربية أولاً. بدّل الوضع من هنا — كل ما تحته يتبع بلا سطر CSS إضافي.') }}
                </p>
            </div>
            <div class="flex gap-2 shrink-0">
                <x-ui.theme-toggle />
                <a href="{{ app()->getLocale() === 'ar' ? url('/en/design-system') : url('/design-system') }}"
                   class="inline-flex items-center bg-surface border border-line rounded-full px-4 text-xs font-semibold text-muted hover:text-content shadow-sm transition-colors">
                    {{ app()->getLocale() === 'ar' ? 'English' : 'العربية' }}
                </a>
            </div>
        </header>

        {{-- ============ الألوان ============ --}}
        <section id="colors" class="mb-16 scroll-mt-6">
            <div class="flex items-baseline gap-3 pb-3 mb-3 border-b border-line">
                <h2 class="text-xl">{{ __('الألوان') }}</h2>
                <span class="text-2xs text-subtle font-mono">3 layers</span>
            </div>
            <p class="text-muted text-sm mb-6 max-w-[70ch]">
                {{ __('الطبقة الأولى قيم خام لا تُستخدم في المكوّنات. الطبقة الثانية هي المعنى وهي المستخدمة فعلياً. ثيم المشترك يبدّل الطبقة الثانية فقط، فتتغيّر المنصة كلها بلا لمس مكوّن واحد.') }}
            </p>

            <x-ui.card class="mb-4">
                <p class="text-xs font-semibold text-subtle mb-4 tracking-wide">{{ __('الطبقة 1 — مقياس الهوية') }}</p>
                <div class="flex overflow-x-auto rounded-md border border-line">
                    @foreach ([50,100,200,300,400,500,600,700,800,900,950] as $step)
                        <div class="h-11 min-w-11 flex-1 grid place-items-end justify-center pb-1 font-mono text-[9px] {{ $step < 400 ? 'text-black/40' : 'text-white/60' }}"
                             style="background: var(--c-teal-{{ $step }})">{{ $step }}</div>
                    @endforeach
                </div>
                <p class="text-xs font-semibold text-subtle mt-5 mb-3 tracking-wide">{{ __('مقياس التمييز') }}</p>
                <div class="flex overflow-x-auto rounded-md border border-line">
                    @foreach ([100,300,500,700] as $step)
                        <div class="h-11 min-w-16 flex-1 grid place-items-end justify-center pb-1 font-mono text-[9px] {{ $step < 500 ? 'text-black/40' : 'text-white/60' }}"
                             style="background: var(--c-gold-{{ $step }})">{{ $step }}</div>
                    @endforeach
                </div>
            </x-ui.card>

            <div class="grid gap-4 md:grid-cols-2">
                <x-ui.card>
                    <p class="text-xs font-semibold text-subtle mb-3 tracking-wide">{{ __('الطبقة 2 — الأدوار') }}</p>
                    @foreach ([
                        ['أساسي', '--sem-primary'], ['تمييز', '--sem-accent'],
                        ['نجاح', '--sem-success'], ['تحذير', '--sem-warning'],
                        ['خطر', '--sem-danger'], ['معلومة', '--sem-info'],
                    ] as [$name, $token])
                        <div class="flex items-center gap-3 py-1.5">
                            <span class="size-8 rounded-md border border-line shadow-sm shrink-0" style="background: var({{ $token }})"></span>
                            <div>
                                <div class="text-sm font-semibold">{{ $name }}</div>
                                <div class="text-2xs text-subtle font-mono">{{ $token }}</div>
                            </div>
                        </div>
                    @endforeach
                </x-ui.card>
                <x-ui.card>
                    <p class="text-xs font-semibold text-subtle mb-3 tracking-wide">{{ __('الطبقة 2 — دلالات تعليمية خاصة بنا') }}</p>
                    @foreach ([
                        ['تقدّم في الدرس', '--sem-progress'], ['مكتمل', '--sem-completed'],
                        ['مقفول', '--sem-locked'], ['بث مباشر الآن', '--sem-live'],
                        ['متأخر عن الحصة', '--sem-late'], ['قسط متأخر', '--sem-overdue'],
                    ] as [$name, $token])
                        <div class="flex items-center gap-3 py-1.5">
                            <span class="size-8 rounded-md border border-line shadow-sm shrink-0" style="background: var({{ $token }})"></span>
                            <div>
                                <div class="text-sm font-semibold">{{ $name }}</div>
                                <div class="text-2xs text-subtle font-mono">{{ $token }}</div>
                            </div>
                        </div>
                    @endforeach
                </x-ui.card>
            </div>
        </section>

        {{-- ============ الطباعة ============ --}}
        <section id="type" class="mb-16 scroll-mt-6">
            <div class="flex items-baseline gap-3 pb-3 mb-3 border-b border-line">
                <h2 class="text-xl">{{ __('الطباعة') }}</h2>
                <span class="text-2xs text-subtle font-mono">Tajawal + IBM Plex Sans Arabic</span>
            </div>
            <p class="text-muted text-sm mb-6 max-w-[70ch]">
                {{ __('خطّان عربيان بدورين مختلفين: تجوّل للعناوين لأنه هندسي وواثق، وبلكس العربي للنصوص لأنه الأوضح في الأحجام الصغيرة. الأرقام بخط أحادي المسافة لتصطف في الجداول.') }}
            </p>
            <x-ui.card>
                @foreach ([
                    ['display · 36 · 800', 'font-display text-4xl font-extrabold', 'تعلَّم مهارة جديدة اليوم'],
                    ['h2 · 24 · 700', 'font-display text-2xl font-bold', 'أساسيات تطوير الويب'],
                    ['h3 · 20 · 700', 'font-display text-xl font-bold', 'الوحدة الثالثة: قواعد البيانات'],
                    ['body · 15/1.8', 'text-base', 'في هذه الوحدة نتعلّم كيف نصمّم قاعدة بيانات علائقية سليمة، ونفهم أثر الفهارس المباشر على سرعة الاستعلام.'],
                    ['body-sm · 13', 'text-sm text-muted', 'المدة المتبقّية لإنهاء الكورس: ١٤ يوماً'],
                    ['caption · 12', 'text-xs text-subtle', 'آخر تحديث للمنهج قبل ٣ أيام'],
                    ['mono · 13', 'font-mono text-sm tabular', 'EGP 1,250.00 · 2026-09-14 · #ORD-48213'],
                ] as [$meta, $cls, $sample])
                    <div class="flex items-baseline gap-5 py-3 border-b border-dashed border-line last:border-0">
                        <span class="font-mono text-2xs text-subtle w-28 shrink-0">{{ $meta }}</span>
                        <span class="{{ $cls }}">{{ $sample }}</span>
                    </div>
                @endforeach
            </x-ui.card>
        </section>

        {{-- ============ المسافات ============ --}}
        <section id="space" class="mb-16 scroll-mt-6">
            <div class="flex items-baseline gap-3 pb-3 mb-3 border-b border-line">
                <h2 class="text-xl">{{ __('المسافات والأشكال') }}</h2>
                <span class="text-2xs text-subtle font-mono">base 4px</span>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <x-ui.card>
                    <p class="text-xs font-semibold text-subtle mb-4 tracking-wide">{{ __('المسافات') }}</p>
                    <div class="flex items-end gap-3 flex-wrap">
                        @foreach ([1,2,3,4,6,8,12,16] as $n)
                            <div class="text-center font-mono text-[10px] text-subtle">
                                <span class="block bg-primary rounded-[3px] h-6 mb-1.5" style="width: {{ $n * 4 }}px"></span>{{ $n }}
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>
                <x-ui.card>
                    <p class="text-xs font-semibold text-subtle mb-4 tracking-wide">{{ __('نصف القطر') }}</p>
                    <div class="flex gap-3 flex-wrap">
                        @foreach (['sm' => 6, 'md' => 8, 'lg' => 12, 'xl' => 16, '2xl' => 22] as $name => $px)
                            <div class="size-16 bg-primary-subtle border border-primary grid place-items-end justify-center pb-1 font-mono text-[10px] text-primary"
                                 style="border-radius: {{ $px }}px">{{ $name }}</div>
                        @endforeach
                    </div>
                </x-ui.card>
            </div>
        </section>

        {{-- ============ الأزرار ============ --}}
        <section id="buttons" class="mb-16 scroll-mt-6">
            <div class="flex items-baseline gap-3 pb-3 mb-3 border-b border-line">
                <h2 class="text-xl">{{ __('الأزرار والشارات') }}</h2>
                <span class="text-2xs text-subtle font-mono">x-ui.button · x-ui.badge</span>
            </div>
            <x-ui.card class="mb-4">
                <p class="text-xs font-semibold text-subtle mb-4 tracking-wide">{{ __('الأنواع') }}</p>
                <div class="flex flex-wrap gap-3 items-center">
                    <x-ui.button>{{ __('اشترِ الكورس') }}</x-ui.button>
                    <x-ui.button variant="secondary">{{ __('حفظ كمسودة') }}</x-ui.button>
                    <x-ui.button variant="subtle">{{ __('إضافة درس') }}</x-ui.button>
                    <x-ui.button variant="accent">{{ __('ترقية الباقة') }}</x-ui.button>
                    <x-ui.button variant="ghost">{{ __('إلغاء') }}</x-ui.button>
                    <x-ui.button variant="danger">{{ __('حذف المجموعة') }}</x-ui.button>
                    <x-ui.button variant="secondary" disabled>{{ __('غير متاح في باقتك') }}</x-ui.button>
                    <x-ui.button :loading="true">{{ __('جارٍ الحفظ') }}</x-ui.button>
                </div>
                <p class="text-xs font-semibold text-subtle mt-6 mb-3 tracking-wide">{{ __('الأحجام') }}</p>
                <div class="flex flex-wrap gap-3 items-center">
                    <x-ui.button size="sm">{{ __('صغير') }}</x-ui.button>
                    <x-ui.button>{{ __('متوسط') }}</x-ui.button>
                    <x-ui.button size="lg">{{ __('كبير') }}</x-ui.button>
                </div>
            </x-ui.card>
            <x-ui.card>
                <p class="text-xs font-semibold text-subtle mb-4 tracking-wide">{{ __('الشارات — الحالة تُقرأ من الرمز والنص لا من اللون وحده') }}</p>
                <div class="flex flex-wrap gap-3 items-center">
                    <x-ui.badge tone="success" icon="✓">{{ __('منشور') }}</x-ui.badge>
                    <x-ui.badge tone="warning" icon="⏳">{{ __('بانتظار المراجعة') }}</x-ui.badge>
                    <x-ui.badge>{{ __('مسودة') }}</x-ui.badge>
                    <x-ui.badge tone="danger" icon="✕">{{ __('مرفوض') }}</x-ui.badge>
                    <x-ui.badge tone="info">{{ __('مجاني') }}</x-ui.badge>
                    <x-ui.badge tone="primary">{{ __('مستوى متقدّم') }}</x-ui.badge>
                    <x-ui.badge tone="live" :pulse="true">{{ __('مباشر الآن') }}</x-ui.badge>
                    <x-ui.badge tone="warning" icon="🔒">{{ __('يتطلب باقة أعلى') }}</x-ui.badge>
                </div>
            </x-ui.card>
        </section>

        {{-- ============ النماذج ============ --}}
        <section id="forms" class="mb-16 scroll-mt-6">
            <div class="flex items-baseline gap-3 pb-3 mb-3 border-b border-line">
                <h2 class="text-xl">{{ __('النماذج') }}</h2>
                <span class="text-2xs text-subtle font-mono">x-ui.field</span>
            </div>
            <p class="text-muted text-sm mb-6 max-w-[70ch]">
                {{ __('كل حقل يمرّ بغلاف واحد يضمن نفس التسمية والتلميح ورسالة الخطأ وعلامة المطلوب — فلا يختلف حقل عن حقل في أي شاشة.') }}
            </p>
            <div class="grid gap-4 md:grid-cols-2">
                <x-ui.card>
                    <x-ui.field :label="__('عنوان الكورس')" for="f1" :required="true" :hint="__('يظهر في نتائج البحث وصفحة الكورس.')">
                        <x-ui.input id="f1" value="أساسيات تطوير الويب بلغة PHP" />
                    </x-ui.field>
                    <x-ui.field :label="__('سعر الكورس')" for="f2" :required="true"
                                :error="__('السعر لا يمكن أن يكون بالسالب. اكتب رقماً أكبر من صفر أو اجعل الكورس مجانياً.')">
                        <x-ui.input id="f2" value="-50" :invalid="true" aria-describedby="f2-error" />
                    </x-ui.field>
                    <x-ui.field :label="__('وصف مختصر')" for="f3">
                        <x-ui.textarea id="f3" :rows="3" :placeholder="__('اكتب سطرين يشرحان ما سيتعلمه الطالب…')" />
                    </x-ui.field>
                </x-ui.card>
                <x-ui.card>
                    <x-ui.field :label="__('التصنيف')" for="f4">
                        <x-ui.select id="f4">
                            <option>{{ __('برمجة وتطوير') }}</option>
                            <option>{{ __('تصميم') }}</option>
                            <option>{{ __('لغات') }}</option>
                        </x-ui.select>
                    </x-ui.field>
                    <x-ui.field :label="__('طريقة التقديم')">
                        <div x-data="{ mode: 'recorded' }" class="inline-flex border border-line-strong rounded-md overflow-hidden" role="group">
                            @foreach (['recorded' => __('مسجّل'), 'live' => __('مباشر'), 'blended' => __('مدمج')] as $val => $label)
                                <button type="button" @click="mode = '{{ $val }}'"
                                        :aria-pressed="mode === '{{ $val }}' ? 'true' : 'false'"
                                        :class="mode === '{{ $val }}' ? 'bg-primary text-primary-on' : 'bg-surface text-muted'"
                                        class="text-sm font-semibold px-4 py-2 not-first:border-s not-first:border-line-strong transition-colors">{{ $label }}</button>
                            @endforeach
                        </div>
                    </x-ui.field>
                    <div class="flex flex-col gap-2 mb-4">
                        <x-ui.checkbox checked :label="__('إصدار الشهادة تلقائياً عند الإكمال')" />
                        <x-ui.checkbox :label="__('السماح بتحميل الدروس للمشاهدة دون اتصال')" />
                        <x-ui.checkbox checked :label="__('منع لقطة الشاشة داخل التطبيق')" />
                    </div>
                    <div class="flex gap-3">
                        <x-ui.button>{{ __('حفظ ونشر') }}</x-ui.button>
                        <x-ui.button variant="ghost">{{ __('حفظ كمسودة') }}</x-ui.button>
                    </div>
                </x-ui.card>
            </div>
        </section>

        {{-- ============ التنبيهات ============ --}}
        <section id="feedback" class="mb-16 scroll-mt-6">
            <div class="flex items-baseline gap-3 pb-3 mb-3 border-b border-line">
                <h2 class="text-xl">{{ __('التنبيهات') }}</h2>
                <span class="text-2xs text-subtle font-mono">{{ __('ماذا حدث · لماذا · ماذا تفعل') }}</span>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <x-ui.alert tone="success" :title="__('تم تفعيل الدفع عبر فوري')">
                    {{ __('يستطيع طلابك الآن الدفع نقداً من أي منفذ فوري.') }}
                </x-ui.alert>
                <x-ui.alert tone="warning" :title="__('اقتربت من حد الباقة')">
                    {{ __('استهلكت ٤٦٠ من ٥٠٠ طالب. رقِّ باقتك قبل أن يتعذّر تسجيل طلاب جدد.') }}
                </x-ui.alert>
                <x-ui.alert tone="danger" :title="__('فشل تجديد الاشتراك')">
                    {{ __('رُفضت البطاقة المنتهية في ٠٩/٢٦. حدِّث بيانات الدفع خلال ٥ أيام قبل تعليق المنصة.') }}
                </x-ui.alert>
                <x-ui.alert tone="info" :title="__('٣ واجبات بانتظار التصحيح')">
                    {{ __('أقدمها مُرسل منذ يومين. الطلاب يرون حالتها «قيد التصحيح».') }}
                </x-ui.alert>
            </div>
        </section>

        {{-- ============ الجداول ============ --}}
        <section id="data" class="mb-16 scroll-mt-6">
            <div class="flex items-baseline gap-3 pb-3 mb-3 border-b border-line">
                <h2 class="text-xl">{{ __('الجداول والإحصاء') }}</h2>
                <span class="text-2xs text-subtle font-mono">tabular-nums</span>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-4">
                <x-ui.stat :label="__('إيراد هذا الشهر')" value="٤٨٬٢٥٠" :delta="__('١٢٪ عن الشهر الماضي')" trend="up" />
                <x-ui.stat :label="__('طلاب نشطون')" value="١٬٣٨٤" delta="٨٪" trend="up" />
                <x-ui.stat :label="__('نسبة إكمال الكورسات')" value="٦٢٪" delta="٣٪" trend="down" />
                <x-ui.stat :label="__('أقساط متأخرة')" value="١٧" :delta="__('٤ حالات جديدة')" trend="down" />
            </div>
            <x-ui.table>
                <thead>
                    <tr>
                        @foreach ([__('الطالب'), __('المجموعة'), __('الحضور'), __('آخر قسط'), __('الحالة'), __('المبلغ')] as $th)
                            <th class="bg-surface-sunken text-start font-semibold text-xs text-muted px-4 py-3 border-b border-line whitespace-nowrap">{{ $th }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ([
                        ['سارة عبد الرحمن', 'ثانوية · فيزياء · سبت ٤م', '١٤/١٦', '2026-08-28', 'success', 'مدفوع', '٦٥٠'],
                        ['يوسف حمدي', 'ثانوية · فيزياء · سبت ٤م', '٩/١٦', '2026-08-05', 'warning', 'مستحق', '٦٥٠'],
                        ['منة الله طارق', 'إعدادي · رياضيات · اثنين ٦م', '١٦/١٦', '2026-09-01', 'success', 'مدفوع', '٥٠٠'],
                        ['عمر السيد', 'ثانوية · كيمياء · خميس ٥م', '٦/١٦', '2026-07-12', 'danger', 'متأخر ٥١ يوم', '٧٠٠'],
                    ] as [$name, $group, $att, $date, $tone, $status, $amount])
                        <tr class="hover:bg-surface-sunken transition-colors">
                            <td class="px-4 py-3 border-b border-line">{{ $name }}</td>
                            <td class="px-4 py-3 border-b border-line">{{ $group }}</td>
                            <td class="px-4 py-3 border-b border-line font-mono">{{ $att }}</td>
                            <td class="px-4 py-3 border-b border-line font-mono">{{ $date }}</td>
                            <td class="px-4 py-3 border-b border-line"><x-ui.badge :tone="$tone">{{ $status }}</x-ui.badge></td>
                            <td class="px-4 py-3 border-b border-line font-mono">{{ $amount }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        </section>

        {{-- ============ الكورسات ============ --}}
        <section id="learning" class="mb-16 scroll-mt-6">
            <div class="flex items-baseline gap-3 pb-3 mb-3 border-b border-line">
                <h2 class="text-xl">{{ __('الكورسات والدروس') }}</h2>
                <span class="text-2xs text-subtle font-mono">{{ __('مكوّنات المنتج') }}</span>
            </div>
            <div class="grid gap-4 lg:grid-cols-2">
                <div class="grid gap-4 sm:grid-cols-2 content-start">
                    <article class="surface-card overflow-hidden">
                        <div class="h-24 relative" style="background-color: var(--c-teal-700); background-image: linear-gradient(135deg, var(--c-teal-700), var(--c-teal-500) 70%, var(--c-gold-500));">
                            <span class="absolute top-2.5 start-2.5 text-2xs font-semibold px-2.5 py-0.5 rounded-full text-white" style="background-color:#000C">١٢ ساعة</span>
                        </div>
                        <div class="p-4">
                            <h4 class="text-[15px] font-bold mb-1">{{ __('أساسيات تطوير الويب') }}</h4>
                            <div class="text-xs text-subtle flex gap-2 flex-wrap"><span>م. أحمد معوّض</span><span>·</span><span>٤٢ درساً</span></div>
                            <div class="mt-3">
                                <x-ui.progress :value="68" :label="__('تقدّمك في الكورس')" />
                                <p class="text-xs text-subtle mt-1.5">{{ __('أنجزت ٦٨٪ — باقي ١٣ درساً') }}</p>
                            </div>
                            <div class="flex items-center justify-between mt-4 pt-3 border-t border-line">
                                <span class="font-mono font-medium"><s class="text-subtle text-xs me-1.5">١٬٢٠٠</s>٧٥٠ ج.م</span>
                                <x-ui.button size="sm">{{ __('أكمل التعلّم') }}</x-ui.button>
                            </div>
                        </div>
                    </article>
                    <article class="surface-card overflow-hidden">
                        <div class="h-24 relative" style="background-color: var(--c-teal-800); background-image: linear-gradient(135deg, var(--c-teal-800), var(--c-gold-500));">
                            <span class="absolute top-2.5 start-2.5"><x-ui.badge tone="live" :pulse="true" class="!text-white" style="background-color:#000C">{{ __('مباشر') }}</x-ui.badge></span>
                        </div>
                        <div class="p-4">
                            <h4 class="text-[15px] font-bold mb-1">{{ __('مراجعة الفيزياء النهائية') }}</h4>
                            <div class="text-xs text-subtle flex gap-2 flex-wrap"><span>{{ __('حصة مباشرة') }}</span><span>·</span><span>Zoom</span></div>
                            <p class="text-xs text-subtle mt-3">{{ __('تبدأ بعد ١٢ دقيقة · ٨٤ طالباً مسجّلاً') }}</p>
                            <div class="flex items-center justify-between mt-4 pt-3 border-t border-line">
                                <span class="font-mono font-medium">{{ __('مجاني') }}</span>
                                <x-ui.button size="sm" variant="accent">{{ __('ادخل الحصة') }}</x-ui.button>
                            </div>
                        </div>
                    </article>
                </div>
                <div>
                    <p class="text-xs font-semibold text-subtle mb-3 tracking-wide">{{ __('قائمة المنهج — الحالة تُقرأ من الأيقونة والنص معاً') }}</p>
                    <div class="surface-card overflow-hidden">
                        @foreach ([
                            ['done', '✓', 'مقدّمة: ماذا سنبني في هذا الكورس', '٠٦:٤٢'],
                            ['done', '✓', 'تجهيز بيئة العمل', '١٢:١٠'],
                            ['now',  '▸', 'الاتصال بقاعدة البيانات', '١٨:٣٣'],
                            ['todo', '◷', 'اختبار الوحدة الأولى', '١٠ أسئلة'],
                            ['lock', '🔒', 'العلاقات بين الجداول', 'يُفتح بعد ٣ أيام'],
                            ['lock', '🔒', 'مشروع التخرّج', '٤٥:٠٠'],
                        ] as [$state, $ico, $label, $meta])
                            <div @class([
                                'flex items-center gap-3 px-4 py-3 border-b border-line last:border-0 text-sm',
                                'bg-primary-subtle' => $state === 'now',
                                'text-subtle' => $state === 'lock',
                            ])>
                                <span @class([
                                    'size-6 rounded-full grid place-items-center text-xs shrink-0 font-mono',
                                    'bg-success-subtle text-success' => $state === 'done',
                                    'bg-primary text-primary-on'     => $state === 'now',
                                    'bg-surface-sunken text-muted'   => $state === 'todo',
                                    'bg-surface-sunken text-locked'  => $state === 'lock',
                                ]) aria-hidden="true">{{ $ico }}</span>
                                <span class="flex-1 min-w-0 truncate">{{ $label }}</span>
                                <span class="font-mono text-2xs text-subtle">{{ $meta }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        {{-- ============ السنتر ============ --}}
        <section id="center" class="mb-16 scroll-mt-6">
            <div class="flex items-baseline gap-3 pb-3 mb-3 border-b border-line">
                <h2 class="text-xl">{{ __('السنتر والحضور') }}</h2>
                <span class="text-2xs text-subtle font-mono">{{ __('حضوري + أونلاين') }}</span>
            </div>
            <p class="text-muted text-sm mb-6 max-w-[70ch]">
                {{ __('هذه الشاشات هي ما لا يملكه أي منافس في الكورسات الأونلاين، ولا تملكه أنظمة السناتر مربوطاً بمنصة بيع.') }}
            </p>
            <div class="grid gap-4 lg:grid-cols-2">
                <x-ui.card>
                    <div class="flex items-start justify-between gap-4 mb-4">
                        <div>
                            <strong class="text-sm">{{ __('ثانوية · فيزياء · السبت ٤:٠٠م') }}</strong>
                            <p class="text-xs text-subtle mt-0.5">{{ __('قاعة ٢ · ٢٨ طالباً · م. هبة صلاح') }}</p>
                        </div>
                        <x-ui.badge tone="primary">{{ __('حصة ١٤ من ١٦') }}</x-ui.badge>
                    </div>
                    <div class="grid gap-1.5" style="grid-template-columns: repeat(auto-fill, minmax(26px, 1fr));">
                        @foreach (['p','p','l','p','p','a','p','p','p','p','l','p','a','p','p','p','p','p','n','n'] as $i => $st)
                            <span @class([
                                'aspect-square rounded-sm grid place-items-center text-[10px] font-mono text-status-on',
                                'bg-attended' => $st === 'p',
                                'bg-absent'   => $st === 'a',
                                'bg-late'     => $st === 'l',
                                'bg-surface-sunken !text-subtle' => $st === 'n',
                            ])>{{ $i + 1 }}</span>
                        @endforeach
                    </div>
                    <div class="flex flex-wrap gap-4 mt-4 text-xs text-muted">
                        @foreach ([['attended', 'حاضر ١٤'], ['late', 'متأخر ٢'], ['absent', 'غائب ٢'], ['surface-sunken', 'لم يُسجَّل ٢']] as [$tone, $label])
                            <span class="flex items-center gap-1.5"><span class="size-3 rounded-sm bg-{{ $tone }}"></span>{{ $label }}</span>
                        @endforeach
                    </div>
                    <div class="flex flex-wrap gap-2 mt-4">
                        <x-ui.button size="sm">{{ __('مسح كود QR') }}</x-ui.button>
                        <x-ui.button size="sm" variant="secondary">{{ __('تسجيل يدوي') }}</x-ui.button>
                        <x-ui.button size="sm" variant="ghost">{{ __('إشعار أولياء الأمور') }}</x-ui.button>
                    </div>
                </x-ui.card>
                <x-ui.card>
                    <p class="text-xs font-semibold text-subtle mb-4 tracking-wide">{{ __('التقرير الشهري الذي يصل ولي الأمر') }}</p>
                    <div class="grid gap-3">
                        @foreach ([
                            [__('الحضور'), '14/16 · 87%', 87, 'attended'],
                            [__('متوسط الدرجات'), '78%', 78, 'progress'],
                            [__('الواجبات المسلّمة'), '9/12', 75, 'warning'],
                        ] as [$label, $val, $pct, $tone])
                            <div class="flex items-center justify-between text-sm"><span>{{ $label }}</span><strong class="font-mono">{{ $val }}</strong></div>
                            <x-ui.progress :value="$pct" :tone="$tone" :label="$label" />
                        @endforeach
                        <div class="flex items-center justify-between pt-3 border-t border-line text-sm">
                            <span>{{ __('موقف المصروفات') }}</span>
                            <x-ui.badge tone="danger">{{ __('متأخر ٦٥٠ ج.م') }}</x-ui.badge>
                        </div>
                    </div>
                    <div class="flex gap-2 mt-4">
                        <x-ui.button size="sm" variant="subtle">{{ __('إرسال واتساب') }}</x-ui.button>
                        <x-ui.button size="sm" variant="secondary">{{ __('طباعة PDF') }}</x-ui.button>
                    </div>
                </x-ui.card>
            </div>
        </section>

        {{-- ============ الحالات ============ --}}
        <section id="states" class="mb-16 scroll-mt-6">
            <div class="flex items-baseline gap-3 pb-3 mb-3 border-b border-line">
                <h2 class="text-xl">{{ __('الحالات') }}</h2>
                <span class="text-2xs text-subtle font-mono">{{ __('إلزامية لكل قائمة') }}</span>
            </div>
            <div class="grid gap-4 lg:grid-cols-3">
                <div>
                    <p class="text-xs font-semibold text-subtle mb-3 tracking-wide">{{ __('فارغة') }}</p>
                    <x-ui.empty :title="__('لا توجد كورسات بعد')">
                        {{ __('ابدأ بكورسك الأول — يمكنك حفظه كمسودة والعودة إليه في أي وقت.') }}
                        <x-slot:action><x-ui.button size="sm">{{ __('أنشئ كورساً') }}</x-ui.button></x-slot:action>
                    </x-ui.empty>
                </div>
                <div>
                    <p class="text-xs font-semibold text-subtle mb-3 tracking-wide">{{ __('تحميل — بشكل المحتوى القادم') }}</p>
                    <x-ui.card>
                        <x-ui.skeleton class="h-4 w-3/5 mb-3" />
                        <x-ui.skeleton class="w-full mb-2" />
                        <x-ui.skeleton class="w-[88%] mb-2" />
                        <x-ui.skeleton class="w-2/5 mb-5" />
                        <x-ui.skeleton class="h-10 w-full" />
                    </x-ui.card>
                </div>
                <div>
                    <p class="text-xs font-semibold text-subtle mb-3 tracking-wide">{{ __('خطأ — يقول ماذا يفعل المستخدم الآن') }}</p>
                    <x-ui.empty icon="!" tone="danger" :title="__('تعذّر تحميل قائمة الطلاب')" class="!border-danger">
                        {{ __('انقطع الاتصال بالخادم. بياناتك محفوظة ولم يضِع شيء.') }}
                        <x-slot:action><x-ui.button size="sm" variant="secondary">{{ __('أعد المحاولة') }}</x-ui.button></x-slot:action>
                    </x-ui.empty>
                </div>
            </div>
        </section>

        {{-- ============ القواعد ============ --}}
        <section id="rules" class="mb-10 scroll-mt-6">
            <div class="flex items-baseline gap-3 pb-3 mb-3 border-b border-line">
                <h2 class="text-xl">{{ __('كيف يبقى موحّداً') }}</h2>
                <span class="text-2xs text-subtle font-mono">{{ __('يُفرض آلياً في CI') }}</span>
            </div>
            <div class="grid gap-2">
                @foreach ([
                    ['no',  'Stylelint يرفض: أي لون حرفي · أي token من الطبقة الأولى داخل مكوّن · أي خاصية اتجاهية · أي قياس خارج السلّم.'],
                    ['no',  'ESLint يرفض: أي نص مكتوب مباشرة في الواجهة — كل نص يمرّ بملف ترجمة عربي وإنجليزي.'],
                    ['yes', 'انحدار بصري: لقطة لكل مكوّن × ٤ (عربي/إنجليزي × فاتح/داكن) في كل Pull Request.'],
                    ['yes', 'فحص التباين آلي على كل الـ tokens — أي نسبة أقل من 4.5:1 توقف الـ build.'],
                    ['yes', 'قاعدة الصفر: مكوّن غير موجود في النظام لا يُستخدم — يُضاف للنظام أولاً.'],
                ] as [$kind, $text])
                    <div class="flex gap-3 items-start text-sm surface-card px-4 py-3">
                        <span class="font-mono shrink-0 w-4 {{ $kind === 'no' ? 'text-danger' : 'text-success' }}" aria-hidden="true">{{ $kind === 'no' ? '✕' : '✓' }}</span>
                        <span>{{ $text }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        <footer class="border-t border-line pt-6 text-xs text-subtle flex flex-wrap justify-between gap-3">
            <span>{{ __('أُسُس · نظام تصميم منصة الكورسات والخدمات والسناتر') }}</span>
            <span class="font-mono">Laravel {{ app()->version() }} · Blade + Alpine + Tailwind 4</span>
        </footer>
    </main>
</div>
</x-layouts.app>
