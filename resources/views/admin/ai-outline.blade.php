<x-layouts.admin :title="__('بناء المنهج')" current="courses">
<div class="max-w-[820px]">

    <x-ui.page-header :title="__('بناء منهج: :course', ['course' => $course->title])"
                      :subtitle="__('صِف كورسك في سطرين، واحصل على أقسامه ودروسه مرتّبة — تُراجعها وتملؤها بنفسك.')"
                      :back="url('/admin/courses/'.$course->getKey().'/curriculum')" />

    @error('brief')<x-ui.alert tone="danger" class="mb-5">{{ $message }}</x-ui.alert>@enderror

    {{--
        الحدّ يُقال قبل العمل: ما يُبنى هيكلٌ لا محتوى، ومن يظنّه
        كورساً جاهزاً ينشره فارغاً.
    --}}
    <x-ui.alert tone="info" class="mb-6">
        {{ __('يُبنى الهيكل وحده: أسماء الأقسام والدروس وترتيبها ومدّتها المقدّرة. الدروس تُنشأ فارغة، وأنت تملؤها بالفيديو أو النصّ. ويُضاف إلى منهجك ولا يُمحى ما فيه.') }}
    </x-ui.alert>

    <x-ui.card>
        <form method="POST" action="{{ url('/admin/courses/'.$course->getKey().'/ai-outline') }}" class="grid gap-4">
            @csrf

            <x-ui.field :label="__('وصف الكورس')" for="brief" required class="mb-0"
                        :hint="__('ما موضوعه؟ ولمن؟ وما الذي يخرج به الطالب؟ سطران يكفيان.')">
                <x-ui.textarea id="brief" name="brief" rows="5" maxlength="4000" required>{{ old('brief') }}</x-ui.textarea>
            </x-ui.field>

            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.field :label="__('عدد الأقسام')" for="sections" required class="mb-0">
                    <x-ui.input id="sections" name="sections" type="number" min="1" max="12"
                                value="{{ old('sections', 5) }}" required />
                </x-ui.field>

                <x-ui.field :label="__('دروس كل قسم')" for="per_section" required class="mb-0">
                    <x-ui.input id="per_section" name="per_section" type="number" min="1" max="10"
                                value="{{ old('per_section', 4) }}" required />
                </x-ui.field>

                <x-ui.field :label="__('مستوى الطلبة')" for="level" required class="mb-0">
                    <select id="level" name="level"
                            class="w-full min-h-11 rounded-md border border-line-strong bg-surface text-sm px-3">
                        <option value="مبتدئ">{{ __('مبتدئ') }}</option>
                        <option value="متوسط" selected>{{ __('متوسط') }}</option>
                        <option value="متقدّم">{{ __('متقدّم') }}</option>
                    </select>
                </x-ui.field>
            </div>

            <div><x-ui.button type="submit">{{ __('ابنِ الهيكل') }}</x-ui.button></div>
        </form>
    </x-ui.card>

</div>
</x-layouts.admin>
