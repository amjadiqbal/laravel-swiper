<?php

namespace AmjadIqbal\LaravelSwiper;

use AmjadIqbal\LaravelSwiper\Support\ImageResolver;
use AmjadIqbal\LaravelSwiper\View\Components\Image;
use AmjadIqbal\LaravelSwiper\View\Components\Slide;
use AmjadIqbal\LaravelSwiper\View\Components\Swiper;
use Composer\InstalledVersions;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class LaravelSwiperServiceProvider extends ServiceProvider
{
    public const SUPPORT_URL = 'https://amjad.com.pk/contact?utm_source=artisan&utm_medium=about&utm_campaign=laravel-swiper';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/swiper.php', 'swiper');

        $this->app->scoped(Manager::class);
        $this->app->bind(ImageResolver::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'swiper');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'swiper');

        Blade::component('swiper', Swiper::class);
        Blade::component('swiper.slide', Slide::class);
        Blade::component('swiper.image', Image::class);
        Blade::directive('swiperAssets', fn () => '<?php echo app(\AmjadIqbal\LaravelSwiper\Manager::class)->assets(); ?>');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/swiper.php' => config_path('swiper.php'),
            ], 'swiper-config');

            $this->publishes([
                __DIR__.'/../resources/dist/swiper-bundle.min.js' => public_path('vendor/laravel-swiper/swiper-bundle.min.js'),
                __DIR__.'/../resources/dist/swiper-bundle.min.css' => public_path('vendor/laravel-swiper/swiper-bundle.min.css'),
                __DIR__.'/../resources/dist/LICENSE-swiper.txt' => public_path('vendor/laravel-swiper/LICENSE-swiper.txt'),
            ], 'swiper-assets');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/swiper'),
            ], 'swiper-views');

            $this->publishes([
                __DIR__.'/../lang' => $this->app->langPath('vendor/swiper'),
            ], 'swiper-translations');
        }

        AboutCommand::add('Swiper', fn () => [
            'Version' => InstalledVersions::isInstalled('amjadiqbal/laravel-swiper')
                ? (string) InstalledVersions::getPrettyVersion('amjadiqbal/laravel-swiper')
                : 'unknown',
            'Assets' => (string) config('swiper.assets.mode', 'cdn'),
            'Support' => self::SUPPORT_URL,
        ]);
    }
}
