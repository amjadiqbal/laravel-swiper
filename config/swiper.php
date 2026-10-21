<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Swiper assets
    |--------------------------------------------------------------------------
    |
    | The first <x-swiper> on a page prints Swiper's stylesheet and script plus
    | this package's small runtime (or put @swiperAssets in your layout's
    | <head> yourself, which is what you want with Livewire).
    |
    | "mode":
    |   "cdn"   - Swiper 14.3.0 from jsDelivr with Subresource Integrity hashes.
    |   "local" - the same files served from your app, no third-party request.
    |             Run: php artisan vendor:publish --tag=swiper-assets
    |   "none"  - you bundle Swiper yourself with Vite; only the runtime is
    |             printed. In resources/js/app.js:
    |
    |                 import Swiper from 'swiper/bundle';
    |                 import 'swiper/css/bundle';
    |                 window.Swiper = Swiper;
    |
    | If you change a CDN URL, update its integrity hash or set it to null.
    |
    */

    'assets' => [
        'mode' => env('SWIPER_ASSETS', 'cdn'),
        'js' => 'https://cdn.jsdelivr.net/npm/swiper@14.3.0/swiper-bundle.min.js',
        'js_integrity' => 'sha384-nimz5qRuF8SOYqiuzkhgKleYiz6jIj9pUhB/LlAAsbB6I3fI9v9s5RMwMTzEyOAY',
        'css' => 'https://cdn.jsdelivr.net/npm/swiper@14.3.0/swiper-bundle.min.css',
        'css_integrity' => 'sha384-xkKzAc7JSlGq3SLCZHugx/i0gfYZf6kaFzg3v9/wz0HtsAFYPLz7aUvCFTac8KBk',
        'local_path' => 'vendor/laravel-swiper',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Swiper options
    |--------------------------------------------------------------------------
    |
    | Applied to every carousel before its own props and its :options array.
    | Keys are Swiper option names: https://swiperjs.com/swiper-api#parameters
    |
    */

    'defaults' => [
        'grabCursor' => true,
        'watchOverflow' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Accessibility
    |--------------------------------------------------------------------------
    |
    | With "reduced_motion" on, visitors whose system asks for reduced motion
    | get no autoplay and instant slide changes. Screen-reader messages come
    | from lang/vendor/swiper (publish with --tag=swiper-translations).
    |
    */

    'reduced_motion' => true,

    /*
    |--------------------------------------------------------------------------
    | Images
    |--------------------------------------------------------------------------
    |
    | "disk": where relative image paths live (null = your default disk).
    |
    | "srcset_url": build a srcset for every image from a URL pattern, for
    | image CDNs and resizers (imgix, Cloudinary, Cloudflare Images, Bunny,
    | Glide...). {url} is the full image URL, {path} the stored path and
    | {width} each entry of "widths". Example: '{url}?w={width}&fm=webp'.
    | Null means no generated srcset (Spatie Media Library items still get
    | their own). For full control: Swiper::srcsetUsing(fn ($image, $width) => ...).
    |
    | "folder_cache": seconds to cache the file list of a :folder carousel
    | (useful on S3). Null or 0 lists the folder on every render.
    |
    */

    'images' => [
        'disk' => null,
        'srcset_url' => null,
        'widths' => [480, 800, 1200, 1600, 2000],
        'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg'],
        'folder_cache' => null,
    ],

];
