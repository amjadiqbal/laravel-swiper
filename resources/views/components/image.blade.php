<img {{ $attributes->class(['laravel-swiper-image', 'laravel-swiper-contain' => $fit === 'contain'])->merge(['style' => $aspect ? 'aspect-ratio: '.$aspect : null]) }}
     src="{{ $url }}"
     alt="{{ $alt }}"
     @if ($resolvedSrcset) srcset="{{ $resolvedSrcset }}" sizes="{{ $sizes ?? '100vw' }}" @endif
     @if ($width) width="{{ $width }}" @endif
     @if ($height) height="{{ $height }}" @endif
     @if ($priority) fetchpriority="high" loading="eager" @elseif ($lazy) loading="lazy" @endif
     decoding="async">@if ($lazy && ! $priority)<div class="swiper-lazy-preloader" aria-hidden="true"></div>@endif
