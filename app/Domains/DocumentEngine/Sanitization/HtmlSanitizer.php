<?php

namespace App\Domains\DocumentEngine\Sanitization;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer as SymfonySanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class HtmlSanitizer
{
    private SymfonySanitizer $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig)
            ->allowSafeElements()
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            ->allowRelativeLinks(true);

        $this->sanitizer = new SymfonySanitizer($config);
    }

    /**
     * Sanitize HTML content, stripping dangerous tags, scripts, and attributes.
     */
    public function sanitize(string $html): string
    {
        return $this->sanitizer->sanitize($html);
    }

    /**
     * Determine if a given URL is safe for navigation or media references.
     * Rejects javascript:, data:, vbscript:, and control characters.
     */
    public function isSafeUrl(?string $url): bool
    {
        if ($url === null || trim($url) === '') {
            return true;
        }

        $trimmed = trim($url);

        // Check for invisible/control characters
        if (preg_match('/[\x00-\x1F\x7F]/', $trimmed)) {
            return false;
        }

        // Allow internal anchors: #section
        if (str_starts_with($trimmed, '#')) {
            return true;
        }

        // Allow site-relative paths: /about, /storage/images/hero.jpg
        if (str_starts_with($trimmed, '/') && ! str_starts_with($trimmed, '//')) {
            return true;
        }

        // Parse scheme
        $scheme = parse_url($trimmed, PHP_URL_SCHEME);
        if ($scheme === false) {
            return false;
        }

        if ($scheme === null) {
            // Relative URL without leading slash
            return ! preg_match('/^[a-zA-Z0-9+.-]+:/i', $trimmed);
        }

        $lowerScheme = strtolower($scheme);

        return in_array($lowerScheme, ['http', 'https', 'mailto', 'tel'], true);
    }
}
