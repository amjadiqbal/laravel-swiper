<?php

namespace AmjadIqbal\LaravelSwiper\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * One slide: <x-swiper.slide>Anything</x-swiper.slide>
 */
class Slide extends Component
{
    public function __construct(
        public ?string $label = null,
    ) {}

    public function render(): View
    {
        return view('swiper::components.slide');
    }
}
