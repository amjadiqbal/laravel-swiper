<?php

use AmjadIqbal\LaravelSwiper\LaravelSwiperServiceProvider;
use AmjadIqbal\LaravelSwiper\Manager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;

function swiperState(string $html, int $nth = 0): array
{
    preg_match_all('/data-laravel-swiper="([^"]+)"/', $html, $m);

    return json_decode(html_entity_decode($m[1][$nth]), true);
}

beforeEach(function () {
    Storage::fake('public');
    config(['swiper.images.disk' => 'public']);
});

it('renders a carousel with typed props turned into Swiper options', function () {
    $html = Blade::render(<<<'BLADE'
        <x-swiper id="hero" label="Featured products" :slides-per-view="1" :space-between="16" loop effect="fade"
                  pagination="fraction" :breakpoints="[1024 => 3, 640 => 2]" :options="['speed' => 600]">
            <x-swiper.slide>One</x-swiper.slide>
            <x-swiper.slide label="Second">Two</x-swiper.slide>
        </x-swiper>
        BLADE);

    $config = swiperState($html)['config'];

    expect($html)->toContain('id="hero"')
        ->toContain('role="region"')
        ->toContain('aria-label="Featured products"')
        ->toContain('<div class="swiper-slide">One</div>')
        ->toContain('aria-label="Second"')
        ->toContain('<button type="button" class="swiper-button-prev" aria-label="Previous slide">')
        ->toContain('<div class="swiper-pagination"></div>')
        ->not->toContain('class="laravel-swiper-toggle"')
        ->and($config)->toMatchArray([
            'slidesPerView' => 1,
            'spaceBetween' => 16,
            'loop' => true,
            'effect' => 'fade',
            'fadeEffect' => ['crossFade' => true],
            'speed' => 600,
            'grabCursor' => true,
            'pagination' => ['type' => 'fraction', 'clickable' => true, 'dynamicBullets' => false],
            'breakpoints' => [640 => ['slidesPerView' => 2], 1024 => ['slidesPerView' => 3]],
        ])
        ->and($config)->not->toHaveKey('keyboard')
        ->and(swiperState($html)['keyboard'])->toBeTrue()
        ->and($config['a11y']['containerRoleDescriptionMessage'])->toBe('carousel');
});

it('rejects options Swiper does not have', function (string $blade, string $message) {
    expect(fn () => Blade::render($blade))->toThrow(Exception::class, $message);
})->with([
    'effect' => ['<x-swiper effect="spin" />', 'effect must be one of'],
    'direction' => ['<x-swiper direction="diagonal" />', 'direction must be horizontal or vertical'],
    'pagination' => ['<x-swiper pagination="dots" />', 'pagination must be true, false or one of'],
    'slides per view' => ['<x-swiper slides-per-view="many" />', 'slides-per-view must be a number or "auto"'],
]);

it('adds an accessible pause button when autoplay is on', function () {
    $html = Blade::render('<x-swiper :autoplay="4000" :pause-on-hover="false"><x-swiper.slide>A</x-swiper.slide></x-swiper>');
    $state = swiperState($html);

    expect($html)->toContain('class="laravel-swiper-toggle" aria-label="Pause slideshow"')
        ->and($state['config']['autoplay'])->toBe(['delay' => 4000, 'disableOnInteraction' => false, 'pauseOnMouseEnter' => false])
        ->and($state['labels'])->toBe(['pause' => 'Pause slideshow', 'play' => 'Play slideshow'])
        ->and($state['reducedMotion'])->toBeTrue();

    expect(swiperState(Blade::render('<x-swiper autoplay />'))['config']['autoplay']['delay'])->toBe(5000);
});

it('renders image slides from a collection with responsive, lazy images', function () {
    config(['swiper.images.srcset_url' => '{url}?w={width}', 'swiper.images.widths' => [480, 960]]);

    $html = Blade::render('<x-swiper :images="$images" :breakpoints="[768 => 2]" aspect="4/3" />', [
        'images' => collect([
            ['path' => 'p/one.jpg', 'alt' => 'First', 'caption' => 'Caption one', 'href' => '/one'],
            ['path' => 'p/two.jpg', 'alt' => 'Second'],
        ]),
    ]);

    $first = Storage::disk('public')->url('p/one.jpg');

    expect($html)->toContain('src="'.$first.'"')
        ->toContain('srcset="'.$first.'?w=480 480w, '.$first.'?w=960 960w"')
        ->toContain('sizes="(min-width: 768px) 50vw, 100vw"')
        ->toContain('alt="First"')
        ->toContain('style="aspect-ratio: 4/3"')
        ->toContain('<figcaption class="laravel-swiper-caption">Caption one</figcaption>')
        ->toContain('<a href="/one" class="laravel-swiper-link">')
        ->and(substr_count($html, 'fetchpriority="high" loading="eager"'))->toBe(1)
        ->and(substr_count($html, 'loading="lazy"'))->toBe(1)
        ->and(substr_count($html, 'swiper-lazy-preloader'))->toBe(1);
});

it('renders every image in a disk folder', function () {
    Storage::disk('public')->put('slides/b.jpg', 'x');
    Storage::disk('public')->put('slides/a.png', 'x');

    $html = Blade::render('<x-swiper folder="slides" disk="public" :lazy="false" />');

    expect($html)->toContain('alt="A"')->toContain('alt="B"')
        ->and(strpos($html, 'slides/a.png'))->toBeLessThan(strpos($html, 'slides/b.jpg'))
        ->and($html)->not->toContain('loading="lazy"');
});

