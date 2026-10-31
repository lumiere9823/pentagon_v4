<?php

namespace App\Domains\Publishing\Services;

use App\Domains\DocumentEngine\DocumentLimits;
use App\Domains\DocumentEngine\Registry\ComponentRegistry;
use App\Domains\DocumentEngine\Sanitization\HtmlSanitizer;
use App\Domains\DocumentEngine\Validation\DocumentValidator;
use App\Domains\Publishing\Exceptions\PublishRenderingException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Server-side static HTML5 document renderer for Document Schema v1 AST.
 *
 * Maps all 12 core components safely into semantic HTML5 and Tailwind CSS.
 * Enforces strict contextual escaping, allowlisted RichText sanitization,
 * safe URL validation, responsive style cascading, and per-node error handling.
 */
class DocumentHtmlRenderer
{
    private DocumentValidator $validator;

    private HtmlSanitizer $sanitizer;

    private ComponentRegistry $registry;

    /**
     * Map of intrinsic display modes per component type.
     */
    private const INTRINSIC_DISPLAY_MODES = [
        'Section' => 'block',
        'Container' => 'block',
        'Heading' => 'block',
        'RichText' => 'block',
        'Image' => 'block',
        'Button' => 'inline-block',
        'Hero' => 'flex',
        'Columns' => 'grid',
        'CardGrid' => 'grid',
        'FAQ' => 'block',
        'Spacer' => 'flex',
        'Divider' => 'block',
    ];

    /**
     * Vite entry whose compiled CSS is inlined into rendered documents.
     */
    private const STYLESHEET_ENTRY = 'resources/css/app.css';

    private static ?string $inlineStylesheetCache = null;

    /** @var array{mobile: array<string, list<string>>, tablet: array<string, list<string>>, desktop: array<string, list<string>>} */
    private array $responsiveRules = [
        'mobile' => [],
        'tablet' => [],
        'desktop' => [],
    ];

    public function __construct(
        ?DocumentValidator $validator = null,
        ?HtmlSanitizer $sanitizer = null,
        ?ComponentRegistry $registry = null,
    ) {
        $this->validator = $validator ?? new DocumentValidator;
        $this->sanitizer = $sanitizer ?? new HtmlSanitizer;
        $this->registry = $registry ?? new ComponentRegistry;
    }

    /**
     * Render a validated Document Schema v1 document into its static HTML fragment.
     *
     * @param  array<string, mixed>|string  $document  Document array or JSON string.
     * @param  bool  $previewMode  When true, node render errors output placeholders instead of failing closed.
     *
     * @throws PublishRenderingException If validation fails or node cannot be rendered safely in production.
     */
    public function render(array|string $document, bool $previewMode = false): string
    {
        $this->resetResponsiveRules();
        $rendered = $this->renderFragment($document, $previewMode);

        return $this->responsiveStyleBlock().$rendered['html'];
    }

    /**
     * @param  array<string, mixed>|string  $document
     * @return array{document: array<string, mixed>, html: string}
     */
    private function renderFragment(array|string $document, bool $previewMode): array
    {
        // 1. Authoritative Document Schema v1 validation
        $validationResult = $previewMode
            ? $this->validator->validate($document)
            : $this->validator->validateForPublishing($document);
        if (! $validationResult->isValid()) {
            $formattedErrors = array_map(fn ($e) => $e->toArray(), $validationResult->getErrors());
            $errorSummary = implode('; ', array_map(fn ($e) => "[{$e->getPath()}] {$e->getMessage()}", $validationResult->getErrors()));

            throw new PublishRenderingException(
                message: "Document failed Schema v1 validation: {$errorSummary}",
                errors: $formattedErrors
            );
        }

        $canonicalDocument = $validationResult->getSanitizedDocument();
        if ($canonicalDocument === null || ! isset($canonicalDocument['root']) || ! is_array($canonicalDocument['root'])) {
            throw new PublishRenderingException('Document root node is missing or invalid.');
        }

        return [
            'document' => $canonicalDocument,
            'html' => $this->renderNode($canonicalDocument['root'], 1, $previewMode),
        ];
    }

