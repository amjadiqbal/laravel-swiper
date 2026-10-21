<?php

namespace AmjadIqbal\LaravelSwiper\Facades;

use AmjadIqbal\LaravelSwiper\Manager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void srcsetUsing(?\Closure $resolver)
 * @method static string|null srcsetFor(\AmjadIqbal\LaravelSwiper\Support\Image $image)
 * @method static \Illuminate\Support\HtmlString assets()
 *
 * @see Manager
 */
class Swiper extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Manager::class;
    }
}
