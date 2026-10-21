<?php

namespace AmjadIqbal\LaravelSwiper\Support;

use AmjadIqbal\LaravelSwiper\Manager;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Turns what an app has (paths, URLs, arrays, Eloquent models, Spatie Media
 * items, a disk folder) into a list of Image slides.
 */
class ImageResolver
{
    /** Fields tried, in order, when no field is named. */
    public const IMAGE_FIELDS = ['image', 'src', 'url', 'path', 'photo', 'cover'];

    public const ALT_FIELDS = ['alt', 'title', 'name'];

    public function __construct(protected Manager $manager) {}

    /**
     * @param  iterable<mixed>|null  $items
     * @param  string|Closure|null  $imageField  attribute (dot notation) or fn ($item) => path|url|Media
     * @return list<Image>
     */
    public function resolve(
        ?iterable $items,
        ?string $disk = null,
        string|Closure|null $imageField = null,
        string|Closure|null $altField = null,
        string|Closure|null $captionField = null,
        string|Closure|null $linkField = null,
        ?string $conversion = null,
    ): array {
        $images = [];

        foreach ($items ?? [] as $item) {
            $image = $this->one($item, $disk, $imageField, $altField, $captionField, $linkField, $conversion);

            if ($image !== null) {
                $images[] = $image;
            }
        }

        return $images;
    }

    /**
     * Every image file directly inside $folder on $disk, in natural name order.
     *
     * @param  string|Closure|null  $altField  fn (string $path) => alt text; default: the file name in words, without a leading sort number
     * @return list<Image>
     */
    public function folder(string $folder, ?string $disk = null, string|Closure|null $altField = null): array
    {
        $disk = $this->disk($disk);
        $extensions = array_map('strtolower', (array) config('swiper.images.extensions', []));
        $ttl = (int) config('swiper.images.folder_cache', 0);

        $list = function () use ($folder, $disk, $extensions): array {
            $files = array_values(array_filter(
                Storage::disk($disk)->files(trim($folder, '/')),
                fn (string $file) => in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), $extensions, true),
            ));
            natcasesort($files);

            return array_values($files);
        };

        /** @var list<string> $files */
        $files = $ttl > 0
            ? Cache::remember('laravel-swiper:folder:'.($disk ?? 'default').':'.$folder, $ttl, $list)
            : $list();

        return array_map(function (string $path) use ($disk, $altField) {
            $alt = $altField instanceof Closure
                ? (string) $altField($path)
                : Str::of(pathinfo($path, PATHINFO_FILENAME))->replaceMatches('/^\d+[-_. ]+(?=\D)/', '')->replaceMatches('/[-_]+/', ' ')->trim()->ucfirst()->toString();

            return $this->withSrcset(new Image($this->url($path, $disk), $alt, path: $path));
        }, $files);
    }

    protected function one(
        mixed $item,
        ?string $disk,
        string|Closure|null $imageField,
        string|Closure|null $altField,
        string|Closure|null $captionField,
        string|Closure|null $linkField,
        ?string $conversion,
    ): ?Image {
        if ($item instanceof Image) {
            return $item;
        }

        if (is_string($item)) {
            return $item === '' ? null : $this->withSrcset(new Image($this->url($item, $disk), path: $this->isUrl($item) ? null : $item));
        }

        if ($this->isMedia($item)) {
            return $this->fromMedia($item, $conversion, $this->text($item, $altField, []), $this->text($item, $captionField, []), $this->text($item, $linkField, []));
        }

        if (! is_array($item) && ! is_object($item)) {
            return null;
        }

        $alt = $this->text($item, $altField, self::ALT_FIELDS) ?? '';
        $caption = $this->text($item, $captionField, ['caption']);
        $href = $this->text($item, $linkField, ['href', 'link']);
        $source = $this->value($item, $imageField, self::IMAGE_FIELDS);

        if ($this->isMedia($source)) {
            return $this->fromMedia($source, $conversion, $alt, $caption, $href);
        }

        if (! is_string($source) || $source === '') {
            return null;
        }

        $srcset = data_get($item, 'srcset');
        $width = data_get($item, 'width');
        $height = data_get($item, 'height');

        $image = new Image(
            src: $this->url($source, $disk),
            alt: $alt,
            srcset: is_string($srcset) && $srcset !== '' ? $srcset : null,
            caption: $caption,
            href: $href,
            width: is_numeric($width) ? (int) $width : null,
            height: is_numeric($height) ? (int) $height : null,
            path: $this->isUrl($source) ? null : $source,
        );

        return $image->srcset === null ? $this->withSrcset($image) : $image;
    }

    public function url(string $pathOrUrl, ?string $disk = null): string
    {
        if ($this->isUrl($pathOrUrl)) {
            return $pathOrUrl;
        }

        return Storage::disk($this->disk($disk))->url($pathOrUrl);
    }

    protected function isUrl(string $value): bool
    {
        return (bool) preg_match('#^(https?:)?//|^/|^data:#i', $value);
    }

    protected function disk(?string $disk): ?string
    {
        $disk ??= config('swiper.images.disk');

        return is_string($disk) && $disk !== '' ? $disk : null;
    }

    /** Spatie Media Library's Media (duck-typed: no dependency on the package). */
    protected function isMedia(mixed $value): bool
    {
        return is_object($value) && method_exists($value, 'getUrl') && method_exists($value, 'getSrcset');
    }

    protected function fromMedia(object $media, ?string $conversion, ?string $alt, ?string $caption, ?string $href): Image
    {
        $conversion ??= '';
        /** @var string $src */
        $src = $media->getUrl($conversion);
        $srcset = $media->getSrcset($conversion);
        $customAlt = method_exists($media, 'getCustomProperty') ? $media->getCustomProperty('alt') : null;

        $image = new Image(
            src: $src,
            alt: $alt ?? (is_string($customAlt) ? $customAlt : (string) data_get($media, 'name', '')),
            srcset: is_string($srcset) && $srcset !== '' ? $srcset : null,
            caption: $caption,
            href: $href,
        );

        return $image->srcset === null ? $this->withSrcset($image) : $image;
    }

    protected function withSrcset(Image $image): Image
    {
        return $image->withSrcset($this->manager->srcsetFor($image));
    }

    /**
     * @param  list<string>  $fallbacks
     */
    protected function value(mixed $item, string|Closure|null $field, array $fallbacks): mixed
    {
        if ($field instanceof Closure) {
            return $field($item);
        }

        if ($field !== null) {
            return data_get($item, $field);
        }

        foreach ($fallbacks as $key) {
            $value = data_get($item, $key);

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $fallbacks
     */
    protected function text(mixed $item, string|Closure|null $field, array $fallbacks): ?string
    {
        $value = $this->value($item, $field, $fallbacks);

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
