<?php

namespace AmjadIqbal\LaravelSwiper\Support;

/**
 * One image slide, already resolved to URLs.
 */
final class Image
{
    public function __construct(
        public readonly string $src,
        public readonly string $alt = '',
        public readonly ?string $srcset = null,
        public readonly ?string $caption = null,
        public readonly ?string $href = null,
        public readonly ?int $width = null,
        public readonly ?int $height = null,
        public readonly ?string $path = null,
    ) {}

    public function withSrcset(?string $srcset): self
    {
        return new self($this->src, $this->alt, $srcset, $this->caption, $this->href, $this->width, $this->height, $this->path);
    }
}
