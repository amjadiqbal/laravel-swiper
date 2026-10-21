<?php

namespace AmjadIqbal\LaravelSwiper\View\Components;

use AmjadIqbal\LaravelSwiper\Manager;
use AmjadIqbal\LaravelSwiper\Support\Image as ImageSlide;
use AmjadIqbal\LaravelSwiper\Support\ImageResolver;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;

class Swiper extends Component
{
    public const EFFECTS = ['slide', 'fade', 'cube', 'coverflow', 'flip', 'creative', 'cards'];

    public const PAGINATION = ['bullets', 'dynamic', 'fraction', 'progressbar'];

    /**
     * Swiper options handed to the browser.
     *
     * @var array<string, mixed>
     */
    public array $config;

    /** @var list<ImageSlide> */
    public array $slides = [];

    public ?string $paginationType;

    public ?int $autoplayDelay;

    /** Id of the thumbs carousel this one drives, if any. */
    public ?string $thumbsTarget = null;

    /** Render the thumbs strip ourselves (image carousels with thumbs="true"). */
    public bool $renderThumbs = false;

    /** Not called $loop: Blade's @foreach owns that variable in views. */
    public bool $loops;

    /**
     * @param  iterable<mixed>|null  $images  paths, URLs, arrays, models or Spatie Media items; rendered as image slides
     * @param  string|null  $folder  render every image in this folder of $disk
     * @param  string|Closure|null  $imageField  attribute (dot notation) or closure giving each item's path/URL/Media
     * @param  array<int|string, int|float|string|array<string, mixed>>  $breakpoints  [640 => 2, 1024 => 3] or full Swiper breakpoint arrays
     * @param  array<string, mixed>  $options  any Swiper option, applied last
     */
    public function __construct(
        public ?string $id = null,
        public ?string $label = null,
        public int|float|string $slidesPerView = 1,
        public int|string $spaceBetween = 0,
        bool $loop = false,
        public bool $rewind = false,
        public int $speed = 300,
        public string $direction = 'horizontal',
        public string $effect = 'slide',
        public bool $centered = false,
        public bool $autoHeight = false,
        public bool $navigation = true,
        bool|string $pagination = true,
        public bool $scrollbar = false,
        bool|int $autoplay = false,
        public bool $pauseOnHover = true,
        public bool $keyboard = true,
        public bool $mousewheel = false,
        public bool $freeMode = false,
        public bool $lazy = true,
        bool|string $thumbs = false,
        public array $breakpoints = [],
        public array $options = [],
        ?iterable $images = null,
        ?string $folder = null,
        public ?string $disk = null,
        string|Closure|null $imageField = null,
        string|Closure|null $altField = null,
        string|Closure|null $captionField = null,
        string|Closure|null $linkField = null,
        public ?string $conversion = null,
        public ?string $sizes = null,
        public ?string $aspect = '16/9',
        public string $fit = 'cover',
        public bool $isThumbs = false,
    ) {
        if (! in_array($effect, self::EFFECTS, true)) {
            throw new InvalidArgumentException('<x-swiper> effect must be one of: '.implode(', ', self::EFFECTS).". Got \"{$effect}\".");
        }

        if (! in_array($direction, ['horizontal', 'vertical'], true)) {
            throw new InvalidArgumentException("<x-swiper> direction must be horizontal or vertical. Got \"{$direction}\".");
        }

        if (is_string($slidesPerView) && $slidesPerView !== 'auto' && ! is_numeric($slidesPerView)) {
            throw new InvalidArgumentException("<x-swiper> slides-per-view must be a number or \"auto\". Got \"{$slidesPerView}\".");
        }

        if (is_string($pagination) && ! in_array($pagination, self::PAGINATION, true)) {
            throw new InvalidArgumentException('<x-swiper> pagination must be true, false or one of: '.implode(', ', self::PAGINATION).". Got \"{$pagination}\".");
        }

        if (! in_array($fit, ['cover', 'contain'], true)) {
            throw new InvalidArgumentException("<x-swiper> fit must be cover or contain. Got \"{$fit}\".");
        }

        $this->loops = $loop;
        $this->paginationType = $pagination === true ? 'bullets' : ($pagination === false ? null : $pagination);
        $this->autoplayDelay = $autoplay === true ? 5000 : ($autoplay === false || $autoplay <= 0 ? null : $autoplay);

        $resolver = app(ImageResolver::class);

        if ($folder !== null) {
            $this->slides = $resolver->folder($folder, $disk, $altField);
        } elseif ($images !== null) {
            $this->slides = $resolver->resolve($images, $disk, $imageField, $altField, $captionField, $linkField, $conversion);
        }

        if (is_string($thumbs) && $thumbs !== '') {
            $this->thumbsTarget = $thumbs;
        } elseif ($thumbs === true) {
            $this->renderThumbs = true;
        }

        $this->config = $this->buildConfig();
    }

