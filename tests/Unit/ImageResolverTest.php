<?php

use AmjadIqbal\LaravelSwiper\Facades\Swiper;
use AmjadIqbal\LaravelSwiper\Support\Image;
use AmjadIqbal\LaravelSwiper\Support\ImageResolver;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    config(['swiper.images.disk' => 'public']);
    Swiper::srcsetUsing(null);
});

function resolver(): ImageResolver
{
    return app(ImageResolver::class);
}

it('resolves stored paths through the disk and leaves URLs alone', function () {
    $images = resolver()->resolve(['photos/a.jpg', 'https://cdn.test/b.jpg', '/img/c.jpg', '']);

    expect($images)->toHaveCount(3)
        ->and($images[0]->src)->toBe(Storage::disk('public')->url('photos/a.jpg'))
        ->and($images[0]->path)->toBe('photos/a.jpg')
        ->and($images[1]->src)->toBe('https://cdn.test/b.jpg')
        ->and($images[1]->path)->toBeNull()
        ->and($images[2]->src)->toBe('/img/c.jpg');
});

it('reads arrays and models with the usual field names', function () {
    $model = new class
    {
        public string $cover = 'covers/x.png';

        public string $title = 'Cover X';

        public string $caption = 'Shot at dawn';
    };

    [$fromArray, $fromModel] = resolver()->resolve([
        ['src' => 'https://cdn.test/a.jpg', 'alt' => 'A', 'href' => '/a', 'width' => 800, 'height' => 600, 'srcset' => 'a-480.jpg 480w'],
        $model,
    ]);

    expect($fromArray)->toEqual(new Image('https://cdn.test/a.jpg', 'A', 'a-480.jpg 480w', null, '/a', 800, 600))
        ->and($fromModel->src)->toBe(Storage::disk('public')->url('covers/x.png'))
        ->and($fromModel->alt)->toBe('Cover X')
        ->and($fromModel->caption)->toBe('Shot at dawn');
});

it('uses named fields and closures', function () {
    $items = [
        ['product' => ['image_path' => 'p/1.jpg', 'name' => 'Shoe'], 'url' => '/shoe'],
    ];

    [$image] = resolver()->resolve($items, null, 'product.image_path', fn ($item) => 'Red '.$item['product']['name'], null, 'url');

    expect($image->src)->toBe(Storage::disk('public')->url('p/1.jpg'))
        ->and($image->alt)->toBe('Red Shoe')
        ->and($image->href)->toBe('/shoe');
});

it('skips items without an image', function () {
    expect(resolver()->resolve([['title' => 'no image'], 42, null]))->toBe([]);
});

it('takes Spatie Media items with their own srcset', function () {
    $media = new class
    {
        public string $name = 'Beach';

        public function getUrl(string $conversion = ''): string
        {
            return 'https://media.test/beach'.($conversion ? "-$conversion" : '').'.jpg';
        }

        public function getSrcset(string $conversion = ''): string
        {
            return 'https://media.test/beach-320.jpg 320w';
        }

        public function getCustomProperty(string $name): mixed
        {
            return $name === 'alt' ? 'Empty beach at sunset' : null;
        }
    };

    [$direct] = resolver()->resolve([$media], conversion: 'large');
    [$viaModel] = resolver()->resolve([['photo' => $media, 'title' => 'Model title']]);

    expect($direct->src)->toBe('https://media.test/beach-large.jpg')
        ->and($direct->srcset)->toBe('https://media.test/beach-320.jpg 320w')
        ->and($direct->alt)->toBe('Empty beach at sunset')
        ->and($viaModel->alt)->toBe('Model title');
});

it('lists a disk folder in natural order, images only, with readable alt text', function () {
    Storage::disk('public')->put('gallery/img10.jpg', 'x');
    Storage::disk('public')->put('gallery/img2.JPG', 'x');
    Storage::disk('public')->put('gallery/sunset-over_lake.webp', 'x');
    Storage::disk('public')->put('other/01-harbour-at-noon.jpg', 'x');
    Storage::disk('public')->put('other/2024.jpg', 'x');
    Storage::disk('public')->put('gallery/notes.txt', 'x');
    Storage::disk('public')->put('gallery/sub/deep.jpg', 'x');

    $images = resolver()->folder('gallery');

    expect(array_map(fn (Image $i) => $i->path, $images))->toBe(['gallery/img2.JPG', 'gallery/img10.jpg', 'gallery/sunset-over_lake.webp'])
        ->and($images[2]->alt)->toBe('Sunset over lake')
        ->and(array_map(fn (Image $i) => $i->alt, resolver()->folder('other')))->toBe(['Harbour at noon', '2024'])
        ->and(resolver()->folder('gallery', null, fn ($path) => 'Photo '.basename($path))[0]->alt)->toBe('Photo img2.JPG');
});

it('builds a srcset from the configured URL pattern', function () {
    config(['swiper.images.srcset_url' => '{url}?w={width}', 'swiper.images.widths' => [400, 800]]);

    [$image, $svg] = resolver()->resolve(['https://cdn.test/a.jpg', 'https://cdn.test/logo.svg']);

    expect($image->srcset)->toBe('https://cdn.test/a.jpg?w=400 400w, https://cdn.test/a.jpg?w=800 800w')
        ->and($svg->srcset)->toBeNull();
});

it('lets the app build srcset URLs with a closure', function () {
    config(['swiper.images.widths' => [300]]);
    Swiper::srcsetUsing(fn (Image $image, int $width) => '/resize/'.$width.'/'.$image->path);

    [$image] = resolver()->resolve(['photos/a.jpg']);

    expect($image->srcset)->toBe('/resize/300/photos/a.jpg 300w');

    Swiper::srcsetUsing(null);
});
