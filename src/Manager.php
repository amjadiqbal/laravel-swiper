<?php

namespace AmjadIqbal\LaravelSwiper;

use AmjadIqbal\LaravelSwiper\Support\Image;
use Closure;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\HtmlString;

/**
 * Bound as a scoped singleton, so "assets already printed" resets per request
 * (and per Octane request).
 */
class Manager
{
    protected bool $assetsRendered = false;

    /** @var (Closure(Image, int): ?string)|null */
    protected static ?Closure $srcsetResolver = null;

    /**
     * Build srcset URLs yourself: Swiper::srcsetUsing(fn (Image $image, int $width) => "...?w=$width").
     * Return null for an image to leave it without a srcset.
     *
     * @param  (Closure(Image, int): ?string)|null  $resolver
     */
    public function srcsetUsing(?Closure $resolver): void
    {
        static::$srcsetResolver = $resolver;
    }

    public function srcsetFor(Image $image): ?string
    {
        $widths = array_values(array_filter(
            array_map('intval', (array) config('swiper.images.widths', [])),
            fn (int $width) => $width > 0,
        ));

        if ($widths === [] || str_starts_with($image->src, 'data:') || str_ends_with(strtolower(parse_url($image->src, PHP_URL_PATH) ?: ''), '.svg')) {
            return null;
        }

        $template = config('swiper.images.srcset_url');

        if (static::$srcsetResolver === null && (! is_string($template) || $template === '')) {
            return null;
        }

        $entries = [];

        foreach ($widths as $width) {
            $url = static::$srcsetResolver !== null
                ? (static::$srcsetResolver)($image, $width)
                : strtr((string) $template, [
                    '{url}' => $image->src,
                    '{path}' => (string) $image->path,
                    '{width}' => (string) $width,
                ]);

            if (! is_string($url) || $url === '') {
                return null;
            }

            $entries[] = $url.' '.$width.'w';
        }

        return implode(', ', $entries);
    }

    /**
     * Swiper's stylesheet and script, the package CSS and the runtime, printed
     * once per request. Every <x-swiper> calls this, so you only need
     *
     * @swiperAssets when you want the tags in the <head> (recommended with
     * Livewire and wire:navigate).
     */
    public function assets(): HtmlString
    {
        if ($this->assetsRendered) {
            return new HtmlString('');
        }

        $this->assetsRendered = true;

        // A Livewire update can't run scripts, and the page already has them (the runtime moved them to <head>).
        if (app()->bound('request') && request()->hasHeader('X-Livewire')) {
            return new HtmlString('');
        }

        $assets = (array) config('swiper.assets', []);
        $mode = $assets['mode'] ?? 'cdn';
        $nonce = Vite::cspNonce();
        $nonceAttr = $nonce ? ' nonce="'.e($nonce).'"' : '';
        $marker = ' data-laravel-swiper-asset data-navigate-once';
        $html = '';

        [$css, $js, $cssIntegrity, $jsIntegrity] = match ($mode) {
            'local' => [
                asset(trim((string) ($assets['local_path'] ?? 'vendor/laravel-swiper'), '/').'/swiper-bundle.min.css'),
                asset(trim((string) ($assets['local_path'] ?? 'vendor/laravel-swiper'), '/').'/swiper-bundle.min.js'),
                null,
                null,
            ],
            'cdn' => [$assets['css'] ?? null, $assets['js'] ?? null, $assets['css_integrity'] ?? null, $assets['js_integrity'] ?? null],
            default => [null, null, null, null],
        };

        if (is_string($css) && $css !== '') {
            $html .= '<link rel="stylesheet" href="'.e($css).'"'.$this->integrity($cssIntegrity).$marker.'>'."\n";
        }

        $html .= '<style'.$nonceAttr.$marker.'>'.trim((string) file_get_contents(__DIR__.'/../resources/css/laravel-swiper.css')).'</style>'."\n";

        // The runtime loads Swiper itself (once, async), so a re-run after wire:navigate costs nothing.
        $swiperAttrs = is_string($js) && $js !== ''
            ? ' data-swiper-src="'.e($js).'"'.(is_string($jsIntegrity) && $jsIntegrity !== '' ? ' data-swiper-integrity="'.e($jsIntegrity).'"' : '')
            : '';

        $html .= '<script'.$nonceAttr.$marker.$swiperAttrs.'>'.trim((string) file_get_contents(__DIR__.'/../resources/js/runtime.js')).'</script>'."\n";

        return new HtmlString($html);
    }

    public function assetsRendered(): bool
    {
        return $this->assetsRendered;
    }

    protected function integrity(mixed $hash): string
    {
        return is_string($hash) && $hash !== ''
            ? ' integrity="'.e($hash).'" crossorigin="anonymous"'
            : '';
    }
}
