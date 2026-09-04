<x-layouts.admin :title="__('إضافة طالب')" current="center-students">
<div class="max-w-[820px]">

    <x-ui.page-header :title="__('إضافة طالب')"
                      :subtitle="__('حساب الطالب وصفّه وولي أمره في خطوة واحدة — ثم سجّله في مجموعة.')"
                      :back="url('/admin/center-students')" />

    <form method="POST" action="{{ url('/admin/center-students') }}" class="grid gap-4">
        @csrf

        <x-ui.card :title="__('الطالب')">
            <div class="grid gap-x-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-ui.field :label="__('الاسم')" for="name" required :error="$errors->first('name')">
                        <x-ui.input id="name" name="name" :value="old('name')" required autofocus />
                    </x-ui.field>
                </div>

                <x-ui.field :label="__('الهاتف')" for="phone" required :error="$errors->first('phone')"
                            :hint="__('يدخل به الطالب إلى المنصّة.')">
                    <x-ui.input id="phone" name="phone" type="tel" inputmode="tel" dir="ltr" :value="old('phone')" required />
                </x-ui.field>

                <x-ui.field :label="__('البريد')" for="email" :error="$errors->first('email')" :hint="__('اختياري.')">
                    <x-ui.input id="email" name="email" type="email" dir="ltr" :value="old('email')" />
                </x-ui.field>

                <x-ui.field :label="__('الصف')" for="grade_id" required :error="$errors->first('grade_id')">
                    <x-ui.select id="grade_id" name="grade_id" required>
                        <option value="">{{ __('اختر…') }}</option>
                        @foreach($grades as $grade)
                            <option value="{{ $grade->id }}" @selected((string) old('grade_id') === (string) $grade->id)>
                                {{ trim(($grade->stage?->name ?? '').' — '.$grade->name, ' —') }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field :label="__('المدرسة')" for="school" :error="$errors->first('school')">
                    <x-ui.input id="school" name="school" :value="old('school')" />
                </x-ui.field>

                <x-ui.field :label="__('تاريخ الميلاد')" for="birth_date" :error="$errors->first('birth_date')">
                    <x-ui.input id="birth_date" name="birth_date" type="date" :value="old('birth_date')"
                                onclick="this.showPicker && this.showPicker()" />
                </x-ui.field>

                <x-ui.field :label="__('النوع')" for="gender" :error="$errors->first('gender')">
                    <x-ui.select id="gender" name="gender">
                        <option value="">—</option>
                        <option value="male" @selected(old('gender') === 'male')>{{ __('ذكر') }}</option>
                        <option value="female" @selected(old('gender') === 'female')>{{ __('أنثى') }}</option>
                    </x-ui.select>
                </x-ui.field>
            </div>
        </x-ui.card>

        {{-- ولي الأمر هو من يتلقّى تنبيه الغياب وكشف الحساب — يُضاف هنا لا في شاشة أخرى تُنسى --}}
        <x-ui.card :title="__('ولي الأمر')" :subtitle="__('اختياري الآن، لكنه من يتلقّى تنبيه الغياب والأقساط.')">
            <div class="grid gap-x-4 sm:grid-cols-2">
                <x-ui.field :label="__('الاسم')" for="guardian_name" :error="$errors->first('guardian_name')">
                    <x-ui.input id="guardian_name" name="guardian_name" :value="old('guardian_name')" />
                </x-ui.field>

                <x-ui.field :label="__('صلة القرابة')" for="guardian_relation" :error="$errors->first('guardian_relation')">
                    <x-ui.select id="guardian_relation" name="guardian_relation">
                        <option value="">—</option>
                        @foreach([__('الأب'), __('الأم'), __('الجد'), __('الجدة'), __('الأخ'), __('الأخت'), __('العم'), __('الخال')] as $relation)
                            <option value="{{ $relation }}" @selected(old('guardian_relation') === $relation)>{{ $relation }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field :label="__('الهاتف')" for="guardian_phone" :error="$errors->first('guardian_phone')">
                    <x-ui.input id="guardian_phone" name="guardian_phone" type="tel" inputmode="tel" dir="ltr" :value="old('guardian_phone')" />
                </x-ui.field>

                <x-ui.field :label="__('واتساب')" for="guardian_whatsapp" :error="$errors->first('guardian_whatsapp')"
                            :hint="__('اتركه فارغاً إن كان هو الهاتف نفسه.')">
                    <x-ui.input id="guardian_whatsapp" name="guardian_whatsapp" type="tel" inputmode="tel" dir="ltr" :value="old('guardian_whatsapp')" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <div class="flex flex-wrap gap-2 sticky bottom-0 bg-bg/95 backdrop-blur py-3">
            <x-ui.button type="submit">{{ __('إضافة الطالب') }}</x-ui.button>
            <x-ui.button variant="ghost" :href="url('/admin/center-students')">{{ __('إلغاء') }}</x-ui.button>
        </div>
    </form>
</div>
</x-layouts.admin>
