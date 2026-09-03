<x-layouts.admin :title="__('الإعلانات')" current="announcements">
<div class="max-w-[1000px]">

    <x-ui.page-header :title="__('إعلانات كورساتي')"
                      :subtitle="__('يُثبَّت الإعلان في صفحة الكورس حيث يقرأ الطالب — لا في بريد يُهمَل.')" />

    @if(session('status'))
        <x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">

        <x-ui.card :title="__('إعلان جديد')">
            @if($courses->isEmpty())
                <x-ui.empty :title="__('لا كورسات')">{{ __('الإعلان يحتاج كورساً يُنشر فيه.') }}</x-ui.empty>
            @else
                <form method="POST" action="{{ url('/admin/announcements') }}" class="grid gap-3">
                    @csrf

                    <x-ui.field :label="__('الكورس')" name="course_id" required :error="$errors->first('course_id')">
                        <x-ui.select name="course_id" :invalid="$errors->has('course_id')">
                            @foreach($courses as $course)
                                <option value="{{ $course->id }}" @selected(old('course_id') == $course->id)>{{ $course->title }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>

                    <x-ui.field :label="__('العنوان')" name="title" required :error="$errors->first('title')">
                        <x-ui.input name="title" :value="old('title')" :invalid="$errors->has('title')" />
                    </x-ui.field>

                    <x-ui.field :label="__('النص')" name="body" required :error="$errors->first('body')">
                        <x-ui.textarea name="body" rows="6" :invalid="$errors->has('body')">{{ old('body') }}</x-ui.textarea>
                    </x-ui.field>

                    <x-ui.checkbox name="notify" value="1" :checked="old('notify', true)">
                        <span class="text-sm">{{ __('أرسل إشعاراً للملتحقين') }}</span>
                    </x-ui.checkbox>

                    <div><x-ui.button type="submit">{{ __('انشر الإعلان') }}</x-ui.button></div>
                </form>
            @endif
        </x-ui.card>

        <x-ui.card :title="__('ما نشرتَه')" :padding="false">
            @if($announcements->isEmpty())
                <div class="p-5"><x-ui.empty :title="__('لا إعلانات بعد')">{{ __('أول إعلان تنشره سيظهر هنا.') }}</x-ui.empty></div>
            @else
                <ul class="divide-y divide-line">
                    @foreach($announcements as $announcement)
                        <li class="px-5 py-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold">{{ $announcement->title }}</p>
                                    <p class="text-xs text-subtle mt-1">
                                        {{ $announcement->course?->title }} · {{ $announcement->created_at?->diffForHumans() }}
                                    </p>
                                    <p class="text-sm text-muted mt-2 leading-relaxed">{{ Str::limit($announcement->body, 220) }}</p>
                                </div>
                                <form method="POST" action="{{ url('/admin/announcements/'.$announcement->id) }}" class="shrink-0">
                                    @csrf @method('DELETE')
                                    <x-ui.button type="submit" size="sm" variant="danger">{{ __('حذف') }}</x-ui.button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>

                @if($announcements->hasPages())
                    <div class="p-4 border-t border-line">
                        <x-ui.pagination :current="$announcements->currentPage()" :last="$announcements->lastPage()"
                                         :url="request()->fullUrlWithQuery(['page' => '']).''" />
                    </div>
                @endif
            @endif
        </x-ui.card>
    </div>
</div>
</x-layouts.admin>
