<x-layouts.student :title="__('الإشعارات')" current="notifications">

<div>

    <x-ui.page-header :title="__('الإشعارات')"
                      :subtitle="trans_choice('{0} لا جديد|{1} إشعار واحد غير مقروء|{2} إشعاران غير مقروءين|[3,10] :count إشعارات غير مقروءة|[11,*] :count إشعاراً غير مقروء', $unread, ['count' => $unread])">
        <x-slot:actions>
            @if($unread > 0)
                <form method="POST" action="{{ url('/notifications/read-all') }}">
                    @csrf
                    <x-ui.button type="submit" variant="secondary" size="sm">{{ __('علّم الكل مقروءاً') }}</x-ui.button>
                </form>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if(session('status'))
        <x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    <div class="flex items-center gap-2 mb-4">
        <a href="{{ url('/notifications') }}"
           @class(['min-h-11 px-4 grid place-items-center rounded-md text-sm font-semibold border transition-colors',
                   'bg-primary text-primary-on border-transparent' => request('filter') !== 'unread',
                   'bg-surface border-line-strong hover:bg-surface-sunken' => request('filter') === 'unread'])>{{ __('الكل') }}</a>
        <a href="{{ url('/notifications?filter=unread') }}"
           @class(['min-h-11 px-4 grid place-items-center rounded-md text-sm font-semibold border transition-colors',
                   'bg-primary text-primary-on border-transparent' => request('filter') === 'unread',
                   'bg-surface border-line-strong hover:bg-surface-sunken' => request('filter') !== 'unread'])>{{ __('غير المقروء') }}</a>
    </div>

    @if($notifications->isEmpty())
        <x-ui.card>
            <x-ui.empty :title="__('لا إشعارات')">{{ __('ما يخصّك سيظهر هنا أولاً بأول.') }}</x-ui.empty>
        </x-ui.card>
    @else
        <ul class="flex flex-col gap-2">
            @foreach($notifications as $notification)
                <li @class(['surface-card p-4 flex items-start gap-3', 'border-s-4 border-s-primary' => $notification->isUnread()])>
                    <span class="size-9 shrink-0 rounded-md grid place-items-center bg-primary-subtle text-primary" aria-hidden="true">
                        {{ $notification->icon ?: '◔' }}
                    </span>

                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-sm leading-snug">{{ $notification->title }}</p>
                        @if($notification->body)
                            <p class="text-sm text-muted leading-relaxed mt-1 whitespace-pre-line">{{ $notification->body }}</p>
                        @endif
                        <p class="text-2xs text-subtle mt-1.5">{{ $notification->created_at?->diffForHumans() }}</p>
                    </div>

                    @if($notification->url)
                        <form method="POST" action="{{ url('/notifications/'.$notification->id.'/read') }}" class="shrink-0">
                            @csrf
                            <x-ui.button type="submit" size="sm" variant="secondary">{{ __('افتح') }}</x-ui.button>
                        </form>
                    @elseif($notification->isUnread())
                        <form method="POST" action="{{ url('/notifications/'.$notification->id.'/read') }}" class="shrink-0">
                            @csrf
                            <x-ui.button type="submit" size="sm" variant="ghost" aria-label="{{ __('علّم مقروءاً') }}">✓</x-ui.button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>

        @if($notifications->hasPages())
            <div class="mt-6">
                <x-ui.pagination :current="$notifications->currentPage()" :last="$notifications->lastPage()"
                                 :url="request()->fullUrlWithQuery(['page' => '']).''" />
            </div>
        @endif
    @endif

    <p class="text-center mt-6">
        <a href="{{ url('/account/notifications') }}" class="tap-link text-sm text-primary font-semibold">{{ __('إدارة إشعاراتك') }}</a>
    </p>
</div>

</x-layouts.student>
