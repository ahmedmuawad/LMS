@props(['meta' => null])
@php
    /** @var App\Core\Seo\Seo $seo */
    $seo = app(App\Core\Seo\Seo::class);
    $meta = $meta ?? $seo->forPage(null);

    $title = $meta->title ?: $seo->title(null);
    $description = $meta->description;
    $canonical = $meta->canonical ?: $seo->canonical();
    $image = $meta->image;
    $siteName = setting()->translated('general.site_name') ?: (tenant('name') ?? config('app.name'));

    $schemas = [...$seo->siteSchema(), ...$meta->schema];
    $crumbs = $seo->breadcrumbSchema($meta->breadcrumbs);

    if ($crumbs !== null) {
        $schemas[] = $crumbs;
    }
@endphp

@if($description)<meta name="description" content="{{ $description }}">@endif

{{-- الفهرسة: إعداد المشترك أولاً، ثم استثناء الصفحة نفسها --}}
@if($seo->isIndexable($meta))
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1">
@else
    <meta name="robots" content="noindex, nofollow">
@endif

@if($canonical)<link rel="canonical" href="{{ $canonical }}">@endif

@foreach($seo->alternates() as $locale => $href)
    <link rel="alternate" hreflang="{{ $locale }}" href="{{ $href }}">
@endforeach

<meta property="og:type" content="{{ $meta->type }}">
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:title" content="{{ $title }}">
@if($description)<meta property="og:description" content="{{ $description }}">@endif
@if($canonical)<meta property="og:url" content="{{ $canonical }}">@endif
@if($image)<meta property="og:image" content="{{ $image }}">@endif
<meta property="og:locale" content="{{ str_replace('-', '_', app()->getLocale()) }}">

<meta name="twitter:card" content="{{ $image ? setting('seo.twitter_card', 'summary_large_image') : 'summary' }}">
@if(setting('seo.twitter_site'))<meta name="twitter:site" content="{{ setting('seo.twitter_site') }}">@endif
<meta name="twitter:title" content="{{ $title }}">
@if($description)<meta name="twitter:description" content="{{ $description }}">@endif
@if($image)<meta name="twitter:image" content="{{ $image }}">@endif

@if($meta->type === 'article')
    @if($meta->publishedAt)<meta property="article:published_time" content="{{ $meta->publishedAt }}">@endif
    @if($meta->modifiedAt)<meta property="article:modified_time" content="{{ $meta->modifiedAt }}">@endif
    @if($meta->author)<meta property="article:author" content="{{ $meta->author }}">@endif
@endif

@foreach(['google' => 'google-site-verification', 'bing' => 'msvalidate.01', 'yandex' => 'yandex-verification', 'pinterest' => 'p:domain_verify'] as $key => $name)
    @if(setting('seo.'.$key.'_verification'))
        <meta name="{{ $name }}" content="{{ setting('seo.'.$key.'_verification') }}">
    @endif
@endforeach

@foreach($schemas as $schema)
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endforeach
