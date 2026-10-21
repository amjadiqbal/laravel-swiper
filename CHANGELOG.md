# Changelog

All notable changes to `amjadiqbal/laravel-swiper` are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- `<x-swiper>`, `<x-swiper.slide>` and `<x-swiper.image>` Blade components for Swiper 14.3.0, with typed,
  validated props for slides per view, spacing, breakpoints, loop, rewind, speed, direction, all seven
  effects, centering, auto height, navigation, four pagination styles, scrollbar, autoplay, mousewheel,
  free mode, lazy loading and thumbs, plus an `options` array for any other Swiper parameter.
- Image carousels from paths, URLs, arrays, Eloquent models (named fields, dot notation or closures),
  Spatie Media Library items (their own srcset) or every image in a storage disk folder.
- Responsive images: `srcset` from a URL pattern or `Swiper::srcsetUsing()`, `sizes` worked out from the
  breakpoints, fixed aspect ratio, lazy loading with a high-priority first image.
- Livewire 3/4 support: unchanged carousels survive a morph untouched, changed ones are rebuilt on the
  same slide (and stay paused if the visitor paused them), `wire:navigate` and back/forward work, and
  the `swiper:go` / `swiper:slide-change` / `swiper:init` events.
- Accessibility: labelled region, `<button>` arrows, translated screen-reader messages, a pause button
  for autoplay, pause on focus and hover, `prefers-reduced-motion` support, and arrow keys scoped to the
  carousel in use.
- Assets from jsDelivr with SRI (default), from your own server (`--tag=swiper-assets`), or your own
  bundle; CSP nonce support; `@swiperAssets`; a `php artisan about` entry.