    /** @return array<string, mixed> */
    protected function buildConfig(): array
    {
        $config = [
            'slidesPerView' => is_numeric($this->slidesPerView) ? $this->slidesPerView + 0 : $this->slidesPerView,
            'spaceBetween' => is_numeric($this->spaceBetween) ? $this->spaceBetween + 0 : $this->spaceBetween,
            'speed' => $this->speed,
            'direction' => $this->direction,
            'effect' => $this->effect,
        ];

        foreach (['loop' => $this->loops, 'rewind' => $this->rewind, 'centeredSlides' => $this->centered, 'autoHeight' => $this->autoHeight] as $key => $on) {
            if ($on) {
                $config[$key] = true;
            }
        }

        if ($this->effect === 'fade') {
            $config['fadeEffect'] = ['crossFade' => true];
        }

        if ($this->mousewheel) {
            $config['mousewheel'] = ['enabled' => true, 'forceToAxis' => true];
        }

        if ($this->freeMode) {
            $config['freeMode'] = ['enabled' => true, 'sticky' => false];
        }

        if ($this->paginationType !== null) {
            $config['pagination'] = [
                'type' => $this->paginationType === 'dynamic' ? 'bullets' : $this->paginationType,
                'clickable' => true,
                'dynamicBullets' => $this->paginationType === 'dynamic',
            ];
        }

        if ($this->scrollbar) {
            $config['scrollbar'] = ['draggable' => true, 'hide' => false];
        }

        if ($this->autoplayDelay !== null) {
            $config['autoplay'] = [
                'delay' => $this->autoplayDelay,
                'disableOnInteraction' => false,
                'pauseOnMouseEnter' => $this->pauseOnHover,
            ];
        }

        if ($this->breakpoints !== []) {
            $config['breakpoints'] = $this->normaliseBreakpoints($this->breakpoints);
        }

        if ($this->isThumbs) {
            $config['watchSlidesProgress'] = true;
            $config['slideToClickedSlide'] = false;
        }

        $config['a11y'] = [
            'enabled' => true,
            'prevSlideMessage' => __('swiper::swiper.previous'),
            'nextSlideMessage' => __('swiper::swiper.next'),
            'firstSlideMessage' => __('swiper::swiper.first'),
            'lastSlideMessage' => __('swiper::swiper.last'),
            'paginationBulletMessage' => __('swiper::swiper.go_to'),
            'slideLabelMessage' => __('swiper::swiper.slide_label'),
            'containerRoleDescriptionMessage' => __('swiper::swiper.roledescription'),
            'itemRoleDescriptionMessage' => __('swiper::swiper.slide'),
        ];

        if ($this->isThumbs) {
            // The strip repeats the main carousel's images: hidden from screen readers, which use the main carousel.
            $config['a11y'] = ['enabled' => false];
        }

        return array_replace_recursive((array) config('swiper.defaults', []), $config, $this->options);
    }

    /**
     * @param  array<int|string, int|float|string|array<string, mixed>>  $breakpoints
     * @return array<int|string, array<string, mixed>>
     */
    protected function normaliseBreakpoints(array $breakpoints): array
    {
        $out = [];

        foreach ($breakpoints as $width => $value) {
            $out[$width] = is_array($value) ? $value : ['slidesPerView' => is_numeric($value) ? $value + 0 : $value];
        }

        ksort($out, SORT_NATURAL);

        return $out;
    }

    /**
     * The img sizes attribute: given, or worked out from slides-per-view and breakpoints.
     */
    public function imageSizes(): string
    {
        if ($this->sizes !== null) {
            return $this->sizes;
        }

        $vw = fn (mixed $perView): string => is_numeric($perView) && $perView + 0 > 0
            ? max(1, (int) ceil(100 / ($perView + 0))).'vw'
            : '100vw';

        $parts = [];
        $breakpoints = $this->normaliseBreakpoints($this->breakpoints);
        krsort($breakpoints, SORT_NATURAL);

        foreach ($breakpoints as $width => $settings) {
            if (is_numeric($width) && isset($settings['slidesPerView'])) {
                $parts[] = '(min-width: '.((int) $width).'px) '.$vw($settings['slidesPerView']);
            }
        }

        $parts[] = $vw($this->slidesPerView);

        return implode(', ', $parts);
    }

    /** Stable id: given, or derived from the content so Livewire re-renders keep it. */
    public function resolvedId(string $hash): string
    {
        return $this->id ?? 'swiper-'.substr($hash, 0, 10);
    }

    public function assets(): string
    {
        return (string) app(Manager::class)->assets();
    }

    public function render(): View
    {
        return view('swiper::components.swiper');
    }
}
