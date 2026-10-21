<?php

namespace AmjadIqbal\LaravelSwiper\View\Components;

use AmjadIqbal\LaravelSwiper\Manager;
use AmjadIqbal\LaravelSwiper\Support\Image as ImageSlide;
use AmjadIqbal\LaravelSwiper\Support\ImageResolver;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * A responsive, lazy-loading <img> for use inside <x-swiper.slide>.
 * <x-swiper.image src="products/shoe.jpg" disk="s3" alt="Red running shoe" />
 */
class Image extends Component
{
    public string $url;

    public ?string $resolvedSrcset;

    public function __construct(
        public string $src,
        public string $alt = '',
        public ?string $srcset = null,
        public ?string $sizes = null,
        public ?int $width = null,
        public ?int $height = null,
        public ?string $disk = null,
        public bool $lazy = true,
        public bool $priority = false,
        public ?string $aspect = null,
        public string $fit = 'cover',
    ) {
        $this->url = app(ImageResolver::class)->url($src, $disk);
        $this->resolvedSrcset = $srcset ?? app(Manager::class)->srcsetFor(new ImageSlide($this->url, $alt, path: $src === $this->url ? null : $src));
    }

    public function render(): View
    {
        return view('swiper::components.image');
    }
}