it('renders a linked thumbs strip for image galleries', function () {
    $html = Blade::render('<x-swiper id="gallery" :images="[\'https://cdn.test/1.jpg\', \'https://cdn.test/2.jpg\']" thumbs />');

    $main = swiperState($html, 0);
    $strip = swiperState($html, 1);

    expect($html)->toContain('class="laravel-swiper-gallery"')
        ->toContain('id="gallery-thumbs"')
        ->toContain('aria-hidden="true"')
        ->and($main['thumbs'])->toBe('gallery-thumbs')
        ->and($strip['isThumbs'])->toBeTrue()
        ->and($strip['config'])->toMatchArray(['watchSlidesProgress' => true, 'freeMode' => ['enabled' => true, 'sticky' => false], 'a11y' => ['enabled' => false]])
        ->and(substr_count($html, 'https://cdn.test/1.jpg'))->toBe(2);
});

it('links to a hand-made thumbs carousel by id', function () {
    $html = Blade::render('<x-swiper thumbs="my-thumbs"><x-swiper.slide>A</x-swiper.slide></x-swiper>');

    expect(swiperState($html)['thumbs'])->toBe('my-thumbs');
});

it('keeps the same id and hash for the same content', function () {
    $render = fn (string $text) => Blade::render('<x-swiper><x-swiper.slide>'.$text.'</x-swiper.slide></x-swiper>');

    preg_match('/id="(swiper-[a-f0-9]+)".*data-swiper-hash="([a-f0-9]+)"/s', $render('A'), $first);
    app()->forgetScopedInstances();
    preg_match('/id="(swiper-[a-f0-9]+)".*data-swiper-hash="([a-f0-9]+)"/s', $render('A'), $again);
    preg_match('/data-swiper-hash="([a-f0-9]+)"/', $render('B'), $changed);

    expect($first[1])->toBe($again[1])
        ->and($first[2])->toBe($again[2])
        ->and($changed[1])->not->toBe($first[2]);
});

it('prints the CDN assets with SRI once per request', function () {
    $html = Blade::render('<x-swiper /><x-swiper />');

    expect(substr_count($html, 'swiper-bundle.min.js'))->toBe(1)
        ->and($html)->toContain('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@14.3.0/swiper-bundle.min.css" integrity="sha384-xkKzAc7JSlGq3SLCZHugx/i0gfYZf6kaFzg3v9/wz0HtsAFYPLz7aUvCFTac8KBk" crossorigin="anonymous" data-laravel-swiper-asset data-navigate-once>')
        ->toContain('integrity="sha384-nimz5qRuF8SOYqiuzkhgKleYiz6jIj9pUhB/LlAAsbB6I3fI9v9s5RMwMTzEyOAY"')
        ->toContain('window.LaravelSwiper');
});

it('serves local assets or none at all', function () {
    config(['swiper.assets.mode' => 'local']);
    $local = Blade::render('@swiperAssets');

    app()->forgetScopedInstances();
    config(['swiper.assets.mode' => 'none']);
    $none = (string) app(Manager::class)->assets();

    expect($local)->toContain('href="'.asset('vendor/laravel-swiper/swiper-bundle.min.css').'"')
        ->toContain('src="'.asset('vendor/laravel-swiper/swiper-bundle.min.js').'"')
        ->not->toContain('cdn.jsdelivr.net')
        ->and($none)->not->toContain('swiper-bundle')
        ->toContain('window.LaravelSwiper');
});

it('ships the vendored Swiper build that matches the CDN hashes', function () {
    $hash = fn (string $file) => 'sha384-'.base64_encode(hash_file('sha384', __DIR__.'/../../resources/dist/'.$file, true));

    expect($hash('swiper-bundle.min.js'))->toBe(config('swiper.assets.js_integrity'))
        ->and($hash('swiper-bundle.min.css'))->toBe(config('swiper.assets.css_integrity'));
});

it('adds the CSP nonce to inline tags', function () {
    Vite::useCspNonce('abc123');

    expect((string) app(Manager::class)->assets())->toContain('<script nonce="abc123" data-laravel-swiper-asset data-navigate-once data-swiper-src="https://cdn.jsdelivr.net/npm/swiper@14.3.0/swiper-bundle.min.js" data-swiper-integrity="sha384-')
        ->toContain('<style nonce="abc123" data-laravel-swiper-asset data-navigate-once>');
});

it('prints no assets in a Livewire update request', function () {
    request()->headers->set('X-Livewire', '1');

    expect((string) app(Manager::class)->assets())->toBe('');
});

it('uses the translated screen-reader messages', function () {
    app('translator')->addLines(['swiper.next' => 'Diapositive suivante'], 'fr', 'swiper');
    app()->setLocale('fr');

    $html = Blade::render('<x-swiper />');

    expect($html)->toContain('aria-label="Diapositive suivante"')
        ->and(swiperState($html)['config']['a11y']['nextSlideMessage'])->toBe('Diapositive suivante');
});

it('registers an about entry with version and support link only', function () {
    Artisan::call('about', ['--only' => 'swiper', '--json' => true]);
    $about = json_decode(Artisan::output(), true);

    expect($about['swiper'])->toHaveKeys(['version', 'assets', 'support'])
        ->and($about['swiper']['support'])->toBe(LaravelSwiperServiceProvider::SUPPORT_URL)
        ->and($about['swiper']['assets'])->toBe('cdn');
});
