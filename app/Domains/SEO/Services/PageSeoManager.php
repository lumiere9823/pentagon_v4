<?php

namespace App\Domains\SEO\Services;

use App\Domains\Pages\Contracts\PublicPageLocatorInterface;
use App\Models\Page;
use App\Models\Website;

class PageSeoManager
{
    public function __construct(
        protected ?PublicPageLocatorInterface $pageLocator = null
    ) {
        $this->pageLocator = $pageLocator ?? app(PublicPageLocatorInterface::class);
    }

    /**
     * Clean and normalize SEO metadata structure.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function normalizeSeoMetadata(array $input): array
    {
        $metaTitle = isset($input['meta_title']) && is_string($input['meta_title'])
            ? mb_substr(trim(strip_tags($input['meta_title'])), 0, 120)
            : null;

        $metaDescription = isset($input['meta_description']) && is_string($input['meta_description'])
            ? mb_substr(trim(strip_tags($input['meta_description'])), 0, 320)
            : null;

        $canonicalUrl = isset($input['canonical_url']) && is_string($input['canonical_url'])
            ? mb_substr(trim($input['canonical_url']), 0, 2048)
            : null;

        $ogTitle = isset($input['og_title']) && is_string($input['og_title'])
            ? mb_substr(trim(strip_tags($input['og_title'])), 0, 120)
            : null;

        $ogDescription = isset($input['og_description']) && is_string($input['og_description'])
            ? mb_substr(trim(strip_tags($input['og_description'])), 0, 320)
            : null;

        $ogImageUrl = isset($input['og_image_url']) && is_string($input['og_image_url'])
            ? mb_substr(trim($input['og_image_url']), 0, 2048)
            : null;

        $twitterCard = isset($input['twitter_card']) && in_array($input['twitter_card'], ['summary', 'summary_large_image'], true)
            ? $input['twitter_card']
            : 'summary_large_image';

        $noindex = ! empty($input['noindex']);
        $nofollow = ! empty($input['nofollow']);

        $schemaType = isset($input['schema_type']) && in_array($input['schema_type'], ['WebPage', 'Article', 'Organization'], true)
            ? $input['schema_type']
            : 'WebPage';

        $structuredDataJson = isset($input['structured_data_json']) && is_array($input['structured_data_json'])
            ? $input['structured_data_json']
            : null;

        return [
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'canonical_url' => $canonicalUrl,
            'og_title' => $ogTitle,
            'og_description' => $ogDescription,
            'og_image_url' => $ogImageUrl,
            'twitter_card' => $twitterCard,
            'noindex' => $noindex,
            'nofollow' => $nofollow,
            'schema_type' => $schemaType,
            'structured_data_json' => $structuredDataJson,
        ];
    }

    /**
     * Build SEO payload containing effective title, description, canonical, and HTML meta tags.
     *
     * @param  array<string, mixed>|null  $seoOverride
     * @return array{
     *     title: string,
     *     description: string,
     *     canonical_url: string,
     *     meta_tags: list<string>
     * }
     */
    public function buildSeoPayload(Page $page, Website $website, ?array $seoOverride = null): array
    {
        $seo = $this->normalizeSeoMetadata($seoOverride ?? $page->seo_metadata ?? []);

        // 1. Fallback cascade for Title & Description
        $effectiveTitle = ! empty($seo['meta_title']) ? $seo['meta_title'] : $page->title;
        $effectiveDescription = ! empty($seo['meta_description']) ? $seo['meta_description'] : '';

        // 2. Canonical URL fallback
        $effectiveCanonical = ! empty($seo['canonical_url'])
            ? $seo['canonical_url']
            : $this->pageLocator->getCanonicalUrl($page, $website);

        // 3. Robots directive
        if ($seo['noindex']) {
            $robotsDirective = 'noindex, nofollow';
        } elseif ($seo['nofollow']) {
            $robotsDirective = 'index, nofollow';
        } else {
            $robotsDirective = 'index, follow';
        }

        // 4. OpenGraph fallbacks
        $ogTitle = ! empty($seo['og_title']) ? $seo['og_title'] : $effectiveTitle;
        $ogDescription = ! empty($seo['og_description']) ? $seo['og_description'] : $effectiveDescription;
        $ogImage = ! empty($seo['og_image_url']) ? $this->qualifyUrl($seo['og_image_url']) : null;

        $tags = [
            '<meta name="robots" content="'.$this->escape($robotsDirective).'">',
            '<meta property="og:type" content="website">',
            '<meta property="og:title" content="'.$this->escape($ogTitle).'">',
            '<meta property="og:url" content="'.$this->escape($effectiveCanonical).'">',
        ];

        if ($ogDescription !== '') {
            $tags[] = '<meta property="og:description" content="'.$this->escape($ogDescription).'">';
        }

        if ($ogImage !== null) {
            $tags[] = '<meta property="og:image" content="'.$this->escape($ogImage).'">';
        }

        // Twitter Card
        $tags[] = '<meta name="twitter:card" content="'.$this->escape($seo['twitter_card']).'">';
        $tags[] = '<meta name="twitter:title" content="'.$this->escape($ogTitle).'">';
        if ($ogDescription !== '') {
            $tags[] = '<meta name="twitter:description" content="'.$this->escape($ogDescription).'">';
        }
        if ($ogImage !== null) {
            $tags[] = '<meta name="twitter:image" content="'.$this->escape($ogImage).'">';
        }

        // 5. JSON-LD Structured Data
        $structuredData = $seo['structured_data_json'] ?? [
            '@context' => 'https://schema.org',
            '@type' => $seo['schema_type'] ?? 'WebPage',
            'name' => $effectiveTitle,
            'description' => $effectiveDescription,
            'url' => $effectiveCanonical,
        ];

        $encodedJsonLd = json_encode(
            $structuredData,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );

        if ($encodedJsonLd !== false) {
            $tags[] = '<script type="application/ld+json">'.$encodedJsonLd.'</script>';
        }

        return [
            'title' => $effectiveTitle,
            'description' => $effectiveDescription,
            'canonical_url' => $effectiveCanonical,
            'meta_tags' => $tags,
        ];
    }

    private function qualifyUrl(string $url): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return url($url);
    }

    private function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
