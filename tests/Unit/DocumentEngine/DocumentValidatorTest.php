<?php

namespace Tests\Unit\DocumentEngine;

use App\Domains\DocumentEngine\DocumentLimits;
use App\Domains\DocumentEngine\Schema\StylePrimitiveCatalog;
use App\Domains\DocumentEngine\Validation\DocumentValidator;
use Tests\TestCase;

class DocumentValidatorTest extends TestCase
{
    private DocumentValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new DocumentValidator;
    }

    public function test_nonresponsive_primitive_remains_valid_at_base_but_is_rejected_in_overrides(): void
    {
        $reflection = new \ReflectionClass(StylePrimitiveCatalog::class);
        $definitions = $reflection->getProperty('definitions');
        $original = StylePrimitiveCatalog::all();
        $modified = $original;
        $modified['dimensions.controlSize']['responsive'] = false;
        $definitions->setValue(null, $modified);

        try {
            $validator = new DocumentValidator;
            $document = [
                'schemaVersion' => 1,
                'root' => [
                    'id' => 'root_section',
                    'type' => 'Section',
                    'props' => [],
                    'children' => [[
                        'id' => 'button_node',
                        'type' => 'Button',
                        'props' => [
                            'label' => 'Button',
                            'url' => '/',
                            'size' => 'lg',
                        ],
                    ]],
                ],
            ];

            $baseResult = $validator->validate($document);
            $this->assertTrue($baseResult->isValid());
            $this->assertSame(
                'lg',
                $baseResult->getSanitizedDocument()['root']['children'][0]['props']['size'],
            );

            $document['root']['children'][0]['styles'] = [
                'tablet' => ['size' => 'sm'],
            ];
            $result = $validator->validate($document);

            $this->assertFalse($result->isValid());
            $this->assertSame(
                'root.children[0].styles.tablet.size',
                $result->getErrors()[0]->getPath(),
            );
            $this->assertSame(
                'disallowed_style_override',
                $result->getErrors()[0]->getCode(),
            );
        } finally {
            $definitions->setValue(null, $original);
        }
    }

    public function test_validates_minimal_document_successfully(): void
    {
        $fixturePath = base_path('tests/Fixtures/Documents/minimal_valid.json');
        $json = file_get_contents($fixturePath);

        $result = $this->validator->validate($json);

        $this->assertTrue($result->isValid(), 'Minimal valid document should pass validation.');
        $this->assertEmpty($result->getErrors());
        $this->assertNotNull($result->getSanitizedDocument());
        $this->assertEquals(1, $result->getSanitizedDocument()['schemaVersion']);
        $this->assertEquals('root_sec_01', $result->getSanitizedDocument()['root']['id']);
    }

    public function test_validates_all_twelve_components_fixture_successfully(): void
    {
        $fixturePath = base_path('tests/Fixtures/Documents/all_twelve_components.json');
        $json = file_get_contents($fixturePath);

        $result = $this->validator->validate($json);

        $this->assertTrue($result->isValid(), 'Document with all 12 core components should pass validation.');
        $this->assertEmpty($result->getErrors());
        $this->assertNotNull($result->getSanitizedDocument());
    }

    public function test_canonicalizes_all_registered_aliases_deterministically_and_idempotently(): void
    {
        $document = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'canonical_root',
                'type' => 'Section',
                'props' => [],
                'children' => [
                    [
                        'id' => 'canonical_rich_text',
                        'type' => 'Rich Text',
                        'props' => [
                            'content' => '<p onclick="alert(1)">Keep <strong>this</strong></p><script>alert(2)</script>',
                        ],
                        'children' => [],
                    ],
                    [
                        'id' => 'canonical_card_grid',
                        'type' => 'Card Grid',
                        'props' => [],
                        'children' => [[
                            'id' => 'canonical_faq',
                            'type' => 'FAQ',
                            'props' => [
                                'items' => [[
                                    'question' => 'Does content survive?',
                                    'answer' => '<p>Yes <em>it does</em></p><img src=x onerror=alert(3)>',
                                ]],
                            ],
                            'children' => [],
                        ]],
                    ],
                ],
            ],
            'pageSettings' => [
                'title' => 'Canonical Page',
                'description' => 'Preserve this description',
            ],
        ];

        $firstResult = $this->validator->validate($document);

        $this->assertTrue($firstResult->isValid());
        $canonical = $firstResult->getSanitizedDocument();
        $this->assertNotNull($canonical);
        $this->assertSame('RichText', $canonical['root']['children'][0]['type']);
        $this->assertSame('CardGrid', $canonical['root']['children'][1]['type']);
        $this->assertSame(['padding' => 'medium', 'background' => 'transparent'], $canonical['root']['props']);
        $this->assertSame(['columns' => 3, 'gap' => 'medium'], $canonical['root']['children'][1]['props']);
        $this->assertSame('Does content survive?', $canonical['root']['children'][1]['children'][0]['props']['items'][0]['question']);
        $this->assertSame('Canonical Page', $canonical['pageSettings']['title']);
        $this->assertStringContainsString('<strong>this</strong>', $canonical['root']['children'][0]['props']['content']);
        $this->assertStringNotContainsString('onclick', $canonical['root']['children'][0]['props']['content']);
        $this->assertStringNotContainsString('<script', $canonical['root']['children'][0]['props']['content']);
        $this->assertStringContainsString('<em>it does</em>', $canonical['root']['children'][1]['children'][0]['props']['items'][0]['answer']);
        $this->assertStringNotContainsString('onerror', $canonical['root']['children'][1]['children'][0]['props']['items'][0]['answer']);

        $secondResult = $this->validator->validate($canonical);

        $this->assertTrue($secondResult->isValid());
        $this->assertSame($canonical, $secondResult->getSanitizedDocument());
    }

    public function test_rejects_malformed_json_syntax(): void
    {
        $result = $this->validator->validate('{ "schemaVersion": 1, invalid_json ');

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasError('document'));
        $this->assertEquals('invalid_json', $result->firstError()?->getCode());
    }

    public function test_rejects_payload_exceeding_byte_limit(): void
    {
        $hugeString = str_repeat('A', DocumentLimits::MAX_PAYLOAD_BYTES + 100);
        $result = $this->validator->validate($hugeString);

        $this->assertFalse($result->isValid());
        $this->assertEquals('max_payload_size_exceeded', $result->firstError()?->getCode());
    }

    public function test_rejects_missing_or_unsupported_schema_version(): void
    {
        // Missing
        $docNoVersion = [
            'root' => [
                'id' => 'sec_01',
                'type' => 'Section',
                'props' => [],
                'children' => [],
            ],
        ];
        $res1 = $this->validator->validate($docNoVersion);
        $this->assertFalse($res1->isValid());
        $this->assertTrue($res1->hasError('schemaVersion'));
        $this->assertEquals('missing_schema_version', $res1->firstError()?->getCode());

        // Unsupported version
        $docBadVersion = $docNoVersion;
        $docBadVersion['schemaVersion'] = 999;
        $res2 = $this->validator->validate($docBadVersion);
        $this->assertFalse($res2->isValid());
        $this->assertEquals('unsupported_schema_version', $res2->firstError()?->getCode());
    }

    public function test_rejects_invalid_root_component_type(): void
    {
        // Heading is a leaf, cannot be root
        $doc = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'head_root_01',
                'type' => 'Heading',
                'props' => ['text' => 'Bad Root', 'level' => 1],
                'children' => [],
            ],
        ];

        $result = $this->validator->validate($doc);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasError('root.type'));
        $this->assertEquals('invalid_root_component', $result->firstError()?->getCode());
    }

    public function test_rejects_missing_or_invalid_node_id_format(): void
    {
        $doc = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'no', // too short (< 3 chars)
                'type' => 'Section',
                'props' => [],
                'children' => [],
            ],
        ];

        $result = $this->validator->validate($doc);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasError('root.id'));
        $this->assertEquals('invalid_node_id_format', $result->firstError()?->getCode());
    }

    public function test_rejects_duplicate_node_ids(): void
    {
        $doc = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'duplicate_id_01',
                'type' => 'Section',
                'props' => [],
                'children' => [
                    [
                        'id' => 'duplicate_id_01', // duplicate ID
                        'type' => 'Container',
                        'props' => [],
                        'children' => [],
                    ],
                ],
            ],
        ];

        $result = $this->validator->validate($doc);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasError('root.children[0].id'));
        $this->assertEquals('duplicate_node_id', $result->firstError()?->getCode());
    }

    public function test_rejects_unknown_component_type(): void
    {
        $doc = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_sec_01',
                'type' => 'Section',
                'props' => [],
                'children' => [
                    [
                        'id' => 'malicious_node_01',
                        'type' => 'SystemCommandRunner',
                        'props' => ['cmd' => 'ls'],
                        'children' => [],
                    ],
                ],
            ],
        ];

        $result = $this->validator->validate($doc);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasError('root.children[0].type'));
        $this->assertEquals('unknown_component_type', $result->firstError()?->getCode());
    }

    public function test_rejects_leaf_component_with_children(): void
    {
        $doc = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_sec_01',
                'type' => 'Section',
                'props' => [],
                'children' => [
                    [
                        'id' => 'heading_01',
                        'type' => 'Heading',
                        'props' => ['text' => 'Hello', 'level' => 2],
                        'children' => [
                            [
                                'id' => 'invalid_sub_child',
                                'type' => 'Spacer',
                                'props' => [],
                                'children' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->validator->validate($doc);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasError('root.children[0].children'));
        $this->assertEquals('children_not_allowed', $result->firstError()?->getCode());
    }

    public function test_rejects_missing_required_props(): void
    {
        $doc = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_sec_01',
                'type' => 'Section',
                'props' => [],
                'children' => [
                    [
                        'id' => 'heading_01',
                        'type' => 'Heading',
                        'props' => [
                            // missing required 'text' prop
                            'level' => 2,
                        ],
                        'children' => [],
                    ],
                ],
            ],
        ];

        $result = $this->validator->validate($doc);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasError('root.children[0].props.text'));
        $this->assertEquals('required_prop_missing', $result->firstError()?->getCode());
    }

    public function test_rejects_invalid_prop_types_and_enums(): void
    {
        $doc = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_sec_01',
                'type' => 'Section',
                'props' => [
                    'padding' => 'invalid_padding_value', // enum violation
                ],
                'children' => [
                    [
                        'id' => 'heading_01',
                        'type' => 'Heading',
                        'props' => [
                            'text' => 'Title',
                            'level' => 'two', // should be integer
                        ],
                        'children' => [],
                    ],
                ],
            ],
        ];

        $result = $this->validator->validate($doc);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasError('root.props.padding'));
        $this->assertTrue($result->hasError('root.children[0].props.level'));
    }

    public function test_rejects_unknown_props(): void
    {
        $doc = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_sec_01',
                'type' => 'Section',
                'props' => [
                    'unknown_custom_prop' => 'dangerous_payload',
                ],
                'children' => [],
            ],
        ];

        $result = $this->validator->validate($doc);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasError('root.props.unknown_custom_prop'));
        $this->assertEquals('unknown_prop', $result->firstError()?->getCode());
    }

    public function test_rejects_unsafe_urls_in_props(): void
    {
        $doc = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_sec_01',
                'type' => 'Section',
                'props' => [],
                'children' => [
                    [
                        'id' => 'btn_01',
                        'type' => 'Button',
                        'props' => [
                            'label' => 'Click Me',
                            'url' => 'javascript:stealCredentials()', // dangerous scheme
                        ],
                        'children' => [],
                    ],
                ],
            ],
        ];

        $result = $this->validator->validate($doc);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasError('root.children[0].props.url'));
        $this->assertEquals('unsafe_url', $result->firstError()?->getCode());
    }

    public function test_publish_validation_rejects_empty_image_source_with_node_path(): void
    {
        $doc = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_sec_01',
                'type' => 'Section',
                'props' => [],
                'children' => [[
                    'id' => 'image_empty_01',
                    'type' => 'Image',
                    'props' => ['src' => '   ', 'alt' => 'Missing'],
                    'children' => [],
                ]],
            ],
        ];

        $result = $this->validator->validateForPublishing($doc);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasError('root.children[0].props.src'));
        $this->assertSame('empty_image_source', $result->firstError()?->getCode());
    }

    public function test_publish_validation_accepts_safe_external_and_media_image_sources(): void
    {
        foreach (['https://cdn.example.com/photo.jpg', '/storage/media/12/photo.jpg', 'storage/media/12/photo.jpg'] as $src) {
            $doc = [
                'schemaVersion' => 1,
                'root' => [
                    'id' => 'root_sec_01',
                    'type' => 'Section',
                    'props' => [],
                    'children' => [[
                        'id' => 'image_valid_01',
                        'type' => 'Image',
                        'props' => ['src' => $src, 'alt' => 'Valid'],
                        'children' => [],
                    ]],
                ],
            ];

            $this->assertTrue($this->validator->validateForPublishing($doc)->isValid(), "Source '{$src}' should be publishable.");
        }
    }

    public function test_rejects_tree_exceeding_depth_limit(): void
    {
        // Create tree of depth 12 (limit is 10)
        $current = [
            'id' => 'leaf_spacer',
            'type' => 'Spacer',
            'props' => ['size' => 'small'],
            'children' => [],
        ];

        for ($depth = 11; $depth >= 1; $depth--) {
            $current = [
                'id' => "node_depth_{$depth}",
                'type' => 'Container',
                'props' => [],
                'children' => [$current],
            ];
        }

        $doc = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_sec',
                'type' => 'Section',
                'props' => [],
                'children' => [$current],
            ],
        ];

        $result = $this->validator->validate($doc);

        $this->assertFalse($result->isValid());
        $hasDepthError = false;
        foreach ($result->getErrors() as $err) {
            if ($err->getCode() === 'max_depth_exceeded') {
                $hasDepthError = true;
                break;
            }
        }
        $this->assertTrue($hasDepthError, 'Tree exceeding depth 10 must trigger max_depth_exceeded error.');
    }

    public function test_sanitizes_rich_text_html_inside_document_tree(): void
    {
        $doc = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_sec_01',
                'type' => 'Section',
                'props' => [],
                'children' => [
                    [
                        'id' => 'rt_01',
                        'type' => 'RichText',
                        'props' => [
                            'content' => '<p>Safe paragraph</p><script>alert("hacked")</script><a href="javascript:void(0)" onclick="evil()">Link</a>',
                        ],
                        'children' => [],
                    ],
                ],
            ],
        ];

        $result = $this->validator->validate($doc);

        $this->assertTrue($result->isValid(), 'Rich text with XSS should be sanitized rather than crashing.');
        $sanitizedContent = $result->getSanitizedDocument()['root']['children'][0]['props']['content'];

        $this->assertStringContainsString('<p>Safe paragraph</p>', $sanitizedContent);
        $this->assertStringNotContainsString('<script', $sanitizedContent);
        $this->assertStringNotContainsString('alert(', $sanitizedContent);
        $this->assertStringNotContainsString('onclick', $sanitizedContent);
        $this->assertStringNotContainsString('javascript:', $sanitizedContent);
    }

    public function test_preserves_valid_responsive_styles_in_sanitized_document(): void
    {
        $doc = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_sec_01',
                'type' => 'Section',
                'props' => ['padding' => 'medium', 'background' => 'transparent'],
                'children' => [
                    [
                        'id' => 'head_01',
                        'type' => 'Heading',
                        'props' => ['text' => 'Responsive Title', 'level' => 2, 'align' => 'left'],
                        'styles' => [
                            'tablet' => ['align' => 'center'],
                            'mobile' => ['align' => 'center', 'display' => 'hidden'],
                        ],
                        'children' => [],
                    ],
                ],
            ],
        ];

        $result = $this->validator->validate($doc);

        $this->assertTrue($result->isValid());
        $sanitizedNode = $result->getSanitizedDocument()['root']['children'][0];
        $this->assertArrayHasKey('styles', $sanitizedNode);
        $this->assertEquals(['align' => 'center'], $sanitizedNode['styles']['tablet']);
        $this->assertEquals(['align' => 'center', 'display' => 'hidden'], $sanitizedNode['styles']['mobile']);
    }

    public function test_omits_styles_when_absent_in_legacy_document(): void
    {
        $doc = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_sec_01',
                'type' => 'Section',
                'props' => [],
                'children' => [],
            ],
        ];

        $result = $this->validator->validate($doc);

        $this->assertTrue($result->isValid());
        $this->assertArrayNotHasKey('styles', $result->getSanitizedDocument()['root']);
    }

    public function test_prunes_empty_styles_and_empty_viewports_cleanly(): void
    {
        // 1. Empty styles array/object
        $docEmpty = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_sec_01',
                'type' => 'Section',
                'props' => [],
                'styles' => [],
                'children' => [],
            ],
        ];
        $res1 = $this->validator->validate($docEmpty);
        $this->assertTrue($res1->isValid());
        $this->assertArrayNotHasKey('styles', $res1->getSanitizedDocument()['root']);

        // 2. Viewport with empty dictionary
        $docEmptyViewport = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_sec_01',
                'type' => 'Section',
                'props' => [],
                'styles' => ['tablet' => []],
                'children' => [],
            ],
        ];
        $res2 = $this->validator->validate($docEmptyViewport);
        $this->assertTrue($res2->isValid());
        $this->assertArrayNotHasKey('styles', $res2->getSanitizedDocument()['root']);
    }

    public function test_rejects_invalid_styles_format(): void
    {
        // Primitive string
        $docString = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_sec_01',
                'type' => 'Section',
                'props' => [],
                'styles' => 'not-an-object',
                'children' => [],
            ],
        ];
        $res1 = $this->validator->validate($docString);
        $this->assertFalse($res1->isValid());
        $this->assertTrue($res1->hasError('root.styles'));
        $this->assertEquals('invalid_styles_format', $res1->firstError()?->getCode());

        // Non-empty list array
        $docList = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_sec_01',
                'type' => 'Section',
                'props' => [],
                'styles' => ['tablet', 'mobile'],
                'children' => [],
            ],
        ];
        $res2 = $this->validator->validate($docList);
        $this->assertFalse($res2->isValid());
        $this->assertEquals('invalid_styles_format', $res2->firstError()?->getCode());
    }

    public function test_rejects_disallowed_viewport_key(): void
    {
        $doc = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_sec_01',
                'type' => 'Section',
                'props' => [],
                'styles' => [
                    'desktop' => ['padding' => 'large'],
                ],
                'children' => [],
            ],
        ];

        $result = $this->validator->validate($doc);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasError('root.styles.desktop'));
        $this->assertEquals('invalid_viewport_key', $result->firstError()?->getCode());
    }

    public function test_rejects_forbidden_content_and_structural_style_overrides(): void
    {
        $doc = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_sec_01',
                'type' => 'Section',
                'props' => [],
                'children' => [
                    [
                        'id' => 'head_01',
                        'type' => 'Heading',
                        'props' => ['text' => 'Title', 'level' => 2],
                        'styles' => [
                            'mobile' => [
                                'text' => 'Hacked text',
                                'level' => 1,
                            ],
                        ],
                        'children' => [],
                    ],
                ],
            ],
        ];

        $result = $this->validator->validate($doc);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasError('root.children[0].styles.mobile.text'));
        $this->assertEquals('disallowed_style_override', $result->firstError()?->getCode());
    }

    public function test_rejects_invalid_enum_and_type_in_responsive_styles(): void
    {
        // 1. Invalid enum in Heading.align
        $doc1 = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_sec_01',
                'type' => 'Section',
                'props' => [],
                'children' => [
                    [
                        'id' => 'head_01',
                        'type' => 'Heading',
                        'props' => ['text' => 'Title', 'level' => 2],
                        'styles' => [
                            'tablet' => ['align' => 'diagonal'],
                        ],
                        'children' => [],
                    ],
                ],
            ],
        ];
        $res1 = $this->validator->validate($doc1);
        $this->assertFalse($res1->isValid());
        $this->assertTrue($res1->hasError('root.children[0].styles.tablet.align'));
        $this->assertEquals('invalid_enum_value', $res1->firstError()?->getCode());

        // 2. Out of range / non-canonical count in Columns.count (1 is not in [2, 3, 4])
        $doc2 = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_sec_01',
                'type' => 'Section',
                'props' => [],
                'children' => [
                    [
                        'id' => 'col_01',
                        'type' => 'Columns',
                        'props' => ['count' => 3],
                        'styles' => [
                            'mobile' => ['count' => 1],
                        ],
                        'children' => [],
                    ],
                ],
            ],
        ];
        $res2 = $this->validator->validate($doc2);
        $this->assertFalse($res2->isValid());
        $this->assertTrue($res2->hasError('root.children[0].styles.mobile.count'));
        $this->assertEquals('invalid_enum_value', $res2->firstError()?->getCode());

        // 3. Invalid display value
        $doc3 = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_sec_01',
                'type' => 'Section',
                'props' => [],
                'styles' => [
                    'tablet' => ['display' => 'inline-block'],
                ],
                'children' => [],
            ],
        ];
        $res3 = $this->validator->validate($doc3);
        $this->assertFalse($res3->isValid());
        $this->assertTrue($res3->hasError('root.styles.tablet.display'));
        $this->assertEquals('invalid_enum_value', $res3->firstError()?->getCode());
    }

    public function test_rejects_invalid_style_primitive_values_in_base_and_responsive_props(): void
    {
        $base = $this->validator->validate([
            'schemaVersion' => 1,
            'root' => [
                'id' => 'section_invalid_primitive',
                'type' => 'Section',
                'children' => [[
                    'id' => 'heading_invalid_primitive',
                    'type' => 'Heading',
                    'props' => [
                        'text' => 'Heading',
                        'level' => 2,
                        'fontSize' => 'calc(100vw)',
                    ],
                    'children' => [],
                ]],
            ],
        ]);
        $this->assertFalse($base->isValid());
        $this->assertSame('invalid_enum_value', $base->firstError()?->getCode());

        $responsive = $this->validator->validate([
            'schemaVersion' => 1,
            'root' => [
                'id' => 'section_invalid_responsive_primitive',
                'type' => 'Section',
                'children' => [[
                    'id' => 'rich_invalid_primitive',
                    'type' => 'RichText',
                    'props' => ['content' => '<p>Text</p>'],
                    'styles' => ['mobile' => ['textColor' => 'url(javascript:alert(1))']],
                    'children' => [],
                ]],
            ],
        ]);
        $this->assertFalse($responsive->isValid());
        $this->assertSame('invalid_enum_value', $responsive->firstError()?->getCode());
    }

    public function test_missing_locked_defaults_to_unlocked_and_is_omitted_from_sanitized_output(): void
    {
        $doc = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_unlocked',
                'type' => 'Section',
                'props' => [],
                'children' => [
                    [
                        'id' => 'child_unlocked',
                        'type' => 'Heading',
                        'props' => ['text' => 'Hello', 'level' => 2],
                    ],
                ],
            ],
        ];

        $result = $this->validator->validate($doc);
        $this->assertTrue($result->isValid());
        $sanitizedRoot = $result->getSanitizedDocument()['root'];
        $this->assertArrayNotHasKey('locked', $sanitizedRoot);
        $this->assertArrayNotHasKey('locked', $sanitizedRoot['children'][0]);
    }

    public function test_locked_true_survives_backend_sanitization(): void
    {
        $doc = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_sec',
                'type' => 'Section',
                'props' => [],
                'locked' => true,
                'children' => [
                    [
                        'id' => 'child_locked',
                        'type' => 'Heading',
                        'props' => ['text' => 'Hello', 'level' => 2],
                        'locked' => true,
                    ],
                ],
            ],
        ];

        $result = $this->validator->validate($doc);
        $this->assertTrue($result->isValid());
        $sanitizedRoot = $result->getSanitizedDocument()['root'];
        $this->assertTrue($sanitizedRoot['locked']);
        $this->assertTrue($sanitizedRoot['children'][0]['locked']);
    }

    public function test_rejects_invalid_locked_types_with_invalid_locked_type_error(): void
    {
        $invalidTypes = [
            'string' => 'true',
            'integer' => 1,
            'float' => 0.0,
            'array' => [true],
            'null' => null,
        ];

        foreach ($invalidTypes as $label => $val) {
            $doc = [
                'schemaVersion' => 1,
                'root' => [
                    'id' => "root_{$label}",
                    'type' => 'Section',
                    'props' => [],
                    'locked' => $val,
                    'children' => [],
                ],
            ];

            $result = $this->validator->validate($doc);
            $this->assertFalse($result->isValid(), "Lock value of type {$label} should be rejected.");
            $this->assertSame('invalid_locked_type', $result->firstError()?->getCode());
            $this->assertSame('root.locked', $result->firstError()?->getPath());
        }
    }

    public function test_locked_false_is_valid_and_omitted_as_unlocked(): void
    {
        $doc = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_sec',
                'type' => 'Section',
                'props' => [],
                'locked' => false,
                'children' => [],
            ],
        ];

        $result = $this->validator->validate($doc);
        $this->assertTrue($result->isValid());
        $sanitizedRoot = $result->getSanitizedDocument()['root'];
        $this->assertArrayNotHasKey('locked', $sanitizedRoot);
    }

    public function test_missing_hidden_defaults_to_visible_and_is_omitted_from_sanitized_output(): void
    {
        $doc = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_visible',
                'type' => 'Section',
                'props' => [],
                'children' => [
                    [
                        'id' => 'child_visible',
                        'type' => 'Heading',
                        'props' => ['text' => 'Hello', 'level' => 2],
                    ],
                ],
            ],
        ];

        $result = $this->validator->validate($doc);
        $this->assertTrue($result->isValid());
        $sanitizedRoot = $result->getSanitizedDocument()['root'];
        $this->assertArrayNotHasKey('hidden', $sanitizedRoot);
        $this->assertArrayNotHasKey('hidden', $sanitizedRoot['children'][0]);
    }

    public function test_hidden_true_survives_backend_sanitization_for_non_root_nodes(): void
    {
        $doc = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_sec',
                'type' => 'Section',
                'props' => [],
                'children' => [
                    [
                        'id' => 'child_hidden',
                        'type' => 'Heading',
                        'props' => ['text' => 'Hello', 'level' => 2],
                        'hidden' => true,
                    ],
                ],
            ],
        ];

        $result = $this->validator->validate($doc);
        $this->assertTrue($result->isValid());
        $sanitizedRoot = $result->getSanitizedDocument()['root'];
        $this->assertArrayNotHasKey('hidden', $sanitizedRoot);
        $this->assertTrue($sanitizedRoot['children'][0]['hidden']);
    }

    public function test_hidden_false_survives_backend_sanitization(): void
    {
        $doc = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_sec',
                'type' => 'Section',
                'props' => [],
                'children' => [
                    [
                        'id' => 'child_unhidden',
                        'type' => 'Heading',
                        'props' => ['text' => 'Hello', 'level' => 2],
                        'hidden' => false,
                    ],
                ],
            ],
        ];

        $result = $this->validator->validate($doc);
        $this->assertTrue($result->isValid());
        $sanitizedRoot = $result->getSanitizedDocument()['root'];
        $this->assertFalse($sanitizedRoot['children'][0]['hidden']);
    }

    public function test_rejects_invalid_hidden_types_with_invalid_hidden_type_error(): void
    {
        $invalidTypes = [
            'string' => 'true',
            'integer' => 1,
            'float' => 0.0,
            'array' => [true],
            'null' => null,
        ];

        foreach ($invalidTypes as $label => $val) {
            $doc = [
                'schemaVersion' => 1,
                'root' => [
                    'id' => 'root_sec',
                    'type' => 'Section',
                    'props' => [],
                    'children' => [
                        [
                            'id' => "child_{$label}",
                            'type' => 'Heading',
                            'props' => ['text' => 'Hello', 'level' => 2],
                            'hidden' => $val,
                        ],
                    ],
                ],
            ];

            $result = $this->validator->validate($doc);
            $this->assertFalse($result->isValid(), "Hidden value of type {$label} should be rejected.");
            $this->assertSame('invalid_hidden_type', $result->firstError()?->getCode());
            $this->assertSame('root.children[0].hidden', $result->firstError()?->getPath());
        }
    }

    public function test_root_cannot_be_hidden_and_is_rejected_with_root_cannot_be_hidden(): void
    {
        $doc = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_sec',
                'type' => 'Section',
                'props' => [],
                'hidden' => true,
                'children' => [],
            ],
        ];

        $result = $this->validator->validate($doc);
        $this->assertFalse($result->isValid());
        $this->assertSame('root_cannot_be_hidden', $result->firstError()?->getCode());
        $this->assertSame('root.hidden', $result->firstError()?->getPath());
    }

    public function test_valid_node_label_survives_backend_sanitization(): void
    {
        $doc = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_sec',
                'type' => 'Section',
                'props' => [],
                'label' => 'Main Hero Section',
                'children' => [
                    [
                        'id' => 'child_btn',
                        'type' => 'Button',
                        'props' => ['label' => 'Click', 'url' => '#'],
                        'label' => 'Primary CTA',
                        'children' => [],
                    ],
                ],
            ],
        ];

        $result = $this->validator->validate($doc);
        $this->assertTrue($result->isValid());

        $sanitizedRoot = $result->getSanitizedDocument()['root'];
        $this->assertSame('Main Hero Section', $sanitizedRoot['label']);
        $this->assertSame('Primary CTA', $sanitizedRoot['children'][0]['label']);
    }

    public function test_node_label_is_trimmed_and_stripped_of_html_tags(): void
    {
        $doc = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_sec',
                'type' => 'Section',
                'props' => [],
                'children' => [
                    [
                        'id' => 'child_heading',
                        'type' => 'Heading',
                        'props' => ['text' => 'Title', 'level' => 1],
                        'label' => '   <b>Featured</b> Heading   ',
                        'children' => [],
                    ],
                ],
            ],
        ];

        $result = $this->validator->validate($doc);
        $this->assertTrue($result->isValid());

        $sanitizedChild = $result->getSanitizedDocument()['root']['children'][0];
        $this->assertSame('Featured Heading', $sanitizedChild['label']);
    }

    public function test_empty_or_null_node_label_is_omitted_from_sanitized_output(): void
    {
        $doc = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_sec',
                'type' => 'Section',
                'props' => [],
                'label' => '   ',
                'children' => [
                    [
                        'id' => 'child_heading',
                        'type' => 'Heading',
                        'props' => ['text' => 'Title', 'level' => 1],
                        'label' => null,
                        'children' => [],
                    ],
                    [
                        'id' => 'child_btn',
                        'type' => 'Button',
                        'props' => ['label' => 'Click', 'url' => '#'],
                        'children' => [],
                    ],
                ],
            ],
        ];

        $result = $this->validator->validate($doc);
        $this->assertTrue($result->isValid());

        $sanitizedRoot = $result->getSanitizedDocument()['root'];
        $this->assertArrayNotHasKey('label', $sanitizedRoot);
        $this->assertArrayNotHasKey('label', $sanitizedRoot['children'][0]);
        $this->assertArrayNotHasKey('label', $sanitizedRoot['children'][1]);
    }

    public function test_rejects_invalid_node_label_types_with_invalid_label_type(): void
    {
        $invalidTypes = [123, true, false, ['array'], 3.14];

        foreach ($invalidTypes as $val) {
            $doc = [
                'schemaVersion' => 1,
                'root' => [
                    'id' => 'root_sec',
                    'type' => 'Section',
                    'props' => [],
                    'children' => [
                        [
                            'id' => 'child_heading',
                            'type' => 'Heading',
                            'props' => ['text' => 'Title', 'level' => 1],
                            'label' => $val,
                            'children' => [],
                        ],
                    ],
                ],
            ];

            $result = $this->validator->validate($doc);
            $this->assertFalse($result->isValid());
            $this->assertSame('invalid_label_type', $result->firstError()?->getCode());
            $this->assertSame('root.children[0].label', $result->firstError()?->getPath());
        }
    }

    public function test_rejects_node_label_exceeding_max_length_with_label_too_long(): void
    {
        $doc = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_sec',
                'type' => 'Section',
                'props' => [],
                'children' => [
                    [
                        'id' => 'child_heading',
                        'type' => 'Heading',
                        'props' => ['text' => 'Title', 'level' => 1],
                        'label' => str_repeat('A', 65),
                        'children' => [],
                    ],
                ],
            ],
        ];

        $result = $this->validator->validate($doc);
        $this->assertFalse($result->isValid());
        $this->assertSame('label_too_long', $result->firstError()?->getCode());
        $this->assertSame('root.children[0].label', $result->firstError()?->getPath());
    }
}
