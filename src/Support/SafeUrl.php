<?php

namespace AmjadIqbal\LaravelSwiper\Support;

/**
 * Link targets that may come from user data: only http(s), mailto, tel and relative URLs pass.
 * Blade escaping stops markup injection but not a javascript: URL in href.
 */
final class SafeUrl
{
    public const SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public static function href(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $url = trim($url);

        if ($url === '') {
            return null;
        }

        // Browsers ignore control characters and whitespace inside a scheme ("java\tscript:").
        $probe = (string) preg_replace('/[\x00-\x20\x7F]+/', '', $url);

        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $probe, $match)) {
            return in_array(strtolower($match[1]), self::SCHEMES, true) ? $url : null;
        }

        // No scheme: relative path, query, fragment or protocol-relative URL.
        return $url;
    }
}
