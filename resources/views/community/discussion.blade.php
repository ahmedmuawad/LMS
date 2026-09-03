<x-layouts.app :title="$discussion->title">
<x-site.header />

@php
    $me = auth()->user();
    $mayAccept = $me !== null && ($discussion->isOwnedBy($me)
        || $discussion->course?->instructor?->user_id === $me->getKey()
        || in_array($me->role, ['owner', 'admin'], true));
    $voted = fn (string $type, $id): bool => (bool) ($myVotes[$type.':'.$id] ?? false);
@endphp

<main id="main" class="max-w-[760px] mx-auto px-4 sm:px-6 py-8">

    <x-ui.breadcrumb :items="[
        ['label' => __('النقاش'), 'url' => url('/discussions')],
        ['label' => $discussion->title],
    ]" />

    @if(session('status'))
        <x-ui.alert tone="success" class="mt-4">{{ session('status') }}</x-ui.alert>
    @endif

    <article class="surface-card p-5 mt-4">
        <div class="flex items-start gap-4">
            <div class="shrink-0 flex flex-col items-center gap-1">
                @auth
                    <form method="POST" action="{{ url('/discussions/'.$discussion->id.'/vote') }}">
                        @csrf
                        <button type="submit" aria-label="{{ __('صوّت') }}"
                                @class(['size-11 grid place-items-center rounded-md border transition-colors',
                                        'bg-primary text-primary-on border-transparent' => $voted(App\Modules\Community\Models\Discussion::class, $discussion->id),
                                        'border-line-strong hover:bg-surface-sunken' => ! $voted(App\Modules\Community\Models\Discussion::class, $discussion->id)])>▲</button>
                    </form>
                @endauth
                <span class="font-mono font-bold tabular">{{ $discussion->votes_count }}</span>
            </div>

            <div class="flex-1 min-w-0">
                <h1 class="text-xl font-bold leading-snug">{{ $discussion->title }}</h1>

                <div class="flex flex-wrap items-center gap-2 mt-2">
                    @if($discussion->isAnswered())<x-ui.badge tone="success">{{ __('أُجيب') }}</x-ui.badge>@endif
                    @if($discussion->course)
                        <a href="{{ url('/courses/'.$discussion->course->slug) }}" class="tap-link text-2xs text-primary">{{ $discussion->course->title }}</a>
                    @endif
                </div>

                <div class="text-muted leading-relaxed whitespace-pre-line mt-3">{{ $discussion->body }}</div>

                <div class="flex items-center gap-2 mt-4 pt-3 border-t border-default">
                    <x-ui.avatar :name="$discussion->user?->name ?? ''" size="sm" />
                    <span class="text-2xs text-subtle">{{ $discussion->user?->name }} · {{ $discussion->created_at?->diffForHumans() }}</span>
                </div>
            </div>
        </div>
    </article>

    <h2 class="text-lg font-bold mt-8 mb-3">
        {{ trans_choice('{0} لا ردود بعد|{1} ردّ واحد|{2} ردّان|[3,10] :count ردود|[11,*] :count ردّاً', $replies->count(), ['count' => $replies->count()]) }}
    </h2>

    <ul class="flex flex-col gap-3">
        @foreach($replies as $reply)
            <li @class(['surface-card p-4 flex items-start gap-4', 'border-s-4 border-s-success' => $reply->is_answer])>
                <div class="shrink-0 flex flex-col items-center gap-1">
                    @auth
                        <form method="POST" action="{{ url('/discussions/'.$discussion->id.'/replies/'.$reply->id.'/vote') }}">
                            @csrf
                            <button type="submit" aria-label="{{ __('صوّت') }}"
                                    @class(['size-11 grid place-items-center rounded-md border transition-colors',
                                            'bg-primary text-primary-on border-transparent' => $voted(App\Modules\Community\Models\DiscussionReply::class, $reply->id),
                                            'border-line-strong hover:bg-surface-sunken' => ! $voted(App\Modules\Community\Models\DiscussionReply::class, $reply->id)])>▲</button>
                        </form>
                    @endauth
                    <span class="font-mono font-bold tabular text-sm">{{ $reply->votes_count }}</span>
                </div>

                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2 mb-2">
                        <x-ui.avatar :name="$reply->user?->name ?? ''" size="sm" />
                        <span class="font-semibold text-sm">{{ $reply->user?->name }}</span>
                        @if($reply->is_instructor)<x-ui.badge tone="primary">{{ __('المدرّس') }}</x-ui.badge>@endif
                        @if($reply->is_answer)<x-ui.badge tone="success">{{ __('الإجابة المقبولة') }}</x-ui.badge>@endif
                        <span class="text-2xs text-subtle">{{ $reply->created_at?->diffForHumans() }}</span>
                    </div>

                    <div class="text-muted leading-relaxed whitespace-pre-line">{{ $reply->body }}</div>

                    @if($mayAccept && ! $reply->is_answer)
                        <form method="POST" action="{{ url('/discussions/'.$discussion->id.'/replies/'.$reply->id.'/accept') }}" class="mt-3">
                            @csrf
                            <x-ui.button type="submit" size="sm" variant="secondary">{{ __('اقبل كإجابة') }}</x-ui.button>
                        </form>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>

    @auth
        @if($discussion->status !== 'closed')
            <form method="POST" action="{{ url('/discussions/'.$discussion->id.'/replies') }}" class="surface-card p-4 mt-6 flex flex-col gap-3">
                @csrf
                <x-ui.field :label="__('ردّك')" for="body" class="mb-0" :error="$errors->first('body') ?: $errors->first('reply')">
                    <x-ui.textarea name="body" id="body" rows="4" required :placeholder="__('شارك ما تعرفه…')" />
                </x-ui.field>
                <x-ui.button type="submit" class="self-start">{{ __('أرسل الردّ') }}</x-ui.button>
            </form>
        @else
            <x-ui.alert tone="info" class="mt-6">{{ __('هذا النقاش مغلق.') }}</x-ui.alert>
        @endif
    @else
        <x-ui.card class="mt-6">
            <p class="text-sm text-muted">
                {{ __('سجّل دخولك للمشاركة.') }}
                <a href="{{ url('/login') }}" class="tap-link text-primary font-semibold">{{ __('تسجيل الدخول') }}</a>
            </p>
        </x-ui.card>
    @endauth
</main>

<x-site.footer />
</x-layouts.app>
