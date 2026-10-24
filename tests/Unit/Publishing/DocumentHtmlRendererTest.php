<?php

namespace Tests\Unit\Publishing;

use App\Domains\Publishing\Exceptions\PublishRenderingException;
use App\Domains\Publishing\Services\DocumentHtmlRenderer;
use Tests\TestCase;

class DocumentHtmlRendererTest extends TestCase
{
    private DocumentHtmlRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new DocumentHtmlRenderer;
    }

    private function wrapInValidDocument(array $rootNode): array
    {
        return [
            'schemaVersion' => 1,
            'root' => $rootNode,
            'pageSettings' => [
                'title' => 'Test Page',
                'description' => 'Test Description',
            ],
        ];
    }

    /**
     * 1. Render document Schema v1 hợp lệ.
     */
    public function test_renders_valid_schema_v1_document_with_all_twelve_components(): void
    {
        $fixturePath = base_path('tests/Fixtures/Documents/all_twelve_components.json');
        $json = file_get_contents($fixturePath);

        $html = $this->renderer->render($json);

        $this->assertNotEmpty($html);
        $this->assertStringContainsString('<section id="root_section_01"', $html);
        $this->assertStringContainsString('<h1 class="max-w-3xl', $html);
        $this->assertStringContainsString('Welcome to Our Platform', $html);
        $this->assertStringContainsString('<div id="node_container_01"', $html);
        $this->assertStringContainsString('<h2 id="node_heading_01"', $html);
        $this->assertStringContainsString('<div id="node_richtext_01"', $html);
        $this->assertStringContainsString('<figure id="node_image_01"', $html);
        $this->assertStringContainsString('<a href="/docs"', $html);
        $this->assertStringContainsString('<div id="node_columns_01"', $html);
        $this->assertStringContainsString('<div id="node_cardgrid_01"', $html);
        $this->assertStringContainsString('<div id="node_faq_01"', $html);
        $this->assertStringContainsString('<details class="group', $html);
        $this->assertStringContainsString('<div id="node_spacer_01"', $html);
        $this->assertStringContainsString('<div id="node_divider_01"', $html);
    }

    /**
     * 2. Render từng component được registry hỗ trợ (Test cả 12 components).
     */
    public function test_renders_each_of_the_twelve_components_accurately(): void
    {
        // 1. Section
        $sectionDoc = $this->wrapInValidDocument([
            'id' => 'sec_1',
            'type' => 'Section',
            'props' => ['padding' => 'small', 'background' => 'dark'],
            'children' => [],
        ]);
        $html = $this->renderer->render($sectionDoc);
        $this->assertStringContainsString('<section id="sec_1" class="w-full transition-colors py-4 sm:py-6 bg-zinc-900 text-zinc-100"></section>', $html);

        // 2. Container
        $containerDoc = $this->wrapInValidDocument([
            'id' => 'con_1',
            'type' => 'Container',
            'props' => ['maxWidth' => 'sm', 'centered' => false],
            'children' => [],
        ]);
        $html = $this->renderer->render($containerDoc);
        $this->assertStringContainsString('<div id="con_1" class="w-full px-4 transition-all sm:px-6 max-w-screen-sm"></div>', $html);

        // 3. Heading
        $headingDoc = $this->wrapInValidDocument([
            'id' => 'head_1',
            'type' => 'Section',
            'children' => [
                [
                    'id' => 'h_1',
                    'type' => 'Heading',
                    'props' => ['text' => 'My Title', 'level' => 1, 'align' => 'right'],
                    'children' => [],
                ],
            ],
        ]);
        $html = $this->renderer->render($headingDoc);
        $this->assertStringContainsString('<h1 id="h_1" class="my-2 leading-tight text-foreground text-3xl sm:text-4xl font-extrabold tracking-tight text-right">My Title</h1>', $html);

        // 4. RichText
        $richDoc = $this->wrapInValidDocument([
            'id' => 'sec_rt',
            'type' => 'Section',
            'children' => [
                [
                    'id' => 'rt_1',
                    'type' => 'RichText',
                    'props' => ['content' => '<p>Hello <strong>World</strong></p>'],
                    'children' => [],
                ],
            ],
        ]);
        $html = $this->renderer->render($richDoc);
        $this->assertStringContainsString('<div id="rt_1" class="prose max-w-none my-2 leading-relaxed text-foreground/90"><p>Hello <strong>World</strong></p></div>', $html);

        // 5. Image
        $imgDoc = $this->wrapInValidDocument([
            'id' => 'sec_img',
            'type' => 'Section',
            'children' => [
                [
                    'id' => 'img_1',
                    'type' => 'Image',
                    'props' => ['src' => '/storage/media/1/photo.jpg', 'alt' => 'Photo', 'caption' => 'A photo', 'aspectRatio' => '16:9'],
                    'children' => [],
                ],
            ],
        ]);
        $html = $this->renderer->render($imgDoc);
        $this->assertStringContainsString('<img src="/storage/media/1/photo.jpg" alt="Photo" class="w-full rounded-lg shadow-xs transition-opacity aspect-16/9 object-cover" loading="lazy" decoding="async"/>', $html);
        $this->assertStringContainsString('<figcaption class="mt-2 text-center text-xs text-muted-foreground">A photo</figcaption>', $html);

        // 6. Button
        $btnDoc = $this->wrapInValidDocument([
            'id' => 'sec_btn',
            'type' => 'Section',
            'children' => [
                [
                    'id' => 'btn_1',
                    'type' => 'Button',
                    'props' => ['label' => 'Click Me', 'url' => 'https://example.com', 'variant' => 'secondary', 'size' => 'lg', 'target' => '_blank'],
                    'children' => [],
                ],
            ],
        ]);
        $html = $this->renderer->render($btnDoc);
        $this->assertStringContainsString('<a href="https://example.com" target="_blank" role="button" class="inline-flex cursor-pointer items-center justify-center rounded-md shadow-xs transition-colors select-none bg-secondary text-secondary-foreground hover:bg-secondary/80 h-11 px-6 text-base font-semibold" rel="noopener noreferrer">Click Me</a>', $html);

        // 7. Hero
        $heroDoc = $this->wrapInValidDocument([
            'id' => 'hero_1',
            'type' => 'Hero',
            'props' => [
                'title' => 'Hero Title',
                'subtitle' => 'Hero Subtitle',
                'align' => 'left',
                'ctaText' => 'Action',
                'ctaUrl' => '/action',
            ],
            'children' => [],
        ]);
        $html = $this->renderer->render($heroDoc);
        $this->assertStringContainsString('text-left items-start', $html);
        $this->assertStringContainsString('Hero Title', $html);
        $this->assertStringContainsString('Hero Subtitle', $html);
        $this->assertStringContainsString('Action', $html);

        // 8. Columns
        $colDoc = $this->wrapInValidDocument([
            'id' => 'sec_col',
            'type' => 'Section',
            'children' => [
                [
                    'id' => 'col_1',
                    'type' => 'Columns',
                    'props' => ['count' => 3, 'gap' => 'large'],
                    'children' => [],
                ],
            ],
        ]);
        $html = $this->renderer->render($colDoc);
        $this->assertStringContainsString('grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-8 sm:gap-12', $html);

        // 9. CardGrid
        $gridDoc = $this->wrapInValidDocument([
            'id' => 'sec_grid',
            'type' => 'Section',
            'children' => [
                [
                    'id' => 'grid_1',
                    'type' => 'CardGrid',
                    'props' => ['columns' => 4, 'gap' => 'small'],
                    'children' => [],
                ],
            ],
        ]);
        $html = $this->renderer->render($gridDoc);
        $this->assertStringContainsString('grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4', $html);

        // 10. FAQ
        $faqDoc = $this->wrapInValidDocument([
            'id' => 'sec_faq',
            'type' => 'Section',
            'children' => [
                [
                    'id' => 'faq_1',
                    'type' => 'FAQ',
                    'props' => [
                        'items' => [
                            ['question' => 'Q1', 'answer' => '<p>A1</p>'],
                        ],
                    ],
                    'children' => [],
                ],
            ],
        ]);
        $html = $this->renderer->render($faqDoc);
        $this->assertStringContainsString('<details class="group p-4 transition-colors">', $html);
        $this->assertStringContainsString('<span>Q1</span>', $html);
        $this->assertStringContainsString('<p>A1</p>', $html);

        // 11. Spacer
        $spacerDoc = $this->wrapInValidDocument([
            'id' => 'sec_sp',
            'type' => 'Section',
            'children' => [
                [
                    'id' => 'sp_1',
                    'type' => 'Spacer',
                    'props' => ['size' => 'large'],
                    'children' => [],
                ],
            ],
        ]);
        $html = $this->renderer->render($spacerDoc);
        $this->assertStringContainsString('h-16 sm:h-20', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);

        // 12. Divider
        $divDoc = $this->wrapInValidDocument([
            'id' => 'sec_div',
            'type' => 'Section',
            'children' => [
                [
                    'id' => 'div_1',
                    'type' => 'Divider',
                    'props' => ['style' => 'dashed', 'color' => 'primary'],
                    'children' => [],
                ],
            ],
        ]);
        $html = $this->renderer->render($divDoc);
        $this->assertStringContainsString('<hr class="w-full border-t border-dashed border-primary/40" />', $html);
    }

    /**
     * 3. Render đúng thứ tự children và cấu trúc container.
     */
    public function test_renders_children_in_exact_sequential_order_and_nested_structure(): void
    {
        $doc = $this->wrapInValidDocument([
            'id' => 'sec_parent',
            'type' => 'Section',
            'children' => [
                [
                    'id' => 'con_child',
                    'type' => 'Container',
                    'children' => [
                        [
                            'id' => 'heading_first',
                            'type' => 'Heading',
                            'props' => ['text' => 'First Child', 'level' => 2],
                            'children' => [],
                        ],
                        [
                            'id' => 'heading_second',
                            'type' => 'Heading',
                            'props' => ['text' => 'Second Child', 'level' => 3],
                            'children' => [],
                        ],
                        [
                            'id' => 'heading_third',
                            'type' => 'Heading',
                            'props' => ['text' => 'Third Child', 'level' => 4],
                            'children' => [],
                        ],
                    ],
                ],
            ],
        ]);

        $html = $this->renderer->render($doc);

        $posFirst = strpos($html, 'heading_first');
        $posSecond = strpos($html, 'heading_second');
        $posThird = strpos($html, 'heading_third');

        $this->assertNotFalse($posFirst);
        $this->assertNotFalse($posSecond);
        $this->assertNotFalse($posThird);
        $this->assertTrue($posFirst < $posSecond && $posSecond < $posThird, 'Children must be rendered in exact sequential order.');
    }

    /**
     * 4. Escape HTML text và attributes.
     */
    public function test_escapes_html_text_and_attributes_to_prevent_xss(): void
    {
        $doc = $this->wrapInValidDocument([
            'id' => 'sec_xss',
            'type' => 'Section',
            'children' => [
                [
                    'id' => 'head_xss',
                    'type' => 'Heading',
                    'props' => [
                        'text' => '<script>alert("XSS")</script> & "quotes"',
                        'level' => 2,
                    ],
                    'children' => [],
                ],
                [
                    'id' => 'btn_xss',
                    'type' => 'Button',
                    'props' => [
                        'label' => '"><img src=x onerror=alert(1)>',
                        'url' => '/page?name=<test>',
                    ],
                    'children' => [],
                ],
            ],
        ]);

        $html = $this->renderer->render($doc);

        $this->assertStringNotContainsString('<script>alert("XSS")</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt; &amp; &quot;quotes&quot;', $html);
        $this->assertStringNotContainsString('"><img src=x onerror=alert(1)>', $html);
        $this->assertStringContainsString('&quot;&gt;&lt;img src=x onerror=alert(1)&gt;', $html);
    }

    /**
     * 5. Sanitize RichText với các payload XSS đại diện.
     */
    public function test_sanitizes_richtext_with_representative_xss_payloads(): void
    {
        $maliciousPayload = '<p>Normal text</p><script>alert("hacked")</script><img src="x" onerror="alert(1)"><a href="javascript:alert(1)">Click</a>';

        $doc = $this->wrapInValidDocument([
            'id' => 'sec_rt',
            'type' => 'Section',
            'children' => [
                [
                    'id' => 'rt_xss',
                    'type' => 'RichText',
                    'props' => ['content' => $maliciousPayload],
                    'children' => [],
                ],
            ],
        ]);

        $html = $this->renderer->render($doc);

        $this->assertStringContainsString('<p>Normal text</p>', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('alert("hacked")', $html);
        $this->assertStringNotContainsString('onerror=', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    /**
     * 6. Từ chối component type không được phép.
     */
    public function test_rejects_unsupported_or_disallowed_component_type(): void
    {
        $doc = $this->wrapInValidDocument([
            'id' => 'sec_invalid',
            'type' => 'CustomArbitraryComponent',
            'children' => [],
        ]);

        $this->expectException(PublishRenderingException::class);
        $this->expectExceptionMessageMatches('/is not in the allowlist|disallowed_component_type/i');

        $this->renderer->render($doc);
    }

    /**
     * 7. Từ chối document JSON hoặc node không hợp lệ.
     */
    public function test_rejects_invalid_json_or_missing_root_node(): void
    {
        $this->expectException(PublishRenderingException::class);
        $this->expectExceptionMessageMatches('/not valid JSON|invalid_json/i');

        $this->renderer->render('{"malformed: JSON');
    }

    /**
     * 8. Từ chối cấu trúc vượt giới hạn validation (max depth, node count, payload).
     */
    public function test_rejects_document_exceeding_depth_limit(): void
    {
        // Build depth > 10
        $current = [
            'id' => 'leaf_heading',
            'type' => 'Heading',
            'props' => ['text' => 'Deep', 'level' => 2],
            'children' => [],
        ];

        for ($i = 10; $i >= 1; $i--) {
            $current = [
                'id' => "level_{$i}",
                'type' => 'Section',
                'children' => [$current],
            ];
        }

        $deepDoc = $this->wrapInValidDocument($current);

        $this->expectException(PublishRenderingException::class);
        $this->expectExceptionMessageMatches('/Tree depth exceeds maximum allowed depth|max_depth_exceeded/i');

        $this->renderer->render($deepDoc);
    }

    /**
     * 9. Xử lý URL nguy hiểm trong link/image props.
     */
    public function test_rejects_dangerous_url_schemes_in_image_and_button(): void
    {
        $dangerousDoc = $this->wrapInValidDocument([
            'id' => 'sec_danger',
            'type' => 'Section',
            'children' => [
                [
                    'id' => 'btn_danger',
                    'type' => 'Button',
                    'props' => [
                        'label' => 'Evil',
                        'url' => 'javascript:alert(document.cookie)',
                    ],
                    'children' => [],
                ],
            ],
        ]);

        $this->expectException(PublishRenderingException::class);
        $this->expectExceptionMessageMatches('/unsafe or forbidden URL scheme|invalid_url_scheme/i');

        $this->renderer->render($dangerousDoc);
    }

    /**
     * 10. Không xuất script hoặc event-handler attributes do người dùng cung cấp.
     */
    public function test_never_outputs_user_provided_scripts_or_event_handlers(): void
    {
        $doc = $this->wrapInValidDocument([
            'id' => 'sec_clean',
            'type' => 'Section',
            'children' => [
                [
                    'id' => 'faq_clean',
                    'type' => 'FAQ',
                    'props' => [
                        'items' => [
                            [
                                'question' => 'How to hack?',
                                'answer' => '<div onmouseover="alert(1)">Text <svg onload=alert(2)></svg></div>',
                            ],
                        ],
                    ],
                    'children' => [],
                ],
            ],
        ]);

        $html = $this->renderer->render($doc);

        $this->assertStringNotContainsString('onmouseover', $html);
        $this->assertStringNotContainsString('onload', $html);
        $this->assertStringNotContainsString('<svg', $html);
        $this->assertStringContainsString('Text', $html);
    }

    /**
     * 11. Kiểm tra deterministic output cho cùng document.
     */
    public function test_produces_byte_for_byte_deterministic_html_for_identical_document(): void
    {
        $fixturePath = base_path('tests/Fixtures/Documents/all_twelve_components.json');
        $json = file_get_contents($fixturePath);

        $htmlRun1 = $this->renderer->render($json);
        $htmlRun2 = $this->renderer->render($json);
        $htmlRun3 = $this->renderer->render($json);

        $this->assertSame($htmlRun1, $htmlRun2);
        $this->assertSame($htmlRun2, $htmlRun3);
        $this->assertSame(hash('sha256', $htmlRun1), hash('sha256', $htmlRun2));
    }

    /**
     * 12. Kiểm tra lỗi render không tạo ra HTML đầu ra được coi là publishable (Production vs Preview).
     */
    public function test_render_errors_fail_closed_in_production_and_render_placeholder_in_preview(): void
    {
        // Document with empty image src
        $docWithInvalidImage = $this->wrapInValidDocument([
            'id' => 'sec_root',
            'type' => 'Section',
            'children' => [
                [
                    'id' => 'img_missing_src',
                    'type' => 'Image',
                    'props' => [
                        'src' => '',
                        'alt' => 'Empty',
                    ],
                    'children' => [],
                ],
            ],
        ]);

        // In Production Publish mode (previewMode = false): MUST throw exception, zero HTML output
        $this->expectException(PublishRenderingException::class);
        $this->renderer->render($docWithInvalidImage, previewMode: false);
    }

    public function test_preview_mode_renders_node_error_placeholder_without_crashing_entire_page(): void
    {
        $docWithInvalidImage = $this->wrapInValidDocument([
            'id' => 'sec_root',
            'type' => 'Section',
            'children' => [
                [
                    'id' => 'head_valid',
                    'type' => 'Heading',
                    'props' => ['text' => 'Valid Heading', 'level' => 2],
                    'children' => [],
                ],
                [
                    'id' => 'img_missing_src',
                    'type' => 'Image',
                    'props' => [
                        'src' => '',
                        'alt' => 'Empty',
                    ],
                    'children' => [],
                ],
            ],
        ]);

        // In Preview mode (previewMode = true): Isolates defective node with warning placeholder
        $html = $this->renderer->render($docWithInvalidImage, previewMode: true);

        $this->assertNotEmpty($html);
        $this->assertStringContainsString('Valid Heading', $html);
        $this->assertStringContainsString('No Image Source Configured', $html);
    }

    public function test_render_document_wraps_in_valid_html5_with_meta_tags(): void
    {
        $fixturePath = base_path('tests/Fixtures/Documents/minimal_valid.json');
        $json = file_get_contents($fixturePath);

        $fullDoc = $this->renderer->renderDocument($json, [
            'title' => 'Custom Meta Title',
            'description' => 'Custom Meta Description',
            'canonical_url' => 'https://example.com/canonical',
            'stylesheet_url' => '/assets/theme.css',
            'meta_tags' => ['<meta name="robots" content="noindex">'],
        ]);

        $this->assertStringStartsWith('<!DOCTYPE html>', $fullDoc);
        $this->assertStringContainsString('<title>Custom Meta Title</title>', $fullDoc);
        $this->assertStringContainsString('<meta name="description" content="Custom Meta Description">', $fullDoc);
        $this->assertStringContainsString('<link rel="canonical" href="https://example.com/canonical">', $fullDoc);
        $this->assertStringContainsString('<link rel="stylesheet" href="/assets/theme.css">', $fullDoc);
        $this->assertStringContainsString('<meta name="robots" content="noindex">', $fullDoc);
        $this->assertStringContainsString('<body class="min-h-screen bg-white text-gray-900 antialiased">', $fullDoc);
        $this->assertStringContainsString('</html>', $fullDoc);
    }

    public function test_render_document_inlines_compiled_stylesheet_instead_of_unhashed_link(): void
    {
        $json = file_get_contents(base_path('tests/Fixtures/Documents/minimal_valid.json'));

        $fullDoc = $this->renderer->renderDocument($json);

        $this->assertStringNotContainsString('/build/assets/app.css', $fullDoc);
        $this->assertStringNotContainsString('<link rel="stylesheet"', $fullDoc);

        if (! is_file(public_path('build/manifest.json'))) {
            $this->markTestSkipped('Vite build output not present; inline CSS path not exercised.');
        }

        $this->assertMatchesRegularExpression('/<style>.+<\/style>\s*<\/head>/s', $fullDoc);
    }

    public function test_responsive_display_classes_are_rendered_correctly(): void
    {
        $doc = $this->wrapInValidDocument([
            'id' => 'sec_resp',
            'type' => 'Section',
            'styles' => [
                'mobile' => ['display' => 'hidden'],
                'tablet' => ['display' => 'hidden'],
            ],
            'children' => [
                [
                    'id' => 'btn_resp',
                    'type' => 'Button',
                    'props' => ['label' => 'Mobile Only', 'url' => '#'],
                    'styles' => [
                        'tablet' => ['display' => 'hidden'],
                    ],
                    'children' => [],
                ],
            ],
        ]);

        $html = $this->renderer->render($doc);

        // Section has mobile and tablet hidden -> hidden lg:block
        $this->assertStringContainsString('hidden lg:block', $html);
        // Button has tablet hidden -> inline-block md:hidden lg:inline-block
        $this->assertStringContainsString('inline-block md:hidden lg:inline-block', $html);
    }
}
