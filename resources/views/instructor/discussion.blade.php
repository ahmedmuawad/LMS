<x-layouts.admin :title="$discussion->title" current="discussions">
<div class="max-w-[820px]">

    <x-ui.page-header :title="$discussion->title"
                      :subtitle="__(':name · :course', ['name' => $discussion->user?->name ?? '—', 'course' => $discussion->course?->title ?? ''])"
                      :back="url('/admin/discussions')">
        <x-slot:actions>
            <form method="POST" action="{{ url('/admin/discussions/'.$discussion->id) }}" class="flex items-center gap-2">
                @csrf @method('PUT')
                <x-ui.select name="status" class="w-40" onchange="this.form.submit()">
                    @foreach(App\Modules\Community\Models\Discussion::STATUSES as $key => $label)
                        <option value="{{ $key }}" @selected($discussion->status === $key)>{{ __($label) }}</option>
                    @endforeach
                </x-ui.select>
                <noscript><x-ui.button type="submit" size="sm" variant="secondary">{{ __('حفظ') }}</x-ui.button></noscript>
            </form>
        </x-slot:actions>
    </x-ui.page-header>

    @if(session('status'))
        <x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    <x-ui.card class="mb-4">
        <p class="text-sm leading-relaxed whitespace-pre-line">{{ $discussion->body }}</p>
        <p class="text-xs text-subtle mt-3">{{ $discussion->created_at?->diffForHumans() }}</p>
    </x-ui.card>

    @if($discussion->replies->isNotEmpty())
        <ul class="grid gap-3 mb-4">
            @foreach($discussion->replies as $reply)
                <li @class([
                    'surface-card p-4',
                    'border-s-2 border-s-primary' => $reply->is_instructor,
                ])>
                    <div class="flex items-center justify-between gap-3 mb-2">
                        <span class="text-xs font-semibold">{{ $reply->user?->name ?? '—' }}</span>
                        <div class="flex items-center gap-2">
                            @if($reply->is_answer)<x-ui.badge tone="success">{{ __('الإجابة') }}</x-ui.badge>@endif
                            <span class="text-2xs text-subtle">{{ $reply->created_at?->diffForHumans() }}</span>
                        </div>
                    </div>
                    <p class="text-sm leading-relaxed whitespace-pre-line">{{ $reply->body }}</p>
                </li>
            @endforeach
        </ul>
    @endif

    <x-ui.card :title="__('ردّك')">
        <form method="POST" action="{{ url('/admin/discussions/'.$discussion->id.'/replies') }}" class="grid gap-3">
            @csrf

            <x-ui.field name="body" :error="$errors->first('body')">
                <x-ui.textarea name="body" rows="5" :invalid="$errors->has('body')"
                               :placeholder="__('اكتب ردّك للطالب…')">{{ old('body') }}</x-ui.textarea>
            </x-ui.field>

            <x-ui.checkbox name="is_answer" value="1" :checked="old('is_answer')">
                <span class="text-sm">{{ __('اعتبر ردّي إجابة السؤال') }}</span>
            </x-ui.checkbox>

            <div><x-ui.button type="submit">{{ __('أرسل') }}</x-ui.button></div>
        </form>
    </x-ui.card>
</div>
</x-layouts.admin>
