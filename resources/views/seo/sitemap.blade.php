<?php echo '<?xml version="1.0" encoding="UTF-8"?>'."\n"; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach($urls as $url)
    <url>
        <loc>{{ $url['loc'] }}</loc>
@if($url['lastmod'])
        <lastmod>{{ $url['lastmod'] }}</lastmod>
@endif
        <changefreq>{{ $url['changefreq'] }}</changefreq>
        <priority>{{ $url['priority'] }}</priority>
@foreach(config('locales.prefixed', []) as $prefix)
        <xhtml:link rel="alternate" hreflang="{{ $prefix }}" href="{{ str_replace(url('/'), url('/'.$prefix), $url['loc']) }}"/>
@endforeach
        <xhtml:link rel="alternate" hreflang="{{ config('locales.default', 'ar') }}" href="{{ $url['loc'] }}"/>
    </url>
@endforeach
</urlset>