    /**
     * Render a complete, standalone HTML5 document wrapper.
     *
     * @param  array<string, mixed>|string  $document
     * @param  array{
     *     title?: string,
     *     description?: string,
     *     canonical_url?: string,
     *     stylesheet_url?: string,
     *     meta_tags?: list<string>
     * }  $meta
     */
    public function renderDocument(
        array|string $document,
        array $meta = [],
        bool $previewMode = false,
        string $bodyPrefixHtml = '',
    ): string {
        $this->resetResponsiveRules();
        $rendered = $this->renderFragment($document, $previewMode);
        $renderedFragment = $rendered['html'];

        $pageSettings = isset($rendered['document']['pageSettings']) && is_array($rendered['document']['pageSettings'])
            ? $rendered['document']['pageSettings']
            : [];

        $title = $meta['title'] ?? ($pageSettings['title'] ?? 'Published Page');
        $description = $meta['description'] ?? ($pageSettings['description'] ?? '');
        $canonicalUrl = $meta['canonical_url'] ?? '';
        $stylesheetBlock = isset($meta['stylesheet_url'])
            ? '<link rel="stylesheet" href="'.$this->escape($meta['stylesheet_url']).'">'
            : $this->inlineCompiledStylesheet();

        $escapedTitle = $this->escape($title);
        $escapedDescription = $this->escape($description);

        $metaHeaders = [];
        if ($canonicalUrl !== '') {
            $metaHeaders[] = '<link rel="canonical" href="'.$this->escape($canonicalUrl).'">';
        }
        if (! empty($meta['meta_tags'])) {
            foreach ($meta['meta_tags'] as $tag) {
                $metaHeaders[] = $tag;
            }
        }
        $metaBlock = ! empty($metaHeaders) ? "\n    ".implode("\n    ", $metaHeaders) : '';
        $responsiveStyleBlock = $this->responsiveStyleBlock();
        $bodyPrefix = $bodyPrefixHtml !== '' ? "\n    {$bodyPrefixHtml}" : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$escapedTitle}</title>
    <meta name="description" content="{$escapedDescription}">{$metaBlock}
    {$stylesheetBlock}
    {$responsiveStyleBlock}
</head>
<body class="min-h-screen bg-white text-gray-900 antialiased">{$bodyPrefix}
    {$renderedFragment}
</body>
</html>
HTML;
    }

    /**
     * Inline the compiled Vite stylesheet so a sealed release stays self-contained.
     *
     * Vite emits content-hashed filenames that change on every build, and each deploy
     * replaces public/build. Linking to the hashed file would break published releases
     * after later deploys, so the CSS is embedded and covered by the manifest html_hash.
     */
    private function inlineCompiledStylesheet(): string
    {
        if (self::$inlineStylesheetCache !== null) {
            return self::$inlineStylesheetCache;
        }

        $manifestPath = public_path('build/manifest.json');
        $manifest = is_file($manifestPath) ? json_decode((string) file_get_contents($manifestPath), true) : null;
        $cssFile = is_array($manifest) ? ($manifest[self::STYLESHEET_ENTRY]['file'] ?? null) : null;
        $cssPath = is_string($cssFile) ? public_path('build/'.$cssFile) : null;

        if ($cssPath === null || ! is_file($cssPath)) {
            Log::warning('Compiled stylesheet not found; rendering page without styles. Run `npm run build`.', [
                'manifest' => $manifestPath,
            ]);

            return '';
        }

        // Prevent the CSS from closing the <style> element early.
        $css = str_ireplace('</style', '<\/style', (string) file_get_contents($cssPath));

        return self::$inlineStylesheetCache = "<style>{$css}</style>";
    }

    /**
     * Recursively render a single document node.
     *
     * @param  array<string, mixed>  $node
     */
    private function renderNode(array $node, int $depth, bool $previewMode): string
    {
        if (($node['hidden'] ?? false) === true) {
            return '';
        }

        if ($depth > DocumentLimits::MAX_TREE_DEPTH) {
            throw new PublishRenderingException('Maximum document tree depth limit of '.DocumentLimits::MAX_TREE_DEPTH." exceeded at node '{$node['id']}'.");
        }

        $type = (string) ($node['type'] ?? '');
        $id = (string) ($node['id'] ?? '');

        try {
            if (! $this->registry->has($type)) {
                throw new PublishRenderingException("Unsupported component type '{$type}' at node '{$id}'.");
            }

            $definition = $this->registry->get($type);
            if (! $definition) {
                throw new PublishRenderingException("Component definition for '{$type}' not found.");
            }

            $responsiveClasses = $this->resolveResponsiveClasses($node);

            // Render children if allowed
            $childrenHtml = '';
            if ($definition->allowChildren && isset($node['children']) && is_array($node['children'])) {
                foreach ($node['children'] as $child) {
                    if (is_array($child)) {
                        $childrenHtml .= $this->renderNode($child, $depth + 1, $previewMode);
                    }
                }
            }

            return match ($type) {
                'Section' => $this->renderSection($node, $childrenHtml, $responsiveClasses),
                'Container' => $this->renderContainer($node, $childrenHtml, $responsiveClasses),
                'Heading' => $this->renderHeading($node, $responsiveClasses),
                'RichText' => $this->renderRichText($node, $responsiveClasses),
                'Image' => $this->renderImage($node, $responsiveClasses, $previewMode),
                'Button' => $this->renderButton($node, $responsiveClasses),
                'Hero' => $this->renderHero($node, $childrenHtml, $responsiveClasses),
                'Columns' => $this->renderColumns($node, $childrenHtml, $responsiveClasses),
                'CardGrid' => $this->renderCardGrid($node, $childrenHtml, $responsiveClasses),
                'FAQ' => $this->renderFAQ($node, $responsiveClasses),
                'Spacer' => $this->renderSpacer($node, $responsiveClasses),
                'Divider' => $this->renderDivider($node, $responsiveClasses),
                default => throw new PublishRenderingException("No render implementation for registered component '{$type}'."),
            };
        } catch (Throwable $e) {
            if ($previewMode) {
                $safeId = $this->escape($id);
                $safeType = $this->escape($type);
                $safeMessage = $this->escape($e->getMessage());

                return "<div class=\"preview-node-error my-2 rounded border border-amber-400 bg-amber-50 p-3 font-mono text-xs text-amber-800\" data-node-id=\"{$safeId}\">[Preview Warning: Component '{$safeType}' ({$safeId}) could not be rendered: {$safeMessage}]</div>";
            }

            if ($e instanceof PublishRenderingException) {
                throw $e;
            }

            throw new PublishRenderingException("Error rendering node '{$id}' of type '{$type}': {$e->getMessage()}", [], 0, $e);
        }
    }

    /**
     * 1. Section Component
     *
     * @param  array<string, mixed>  $node
     */
    private function renderSection(array $node, string $childrenHtml, string $responsiveClasses): string
    {
        $id = $this->escape($node['id'] ?? '');
        $props = $node['props'] ?? [];

        $padding = $props['padding'] ?? 'medium';
        $paddingClass = match ($padding) {
            'none' => 'py-0',
            'small' => 'py-4 sm:py-6',
            'large' => 'py-16 sm:py-24',
            default => 'py-8 sm:py-12',
        };

        $background = $props['background'] ?? 'transparent';
        $backgroundClass = match ($background) {
            'muted' => 'bg-muted/50 text-foreground',
            'primary' => 'bg-primary/10 text-foreground',
            'dark' => 'bg-zinc-900 text-zinc-100',
            default => 'bg-transparent text-foreground',
        };

        $classes = trim("w-full transition-colors {$paddingClass} {$backgroundClass} {$responsiveClasses}");

        return "<section id=\"{$id}\" class=\"{$classes}\">{$childrenHtml}</section>";
    }

    /**
     * 2. Container Component
     *
     * @param  array<string, mixed>  $node
     */
    private function renderContainer(array $node, string $childrenHtml, string $responsiveClasses): string
    {
        $id = $this->escape($node['id'] ?? '');
        $props = $node['props'] ?? [];

        $maxWidth = $props['maxWidth'] ?? 'lg';
        $maxWidthClass = match ($maxWidth) {
            'sm' => 'max-w-screen-sm',
            'md' => 'max-w-screen-md',
            'xl' => 'max-w-screen-xl',
            'full' => 'max-w-full',
            default => 'max-w-screen-lg',
        };

        $centered = ! isset($props['centered']) || (bool) $props['centered'];
        $centeredClass = $centered ? 'mx-auto' : '';

        $classes = trim("w-full px-4 transition-all sm:px-6 {$maxWidthClass} {$centeredClass} {$responsiveClasses}");

        return "<div id=\"{$id}\" class=\"{$classes}\">{$childrenHtml}</div>";
    }

    /**
     * 3. Heading Component
     *
     * @param  array<string, mixed>  $node
     */
    private function renderHeading(array $node, string $responsiveClasses): string
    {
        $id = $this->escape($node['id'] ?? '');
        $props = $node['props'] ?? [];

        $rawLevel = (int) ($props['level'] ?? 2);
        $level = max(1, min(6, $rawLevel));
        $tag = "h{$level}";

        $fontSize = (string) ($props['fontSize'] ?? 'semantic');
        $sizeClass = $fontSize === 'semantic'
            ? match ($level) {
                1 => 'text-3xl sm:text-4xl font-extrabold tracking-tight',
                2 => 'text-2xl sm:text-3xl font-bold tracking-tight',
                3 => 'text-xl sm:text-2xl font-bold',
                4 => 'text-lg sm:text-xl font-semibold',
                5 => 'text-base sm:text-lg font-medium',
                default => 'text-sm sm:text-base font-medium',
            }
        : match ($level) {
            1 => 'font-extrabold tracking-tight',
            2, 3 => 'font-bold tracking-tight',
            4 => 'font-semibold',
            default => 'font-medium',
        };

        $align = $props['align'] ?? 'left';
        $alignClass = match ($align) {
            'center' => 'text-center',
            'right' => 'text-right',
            default => 'text-left',
        };

        $blockSpacingClass = $this->blockSpacingClass((string) ($props['blockSpacing'] ?? 'small'));
        $maxWidthClass = $this->maxWidthClass((string) ($props['maxWidth'] ?? 'full'));
        $fontSizeClass = $this->fontSizeClass($fontSize);
        $textColorClass = $this->textColorClass((string) ($props['textColor'] ?? 'foreground'), 'text-foreground');

        $text = $this->escape((string) ($props['text'] ?? 'Heading'));
        $classes = implode(' ', array_filter([
            $blockSpacingClass,
            'leading-tight',
            $textColorClass,
            $sizeClass,
            $fontSizeClass,
            $alignClass,
            $maxWidthClass,
            $responsiveClasses,
        ]));

        return "<{$tag} id=\"{$id}\" class=\"{$classes}\">{$text}</{$tag}>";
    }

    /**
     * 4. RichText Component
     *
     * @param  array<string, mixed>  $node
     */
    private function renderRichText(array $node, string $responsiveClasses): string
    {
        $id = $this->escape($node['id'] ?? '');
        $props = $node['props'] ?? [];

        $rawContent = (string) ($props['content'] ?? '');
        // Strict allowlisted HTML sanitization (strips all scripts, event handlers, javascript: URIs)
        $sanitizedContent = $this->sanitizer->sanitize($rawContent);

        $alignClass = match ($props['align'] ?? 'left') {
            'center' => 'text-center',
            'right' => 'text-right',
            default => '',
        };
        $blockSpacingClass = $this->blockSpacingClass((string) ($props['blockSpacing'] ?? 'small'));
        $maxWidthClass = $this->maxWidthClass((string) ($props['maxWidth'] ?? 'full'), 'max-w-none');
        $fontSizeClass = $this->fontSizeClass((string) ($props['fontSize'] ?? 'semantic'));
        $textColorClass = $this->textColorClass((string) ($props['textColor'] ?? 'soft'), 'text-foreground/90');

        $classes = implode(' ', array_filter([
            'prose',
            $maxWidthClass,
            $blockSpacingClass,
            'leading-relaxed',
            $textColorClass,
            $fontSizeClass,
            $alignClass,
            $responsiveClasses,
        ]));

        return "<div id=\"{$id}\" class=\"{$classes}\">{$sanitizedContent}</div>";
    }

    /**
     * 5. Image Component
     *
     * @param  array<string, mixed>  $node
     */
    private function renderImage(array $node, string $responsiveClasses, bool $previewMode): string
    {
        $id = $this->escape($node['id'] ?? '');
        $props = $node['props'] ?? [];

        $rawSrc = (string) ($props['src'] ?? '');
        if (! $this->sanitizer->isSafeUrl($rawSrc)) {
            throw new PublishRenderingException("Unsafe or invalid media URL detected at Image node '{$node['id']}': '{$rawSrc}'.");
        }

        if ($rawSrc === '') {
            if ($previewMode) {
                return "<figure id=\"{$id}\" class=\"my-4 w-full overflow-hidden rounded-lg {$responsiveClasses}\"><div class=\"flex aspect-16/9 w-full flex-col items-center justify-center rounded-lg border border-dashed border-border bg-muted/40 p-6 text-center text-muted-foreground\"><p class=\"text-xs font-medium\">No Image Source Configured</p></div></figure>";
            }

            throw new PublishRenderingException("Image node '{$node['id']}' has an empty src attribute.");
        }

        $src = $this->escape($rawSrc);
        $alt = $this->escape((string) ($props['alt'] ?? ''));
        $caption = (string) ($props['caption'] ?? '');

        $aspectRatio = $props['aspectRatio'] ?? 'auto';
        $aspectClass = match ($aspectRatio) {
            '16:9' => 'aspect-16/9 object-cover',
            '4:3' => 'aspect-4/3 object-cover',
            '1:1' => 'aspect-square object-cover',
            default => 'h-auto max-h-[500px] object-cover',
        };

        $captionHtml = '';
        if ($caption !== '') {
            $escapedCaption = $this->escape($caption);
            $captionHtml = "<figcaption class=\"mt-2 text-center text-xs text-muted-foreground\">{$escapedCaption}</figcaption>";
        }

        $classes = trim("my-4 w-full overflow-hidden rounded-lg {$responsiveClasses}");

        return "<figure id=\"{$id}\" class=\"{$classes}\"><img src=\"{$src}\" alt=\"{$alt}\" class=\"w-full rounded-lg shadow-xs transition-opacity {$aspectClass}\" loading=\"lazy\" decoding=\"async\"/>{$captionHtml}</figure>";
    }

    /**
     * 6. Button Component
     *
     * @param  array<string, mixed>  $node
     */
    private function renderButton(array $node, string $responsiveClasses): string
    {
        $id = $this->escape($node['id'] ?? '');
        $props = $node['props'] ?? [];

        $rawUrl = (string) ($props['url'] ?? '#');
        if (! $this->sanitizer->isSafeUrl($rawUrl)) {
            throw new PublishRenderingException("Unsafe link URL detected at Button node '{$node['id']}': '{$rawUrl}'.");
        }

        $url = $this->escape($rawUrl !== '' ? $rawUrl : '#');
        $label = $this->escape((string) ($props['label'] ?? 'Button'));
        $target = ($props['target'] ?? '_self') === '_blank' ? '_blank' : '_self';
        $relAttr = $target === '_blank' ? ' rel="noopener noreferrer"' : '';

        $variant = $props['variant'] ?? 'primary';
        $variantClass = match ($variant) {
            'secondary' => 'bg-secondary text-secondary-foreground hover:bg-secondary/80',
            'outline' => 'border border-input bg-background hover:bg-accent hover:text-accent-foreground',
            'ghost' => 'hover:bg-accent hover:text-accent-foreground',
            default => 'bg-primary text-primary-foreground hover:bg-primary/90',
        };

        $size = $props['size'] ?? 'md';
        $sizeClass = match ($size) {
            'sm' => 'h-8 px-3 text-xs',
            'lg' => 'h-11 px-6 text-base font-semibold',
            default => 'h-9 px-4 text-sm font-medium',
        };

        $classes = trim("my-2 inline-block {$responsiveClasses}");
        $btnClasses = trim("inline-flex cursor-pointer items-center justify-center rounded-md shadow-xs transition-colors select-none {$variantClass} {$sizeClass}");

        return "<div id=\"{$id}\" class=\"{$classes}\"><a href=\"{$url}\" target=\"{$target}\" role=\"button\" class=\"{$btnClasses}\"{$relAttr}>{$label}</a></div>";
    }

    /**
     * 7. Hero Component
     *
     * @param  array<string, mixed>  $node
     */
    private function renderHero(array $node, string $childrenHtml, string $responsiveClasses): string
    {
        $id = $this->escape($node['id'] ?? '');
        $props = $node['props'] ?? [];

        $align = $props['align'] ?? 'center';
        $alignClass = match ($align) {
            'left' => 'text-left items-start',
            'right' => 'text-right items-end',
            default => 'text-center items-center',
        };
        $backgroundClass = $this->backgroundClass((string) ($props['background'] ?? 'transparent'));
        [$titleForegroundClass, $subtitleForegroundClass] = $this->heroForegroundClasses((string) ($props['background'] ?? 'transparent'));

        $title = $this->escape((string) ($props['title'] ?? 'Hero Headline'));
        $subtitle = (string) ($props['subtitle'] ?? '');
        $subtitleHtml = '';
        if ($subtitle !== '') {
            $subtitleHtml = '<p class="mt-4 max-w-2xl text-base leading-relaxed sm:text-lg '.$subtitleForegroundClass.'">'.$this->escape($subtitle).'</p>';
        }

        $ctaText = (string) ($props['ctaText'] ?? '');
        $rawCtaUrl = (string) ($props['ctaUrl'] ?? '');
        $ctaHtml = '';
        if ($ctaText !== '') {
            if ($rawCtaUrl !== '' && ! $this->sanitizer->isSafeUrl($rawCtaUrl)) {
                throw new PublishRenderingException("Unsafe CTA URL in Hero node '{$node['id']}': '{$rawCtaUrl}'.");
            }
            $ctaUrl = $this->escape($rawCtaUrl !== '' ? $rawCtaUrl : '#');
            $ctaHtml = '<div class="mt-6"><a href="'.$ctaUrl.'" role="button" class="inline-flex h-10 cursor-pointer items-center justify-center rounded-md bg-primary px-6 text-sm font-semibold text-primary-foreground shadow-xs transition-colors hover:bg-primary/90">'.$this->escape($ctaText).'</a></div>';
        }

        $slotHtml = $childrenHtml !== '' ? "<div class=\"mt-6 w-full\">{$childrenHtml}</div>" : '';
        $classes = implode(' ', array_filter([
            'flex w-full flex-col justify-center px-4 py-12 sm:px-6 sm:py-20',
            $alignClass,
            $backgroundClass,
            $responsiveClasses,
        ]));

        return "<div id=\"{$id}\" class=\"{$classes}\"><h1 class=\"max-w-3xl text-3xl leading-tight font-extrabold tracking-tight sm:text-5xl {$titleForegroundClass}\">{$title}</h1>{$subtitleHtml}{$ctaHtml}{$slotHtml}</div>";
    }

    /**
     * 8. Columns Component
     *
     * @param  array<string, mixed>  $node
     */
    private function renderColumns(array $node, string $childrenHtml, string $responsiveClasses): string
    {
        $id = $this->escape($node['id'] ?? '');
        $props = $node['props'] ?? [];

        $count = (int) ($props['count'] ?? 2);
        $gridColsClass = match ($count) {
            3 => 'grid-cols-1 sm:grid-cols-2 md:grid-cols-3',
            4 => 'grid-cols-1 sm:grid-cols-2 md:grid-cols-4',
            default => 'grid-cols-1 md:grid-cols-2',
        };

        $gap = $props['gap'] ?? 'medium';
        $gapClass = match ($gap) {
            'small' => 'gap-3 sm:gap-4',
            'large' => 'gap-8 sm:gap-12',
            default => 'gap-6 sm:gap-8',
        };

        $classes = trim("my-4 grid w-full {$gridColsClass} {$gapClass} {$responsiveClasses}");

        return "<div id=\"{$id}\" class=\"{$classes}\">{$childrenHtml}</div>";
    }

    /**
     * 9. CardGrid Component
     *
     * @param  array<string, mixed>  $node
     */
    private function renderCardGrid(array $node, string $childrenHtml, string $responsiveClasses): string
    {
        $id = $this->escape($node['id'] ?? '');
        $props = $node['props'] ?? [];

        $columns = (int) ($props['columns'] ?? 3);
        $gridColsClass = match ($columns) {
            2 => 'grid-cols-1 md:grid-cols-2',
            4 => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
            default => 'grid-cols-1 sm:grid-cols-2 md:grid-cols-3',
        };

        $gap = $props['gap'] ?? 'medium';
        $gapClass = match ($gap) {
            'small' => 'gap-3 sm:gap-4',
            'large' => 'gap-8 sm:gap-10',
            default => 'gap-6',
        };

        $classes = trim("my-4 grid w-full {$gridColsClass} {$gapClass} {$responsiveClasses}");

        return "<div id=\"{$id}\" class=\"{$classes}\">{$childrenHtml}</div>";
    }

    /**
     * 10. FAQ Component (Semantic HTML5 <details>/<summary> with zero client JavaScript)
     *
     * @param  array<string, mixed>  $node
     */
    private function renderFAQ(array $node, string $responsiveClasses): string
    {
        $id = $this->escape($node['id'] ?? '');
        $props = $node['props'] ?? [];
        $items = isset($props['items']) && is_array($props['items']) ? $props['items'] : [];

        $itemsHtml = '';
        if (empty($items)) {
            $itemsHtml = '<div class="p-6 text-center text-xs text-muted-foreground">No FAQ items configured.</div>';
        } else {
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $question = $this->escape((string) ($item['question'] ?? ''));
                $rawAnswer = (string) ($item['answer'] ?? '');
                // Sanitize HTML in FAQ answer
                $answer = $this->sanitizer->sanitize($rawAnswer);

                $itemsHtml .= <<<HTML
<details class="group p-4 transition-colors">
    <summary class="flex w-full cursor-pointer list-none items-center justify-between text-left text-sm font-medium text-foreground hover:bg-muted/30 focus-visible:outline-none">
        <span>{$question}</span>
        <span class="ml-4 shrink-0 text-muted-foreground transition-transform duration-200 group-open:rotate-180">▾</span>
    </summary>
    <div class="prose max-w-none pt-2 pb-2 text-xs leading-relaxed text-muted-foreground">{$answer}</div>
</details>
HTML;
            }
        }

        $classes = trim("my-6 w-full divide-y divide-border rounded-lg border bg-card {$responsiveClasses}");

        return "<div id=\"{$id}\" class=\"{$classes}\">{$itemsHtml}</div>";
    }

    /**
     * 11. Spacer Component
     *
     * @param  array<string, mixed>  $node
     */
    private function renderSpacer(array $node, string $responsiveClasses): string
    {
        $id = $this->escape($node['id'] ?? '');
        $props = $node['props'] ?? [];

        $size = $props['size'] ?? 'medium';
        $heightClass = match ($size) {
            'small' => 'h-4 sm:h-6',
            'large' => 'h-16 sm:h-20',
            'xlarge' => 'h-24 sm:h-32',
            default => 'h-8 sm:h-12',
        };

        $classes = trim("flex w-full items-center justify-center border border-dashed border-transparent transition-all {$heightClass} {$responsiveClasses}");

        return "<div id=\"{$id}\" class=\"{$classes}\" aria-hidden=\"true\"></div>";
    }

    /**
     * 12. Divider Component
     *
     * @param  array<string, mixed>  $node
     */
    private function renderDivider(array $node, string $responsiveClasses): string
    {
        $id = $this->escape($node['id'] ?? '');
        $props = $node['props'] ?? [];

        $style = $props['style'] ?? 'solid';
        $borderStyleClass = match ($style) {
            'dashed' => 'border-dashed',
            'dotted' => 'border-dotted',
            default => 'border-solid',
        };

        $color = $props['color'] ?? 'muted';
        $colorClass = match ($color) {
            'primary' => 'border-primary/40',
            'subtle' => 'border-border/40',
            default => 'border-border',
        };

        $classes = trim("my-6 w-full py-2 {$responsiveClasses}");

        return "<div id=\"{$id}\" class=\"{$classes}\"><hr class=\"w-full border-t {$borderStyleClass} {$colorClass}\" /></div>";
    }

    /**
     * Register allowlisted responsive rules and leave existing desktop classes unchanged.
     *
     * @param  array<string, mixed>  $node
     */
    private function resolveResponsiveClasses(array $node): string
    {
        $styles = isset($node['styles']) && is_array($node['styles']) ? $node['styles'] : [];
        if ($styles === []) {
            return '';
        }

        $type = (string) ($node['type'] ?? '');
        $id = (string) ($node['id'] ?? '');
        $props = isset($node['props']) && is_array($node['props']) ? $node['props'] : [];
        $definition = $this->registry->get($type);
        if ($definition === null) {
            return '';
        }

        $keys = [];
        foreach (['tablet', 'mobile'] as $viewport) {
            foreach (array_keys(is_array($styles[$viewport] ?? null) ? $styles[$viewport] : []) as $key) {
                if (is_string($key) && $this->registry->isResponsiveOverrideAllowed($type, $key)) {
                    $keys[$key] = true;
                }
            }
        }

        foreach (array_keys($keys) as $prop) {
            $baseValue = $prop === 'display'
                ? 'visible'
                : ($props[$prop] ?? ($definition->propSchemas[$prop]['default'] ?? null));
            $tabletValue = $styles['tablet'][$prop] ?? $baseValue;
            $mobileValue = $styles['mobile'][$prop] ?? $tabletValue;

            $mobileUsesNaturalLayout = in_array($prop, ['count', 'columns'], true)
                && ! array_key_exists($prop, is_array($styles['mobile'] ?? null) ? $styles['mobile'] : [])
                && ! array_key_exists($prop, is_array($styles['tablet'] ?? null) ? $styles['tablet'] : []);
            $tabletUsesNaturalLayout = in_array($prop, ['count', 'columns'], true)
                && ! array_key_exists($prop, is_array($styles['tablet'] ?? null) ? $styles['tablet'] : []);

            $this->registerResponsiveDeclaration($id, $type, $prop, $mobileValue, 'mobile', $props, $mobileUsesNaturalLayout);
            $this->registerResponsiveDeclaration($id, $type, $prop, $tabletValue, 'tablet', $props, $tabletUsesNaturalLayout);
            $this->registerResponsiveDeclaration($id, $type, $prop, $baseValue, 'desktop', $props);
        }

        return '';
    }

    private function resetResponsiveRules(): void
    {
        $this->responsiveRules = [
            'mobile' => [],
            'tablet' => [],
            'desktop' => [],
        ];
    }

    private function responsiveStyleBlock(): string
    {
        $mobile = $this->formatResponsiveRules($this->responsiveRules['mobile']);
        $tablet = $this->formatResponsiveRules($this->responsiveRules['tablet']);
        $desktop = $this->formatResponsiveRules($this->responsiveRules['desktop']);

        if ($mobile === '' && $tablet === '' && $desktop === '') {
            return '';
        }

        $css = $mobile;
        if ($tablet !== '') {
            $css .= '@media (min-width: '.ComponentRegistry::RESPONSIVE_BREAKPOINTS['tablet'].'px){'.$tablet.'}';
        }
        if ($desktop !== '') {
            $css .= '@media (min-width: '.ComponentRegistry::RESPONSIVE_BREAKPOINTS['desktop'].'px){'.$desktop.'}';
        }

        return "<style data-pb-responsive>{$css}</style>\n";
    }

    /**
     * @param  array<string, list<string>>  $rules
     */
    private function formatResponsiveRules(array $rules): string
    {
        $css = '';
        foreach ($rules as $selector => $declarations) {
            $css .= $selector.'{'.implode('', array_values(array_unique($declarations))).'}';
        }

        return $css;
    }

    /**
     * @param  'mobile'|'tablet'|'desktop'  $viewport
     * @param  array<string, mixed>  $props
     */
    private function registerResponsiveDeclaration(
        string $id,
        string $type,
        string $prop,
        mixed $value,
        string $viewport,
        array $props,
        bool $useNaturalLayout = false,
    ): void {
        foreach ($this->responsiveDeclarations($type, $prop, $value, $viewport, $props, $useNaturalLayout) as $suffix => $declaration) {
            $selector = '[id="'.$id.'"]'.$suffix;
            $this->responsiveRules[$viewport][$selector][] = $declaration;
        }
    }

    /**
     * @param  'mobile'|'tablet'|'desktop'  $viewport
     * @param  array<string, mixed>  $props
     * @return array<string, string>
     */
    private function responsiveDeclarations(
        string $type,
        string $prop,
        mixed $value,
        string $viewport,
        array $props,
        bool $useNaturalLayout,
    ): array {
        if ($prop === 'display') {
            $display = $value === 'hidden' ? 'none' : (self::INTRINSIC_DISPLAY_MODES[$type] ?? 'block');

            return ['' => "display:{$display};"];
        }

        return match ("{$type}.{$prop}") {
            'Section.padding' => ['' => 'padding-block:'.$this->sectionPadding((string) $value, $viewport).';'],
            'Section.background' => ['' => $this->sectionBackground((string) $value)],
            'Container.maxWidth' => ['' => 'max-width:'.$this->containerMaxWidth((string) $value).';'],
            'Container.centered' => ['' => 'margin-inline:'.((bool) $value ? 'auto' : '0').';'],
            'Heading.align', 'RichText.align' => ['' => 'text-align:'.$this->textAlign((string) $value).';'],
            'Heading.blockSpacing', 'RichText.blockSpacing' => ['' => 'margin-block:'.$this->blockSpacing((string) $value).';'],
            'Heading.maxWidth', 'RichText.maxWidth' => ['' => 'max-width:'.$this->containerMaxWidth((string) $value).';'],
            'Heading.fontSize' => ['' => 'font-size:'.$this->fontSize((string) $value, $this->headingFontSize((int) ($props['level'] ?? 2), $viewport)).';'],
            'RichText.fontSize' => ['' => 'font-size:'.$this->fontSize((string) $value, '1rem').';'],
            'Heading.textColor', 'RichText.textColor' => ['' => 'color:'.$this->textColor((string) $value).';'],
            'Image.aspectRatio' => [' img' => $this->imageAspectRatio((string) $value)],
            'Button.size' => ['>a' => $this->buttonSize((string) $value)],
            'Button.variant' => $this->buttonVariant((string) $value),
            'Hero.align' => ['' => $this->heroAlign((string) $value)],
            'Hero.background' => $this->heroBackgroundDeclarations((string) $value),
            'Columns.count', 'CardGrid.columns' => ['' => 'grid-template-columns:repeat('.$this->gridColumnCount($type, (int) $value, $viewport, $useNaturalLayout).',minmax(0,1fr));'],
            'Columns.gap' => ['' => 'gap:'.$this->columnsGap((string) $value, $viewport).';'],
            'CardGrid.gap' => ['' => 'gap:'.$this->cardGridGap((string) $value, $viewport).';'],
            'Spacer.size' => ['' => 'height:'.$this->spacerHeight((string) $value, $viewport).';'],
            'Divider.style' => ['>hr' => 'border-style:'.$this->borderStyle((string) $value).';'],
            'Divider.color' => ['>hr' => 'border-color:'.$this->dividerColor((string) $value).';'],
            default => [],
        };
    }

    /** @param 'mobile'|'tablet'|'desktop' $viewport */
    private function sectionPadding(string $value, string $viewport): string
    {
        $compact = $viewport === 'mobile';

        return match ($value) {
            'none' => '0',
            'small' => $compact ? '1rem' : '1.5rem',
            'large' => $compact ? '4rem' : '6rem',
            default => $compact ? '2rem' : '3rem',
        };
    }

    private function sectionBackground(string $value): string
    {
        return match ($value) {
            'muted' => 'background-color:color-mix(in oklab,var(--muted) 50%,transparent);color:var(--foreground);',
            'primary' => 'background-color:color-mix(in oklab,var(--primary) 10%,transparent);color:var(--foreground);',
            'dark' => 'background-color:var(--color-zinc-900,#18181b);color:var(--color-zinc-100,#f4f4f5);',
            default => 'background-color:transparent;color:var(--foreground);',
        };
    }

    private function backgroundClass(string $value): string
    {
        return match ($value) {
            'muted' => 'bg-muted/50 text-foreground',
            'primary' => 'bg-primary/10 text-foreground',
            'dark' => 'bg-zinc-900 text-zinc-100',
            default => '',
        };
    }

    /** @return array{string, string} */
    private function heroForegroundClasses(string $background): array
    {
        return match ($background) {
            'dark' => ['text-zinc-100', 'text-zinc-200'],
            'muted', 'primary' => ['text-foreground', 'text-foreground'],
            default => ['text-foreground', 'text-muted-foreground'],
        };
    }

    /** @return array<string, string> */
    private function heroBackgroundDeclarations(string $background): array
    {
        [$titleColor, $subtitleColor] = match ($background) {
            'dark' => ['var(--color-zinc-100,#f4f4f5)', 'var(--color-zinc-200,#e4e4e7)'],
            'muted', 'primary' => ['var(--foreground)', 'var(--foreground)'],
            default => ['var(--foreground)', 'var(--muted-foreground)'],
        };

        return [
            '' => $this->sectionBackground($background),
            '>h1' => "color:{$titleColor};",
            '>p' => "color:{$subtitleColor};",
        ];
    }

    private function blockSpacing(string $value): string
    {
        return match ($value) {
            'none' => '0',
            'medium' => '1rem',
            'large' => '1.5rem',
            default => '0.5rem',
        };
    }

    private function blockSpacingClass(string $value): string
    {
        return match ($value) {
            'none' => 'my-0',
            'medium' => 'my-4',
            'large' => 'my-6',
            default => 'my-2',
        };
    }

    private function containerMaxWidth(string $value): string
    {
        return match ($value) {
            'sm' => '640px',
            'md' => '768px',
            'xl' => '1280px',
            'full' => '100%',
            default => '1024px',
        };
    }

    private function maxWidthClass(string $value, string $fullClass = ''): string
    {
        return match ($value) {
            'sm' => 'max-w-screen-sm',
            'md' => 'max-w-screen-md',
            'lg' => 'max-w-screen-lg',
            'xl' => 'max-w-screen-xl',
            default => $fullClass,
        };
    }

    private function fontSize(string $value, string $semanticValue): string
    {
        return match ($value) {
            'small' => '0.875rem',
            'medium' => '1rem',
            'large' => '1.25rem',
            'xlarge' => '1.875rem',
            default => $semanticValue,
        };
    }

    private function fontSizeClass(string $value): string
    {
        return match ($value) {
            'small' => 'text-sm',
            'medium' => 'text-base',
            'large' => 'text-xl',
            'xlarge' => 'text-3xl',
            default => '',
        };
    }

    private function textColor(string $value): string
    {
        return match ($value) {
            'soft' => 'color-mix(in oklab,var(--foreground) 90%,transparent)',
            'muted' => 'var(--muted-foreground)',
            'primary' => 'var(--primary)',
            default => 'var(--foreground)',
        };
    }

    private function textColorClass(string $value, string $defaultClass): string
    {
        return match ($value) {
            'foreground' => 'text-foreground',
            'soft' => 'text-foreground/90',
            'muted' => 'text-muted-foreground',
            'primary' => 'text-primary',
            default => $defaultClass,
        };
    }

    /** @param 'mobile'|'tablet'|'desktop' $viewport */
    private function headingFontSize(int $level, string $viewport): string
    {
        $sizes = [
            1 => ['1.875rem', '2.25rem'],
            2 => ['1.5rem', '1.875rem'],
            3 => ['1.25rem', '1.5rem'],
            4 => ['1.125rem', '1.25rem'],
            5 => ['1rem', '1.125rem'],
            6 => ['0.875rem', '1rem'],
        ];
        [$mobile, $wider] = $sizes[max(1, min(6, $level))];

        return $viewport === 'mobile' ? $mobile : $wider;
    }

    private function textAlign(string $value): string
    {
        return in_array($value, ['center', 'right'], true) ? $value : 'left';
    }

    private function imageAspectRatio(string $value): string
    {
        return match ($value) {
            '16:9' => 'aspect-ratio:16/9;height:auto;max-height:none;object-fit:cover;',
            '4:3' => 'aspect-ratio:4/3;height:auto;max-height:none;object-fit:cover;',
            '1:1' => 'aspect-ratio:1;height:auto;max-height:none;object-fit:cover;',
            default => 'aspect-ratio:auto;height:auto;max-height:500px;object-fit:cover;',
        };
    }

    private function buttonSize(string $value): string
    {
        return match ($value) {
            'sm' => 'height:2rem;padding-inline:0.75rem;font-size:0.75rem;font-weight:500;',
            'lg' => 'height:2.75rem;padding-inline:1.5rem;font-size:1rem;font-weight:600;',
            default => 'height:2.25rem;padding-inline:1rem;font-size:0.875rem;font-weight:500;',
        };
    }

    /** @return array<string, string> */
    private function buttonVariant(string $value): array
    {
        return match ($value) {
            'secondary' => [
                '>a' => 'border:0;background-color:var(--secondary);color:var(--secondary-foreground);',
                '>a:hover' => 'background-color:color-mix(in oklab,var(--secondary) 80%,transparent);',
            ],
            'outline' => [
                '>a' => 'border:1px solid var(--input);background-color:var(--background);color:inherit;',
                '>a:hover' => 'background-color:var(--accent);color:var(--accent-foreground);',
            ],
            'ghost' => [
                '>a' => 'border:0;background-color:transparent;color:inherit;',
                '>a:hover' => 'background-color:var(--accent);color:var(--accent-foreground);',
            ],
            default => [
                '>a' => 'border:0;background-color:var(--primary);color:var(--primary-foreground);',
                '>a:hover' => 'background-color:color-mix(in oklab,var(--primary) 90%,transparent);',
            ],
        };
    }

    private function heroAlign(string $value): string
    {
        return match ($value) {
            'left' => 'text-align:left;align-items:flex-start;',
            'right' => 'text-align:right;align-items:flex-end;',
            default => 'text-align:center;align-items:center;',
        };
    }

    /** @param 'mobile'|'tablet'|'desktop' $viewport */
    private function gridColumnCount(string $type, int $value, string $viewport, bool $useNaturalLayout): int
    {
        if (! $useNaturalLayout) {
            return $value;
        }
        if ($viewport === 'mobile') {
            return 1;
        }
        if ($type === 'CardGrid' && $value === 4 && $viewport === 'tablet') {
            return 2;
        }

        return $value;
    }

    /** @param 'mobile'|'tablet'|'desktop' $viewport */
    private function columnsGap(string $value, string $viewport): string
    {
        $compact = $viewport === 'mobile';

        return match ($value) {
            'small' => $compact ? '0.75rem' : '1rem',
            'large' => $compact ? '2rem' : '3rem',
            default => $compact ? '1.5rem' : '2rem',
        };
    }

    /** @param 'mobile'|'tablet'|'desktop' $viewport */
    private function cardGridGap(string $value, string $viewport): string
    {
        $compact = $viewport === 'mobile';

        return match ($value) {
            'small' => $compact ? '0.75rem' : '1rem',
            'large' => $compact ? '2rem' : '2.5rem',
            default => '1.5rem',
        };
    }

    /** @param 'mobile'|'tablet'|'desktop' $viewport */
    private function spacerHeight(string $value, string $viewport): string
    {
        $compact = $viewport === 'mobile';

        return match ($value) {
            'small' => $compact ? '1rem' : '1.5rem',
            'large' => $compact ? '4rem' : '5rem',
            'xlarge' => $compact ? '6rem' : '8rem',
            default => $compact ? '2rem' : '3rem',
        };
    }

    private function borderStyle(string $value): string
    {
        return in_array($value, ['dashed', 'dotted'], true) ? $value : 'solid';
    }

    private function dividerColor(string $value): string
    {
        return match ($value) {
            'primary' => 'color-mix(in oklab,var(--primary) 40%,transparent)',
            'subtle' => 'color-mix(in oklab,var(--border) 40%,transparent)',
            default => 'var(--border)',
        };
    }

    /**
     * Context-safe HTML and attribute escaping.
     */
    private function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
