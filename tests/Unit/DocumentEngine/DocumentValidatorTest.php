<?php

namespace Tests\Unit\DocumentEngine;

use App\Domains\DocumentEngine\DocumentLimits;
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
}
