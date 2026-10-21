{!! $assets() !!}
@php
    $slotHtml = trim((string) $slot);
    $state = [
        'config' => $config,
        'thumbs' => null,
        'isThumbs' => $isThumbs,
        // Handled by the runtime, not Swiper's keyboard module, so keys move only the carousel in use.
        'keyboard' => $keyboard && ! $isThumbs,
        'reducedMotion' => (bool) config('swiper.reduced_motion', true),
        'labels' => ['pause' => __('swiper::swiper.pause'), 'play' => __('swiper::swiper.play')],
    ];
    // Stored paths, not URLs: a signed temporary URL changes on every render but the image doesn't.
    $hash = md5(json_encode($state).$slotHtml.serialize(array_map(fn ($image) => [$image->path ?? $image->src, $image->alt, $image->caption, $image->href, $image->srcset !== null && $image->path !== null ? 'srcset' : $image->srcset], $slides)));
    $domId = $resolvedId($hash);
    $state['thumbs'] = $renderThumbs ? $domId.'-thumbs' : $thumbsTarget;
    $sizesAttr = $imageSizes();
@endphp
@if ($renderThumbs)<div class="laravel-swiper-gallery">@endif
<div {{ $attributes->class(['swiper', 'laravel-swiper', 'laravel-swiper-thumbs' => $isThumbs]) }}
     id="{{ $domId }}"
     @if ($isThumbs) aria-hidden="true" @else role="region" aria-label="{{ $label ?? __('swiper::swiper.carousel') }}" @endif
     data-laravel-swiper="{{ json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}"
     data-swiper-hash="{{ $hash }}">
    <div class="swiper-wrapper">
        @foreach ($slides as $index => $image)
            <div class="swiper-slide">
                @if ($image->caption)<figure class="laravel-swiper-figure">@endif
                @php($href = $isThumbs ? null : \AmjadIqbal\LaravelSwiper\Support\SafeUrl::href($image->href))
                @if ($href)<a href="{{ $href }}" class="laravel-swiper-link">@endif
                <x-swiper.image
                    :src="$image->src"
                    :alt="$image->alt"
                    :srcset="$image->srcset"
                    :sizes="$isThumbs ? '160px' : $sizesAttr"
                    :width="$image->width"
                    :height="$image->height"
                    :lazy="$lazy"
                    :priority="$lazy && $index === 0 && ! $isThumbs"
                    :aspect="$aspect"
                    :fit="$fit" />
                @if ($href)</a>@endif
                @if ($image->caption)<figcaption class="laravel-swiper-caption">{{ $image->caption }}</figcaption></figure>@endif
            </div>
        @endforeach
        {{ $slot }}
    </div>
    @if ($navigation && ! $isThumbs)
        <button type="button" class="swiper-button-prev" aria-label="{{ __('swiper::swiper.previous') }}"></button>
        <button type="button" class="swiper-button-next" aria-label="{{ __('swiper::swiper.next') }}"></button>
    @endif
    @if ($paginationType && ! $isThumbs)
        <div class="swiper-pagination"></div>
    @endif
    @if ($scrollbar)
        <div class="swiper-scrollbar"></div>
    @endif
    @if ($autoplayDelay && ! $isThumbs)
        <button type="button" class="laravel-swiper-toggle" aria-label="{{ __('swiper::swiper.pause') }}">
            <svg class="laravel-swiper-icon-pause" viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false"><path d="M4 2h3v12H4zM9 2h3v12H9z" fill="currentColor"/></svg>
            <svg class="laravel-swiper-icon-play" viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false"><path d="M4 2l10 6-10 6z" fill="currentColor"/></svg>
        </button>
    @endif
</div>
@if ($renderThumbs)
<x-swiper
    :id="$domId.'-thumbs'"
    :images="$slides"
    :is-thumbs="true"
    :slides-per-view="4"
    :space-between="8"
    :breakpoints="[768 => 6]"
    :navigation="false"
    :pagination="false"
    :keyboard="false"
    :free-mode="true"
    :lazy="$lazy"
    :aspect="$aspect"
    :fit="$fit"
    class="laravel-swiper-thumbs-strip" />
</div>
@endif
