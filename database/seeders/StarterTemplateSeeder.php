<?php

namespace Database\Seeders;

use App\Domains\DocumentEngine\Validation\DocumentValidator;
use App\Models\PageTemplate;
use Illuminate\Database\Seeder;
use RuntimeException;

class StarterTemplateSeeder extends Seeder
{
    /**
     * Run the starter template database seeder.
     *
     * Non-destructive policy:
     * Canonical starter templates are inserted if they do not yet exist.
     * If a template with the given slug already exists with website_id = NULL,
     * it is preserved intact to respect any intentional customizations or edits,
     * unless the force parameter is explicitly true.
     *
     * @param  bool  $force  When true, overwrites existing global templates with default blueprints.
     */
    public function run(bool $force = false): void
    {
        $validator = app(DocumentValidator::class);
        $templates = $this->getStarterTemplates();

        foreach ($templates as $data) {
            // Validate every template fixture against authoritative Document Schema v1
            $result = $validator->validate($data['document_json']);
            if (! $result->isValid()) {
                $errorMsgs = array_map(fn ($e) => "[{$e->getPath()}] {$e->getMessage()}", $result->getErrors());
                throw new RuntimeException(
                    "Seeded template '{$data['slug']}' failed Document Schema v1 validation: ".implode(', ', $errorMsgs)
                );
            }

            $existing = PageTemplate::whereNull('website_id')
                ->where('slug', $data['slug'])
                ->first();

            if ($existing) {
                if ($force) {
                    $existing->update([
                        'name' => $data['name'],
                        'description' => $data['description'],
                        'category' => $data['category'],
                        'schema_version' => 1,
                        'document_json' => $data['document_json'],
                        'is_active' => true,
                    ]);
                }

                // Non-destructive: Preserve existing global template
                continue;
            }

            PageTemplate::create([
                'website_id' => null,
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'],
                'category' => $data['category'],
                'schema_version' => 1,
                'document_json' => $data['document_json'],
                'is_active' => true,
            ]);
        }
    }

    /**
     * Canonical starter template fixtures.
     *
     * @return list<array{
     *     name: string,
     *     slug: string,
     *     description: string,
     *     category: string,
     *     document_json: array<string, mixed>
     * }>
     */
    public function getStarterTemplates(): array
    {
        return [
            // 1. Blank Canvas
            [
                'name' => 'Blank Canvas',
                'slug' => 'blank',
                'description' => 'Clean empty starting canvas with a single centered container.',
                'category' => 'blank',
                'document_json' => [
                    'schemaVersion' => 1,
                    'root' => [
                        'id' => 'tmpl_blank_root',
                        'type' => 'Section',
                        'props' => [
                            'padding' => 'medium',
                            'background' => 'transparent',
                        ],
                        'children' => [
                            [
                                'id' => 'tmpl_blank_container',
                                'type' => 'Container',
                                'props' => [
                                    'maxWidth' => 'lg',
                                    'centered' => true,
                                ],
                                'children' => [
                                    [
                                        'id' => 'tmpl_blank_heading',
                                        'type' => 'Heading',
                                        'props' => [
                                            'text' => 'Welcome to Your New Page',
                                            'level' => 1,
                                            'align' => 'center',
                                        ],
                                    ],
                                    [
                                        'id' => 'tmpl_blank_text',
                                        'type' => 'RichText',
                                        'props' => [
                                            'content' => '<p class="text-center text-muted-foreground">Start building your layout by adding sections, text, and components.</p>',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            // 2. Business Landing
            [
                'name' => 'Business Landing',
                'slug' => 'business-landing',
                'description' => 'Modern corporate landing page with hero banner, feature columns, and call to action.',
                'category' => 'landing',
                'document_json' => [
                    'schemaVersion' => 1,
                    'root' => [
                        'id' => 'tmpl_biz_root',
                        'type' => 'Section',
                        'props' => [
                            'padding' => 'none',
                            'background' => 'transparent',
                        ],
                        'children' => [
                            [
                                'id' => 'tmpl_biz_hero',
                                'type' => 'Hero',
                                'props' => [
                                    'title' => 'Accelerate Your Business Growth',
                                    'subtitle' => 'Powerful solutions engineered for modern enterprises to scale revenue, streamline operations, and deliver exceptional experiences.',
                                    'align' => 'center',
                                    'ctaText' => 'Get Started Free',
                                    'ctaUrl' => 'https://example.com/signup',
                                ],
                            ],
                            [
                                'id' => 'tmpl_biz_features_sec',
                                'type' => 'Section',
                                'props' => [
                                    'padding' => 'large',
                                    'background' => 'muted',
                                ],
                                'children' => [
                                    [
                                        'id' => 'tmpl_biz_features_container',
                                        'type' => 'Container',
                                        'props' => [
                                            'maxWidth' => 'lg',
                                            'centered' => true,
                                        ],
                                        'children' => [
                                            [
                                                'id' => 'tmpl_biz_features_h2',
                                                'type' => 'Heading',
                                                'props' => [
                                                    'text' => 'Engineered for Performance and Security',
                                                    'level' => 2,
                                                    'align' => 'center',
                                                ],
                                            ],
                                            [
                                                'id' => 'tmpl_biz_features_spacer',
                                                'type' => 'Spacer',
                                                'props' => [
                                                    'size' => 'medium',
                                                ],
                                            ],
                                            [
                                                'id' => 'tmpl_biz_cols',
                                                'type' => 'Columns',
                                                'props' => [
                                                    'count' => 3,
                                                    'gap' => 'large',
                                                ],
                                                'children' => [
                                                    [
                                                        'id' => 'tmpl_biz_col_1',
                                                        'type' => 'Container',
                                                        'props' => ['maxWidth' => 'full', 'centered' => true],
                                                        'children' => [
                                                            [
                                                                'id' => 'tmpl_biz_col1_h',
                                                                'type' => 'Heading',
                                                                'props' => ['text' => 'High Performance', 'level' => 3, 'align' => 'left'],
                                                            ],
                                                            [
                                                                'id' => 'tmpl_biz_col1_p',
                                                                'type' => 'RichText',
                                                                'props' => ['content' => '<p>Built with modern architecture for sub-second load times and rock-solid uptime.</p>'],
                                                            ],
                                                        ],
                                                    ],
                                                    [
                                                        'id' => 'tmpl_biz_col_2',
                                                        'type' => 'Container',
                                                        'props' => ['maxWidth' => 'full', 'centered' => true],
                                                        'children' => [
                                                            [
                                                                'id' => 'tmpl_biz_col2_h',
                                                                'type' => 'Heading',
                                                                'props' => ['text' => 'Enterprise Security', 'level' => 3, 'align' => 'left'],
                                                            ],
                                                            [
                                                                'id' => 'tmpl_biz_col2_p',
                                                                'type' => 'RichText',
                                                                'props' => ['content' => '<p>Tenant isolation, encrypted channels, and comprehensive audit trails by default.</p>'],
                                                            ],
                                                        ],
                                                    ],
                                                    [
                                                        'id' => 'tmpl_biz_col_3',
                                                        'type' => 'Container',
                                                        'props' => ['maxWidth' => 'full', 'centered' => true],
                                                        'children' => [
                                                            [
                                                                'id' => 'tmpl_biz_col3_h',
                                                                'type' => 'Heading',
                                                                'props' => ['text' => 'Seamless Scaling', 'level' => 3, 'align' => 'left'],
                                                            ],
                                                            [
                                                                'id' => 'tmpl_biz_col3_p',
                                                                'type' => 'RichText',
                                                                'props' => ['content' => '<p>Scale seamlessly from small teams to millions of global end-users without friction.</p>'],
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            [
                                'id' => 'tmpl_biz_cta_sec',
                                'type' => 'Section',
                                'props' => [
                                    'padding' => 'large',
                                    'background' => 'transparent',
                                ],
                                'children' => [
                                    [
                                        'id' => 'tmpl_biz_cta_container',
                                        'type' => 'Container',
                                        'props' => [
                                            'maxWidth' => 'md',
                                            'centered' => true,
                                        ],
                                        'children' => [
                                            [
                                                'id' => 'tmpl_biz_cta_h2',
                                                'type' => 'Heading',
                                                'props' => [
                                                    'text' => 'Ready to Upgrade Your Operations?',
                                                    'level' => 2,
                                                    'align' => 'center',
                                                ],
                                            ],
                                            [
                                                'id' => 'tmpl_biz_cta_text',
                                                'type' => 'RichText',
                                                'props' => [
                                                    'content' => '<p class="text-center">Join thousands of teams building the future with our trusted platform.</p>',
                                                ],
                                            ],
                                            [
                                                'id' => 'tmpl_biz_cta_spacer',
                                                'type' => 'Spacer',
                                                'props' => [
                                                    'size' => 'small',
                                                ],
                                            ],
                                            [
                                                'id' => 'tmpl_biz_cta_btn',
                                                'type' => 'Button',
                                                'props' => [
                                                    'label' => 'Start Your 14-Day Free Trial',
                                                    'url' => 'https://example.com/trial',
                                                    'variant' => 'primary',
                                                    'size' => 'lg',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            // 3. Portfolio & Agency
            [
                'name' => 'Portfolio & Agency',
                'slug' => 'portfolio-agency',
                'description' => 'Showcase layout for creative agencies, studios, and freelancers with project showcases.',
                'category' => 'portfolio',
                'document_json' => [
                    'schemaVersion' => 1,
                    'root' => [
                        'id' => 'tmpl_port_root',
                        'type' => 'Section',
                        'props' => [
                            'padding' => 'none',
                            'background' => 'transparent',
                        ],
                        'children' => [
                            [
                                'id' => 'tmpl_port_hero',
                                'type' => 'Hero',
                                'props' => [
                                    'title' => 'Bespoke Digital Design & Craft',
                                    'subtitle' => 'We design memorable brand identities, interactive digital experiences, and intuitive web platforms for visionary brands.',
                                    'align' => 'center',
                                    'ctaText' => 'Explore Selected Works',
                                    'ctaUrl' => 'https://example.com/works',
                                ],
                            ],
                            [
                                'id' => 'tmpl_port_work_sec',
                                'type' => 'Section',
                                'props' => [
                                    'padding' => 'large',
                                    'background' => 'transparent',
                                ],
                                'children' => [
                                    [
                                        'id' => 'tmpl_port_work_container',
                                        'type' => 'Container',
                                        'props' => [
                                            'maxWidth' => 'lg',
                                            'centered' => true,
                                        ],
                                        'children' => [
                                            [
                                                'id' => 'tmpl_port_work_h2',
                                                'type' => 'Heading',
                                                'props' => [
                                                    'text' => 'Selected Case Studies',
                                                    'level' => 2,
                                                    'align' => 'left',
                                                ],
                                            ],
                                            [
                                                'id' => 'tmpl_port_work_spacer',
                                                'type' => 'Spacer',
                                                'props' => ['size' => 'medium'],
                                            ],
                                            [
                                                'id' => 'tmpl_port_grid',
                                                'type' => 'CardGrid',
                                                'props' => [
                                                    'columns' => 3,
                                                    'gap' => 'large',
                                                ],
                                                'children' => [
                                                    [
                                                        'id' => 'tmpl_port_card1',
                                                        'type' => 'Container',
                                                        'props' => ['maxWidth' => 'full', 'centered' => true],
                                                        'children' => [
                                                            [
                                                                'id' => 'tmpl_port_c1_h',
                                                                'type' => 'Heading',
                                                                'props' => ['text' => 'Fintech Next-Gen App', 'level' => 3, 'align' => 'left'],
                                                            ],
                                                            [
                                                                'id' => 'tmpl_port_c1_p',
                                                                'type' => 'RichText',
                                                                'props' => ['content' => '<p>Modern mobile-first design system and dashboard UI for 2M+ active retail investors.</p>'],
                                                            ],
                                                        ],
                                                    ],
                                                    [
                                                        'id' => 'tmpl_port_card2',
                                                        'type' => 'Container',
                                                        'props' => ['maxWidth' => 'full', 'centered' => true],
                                                        'children' => [
                                                            [
                                                                'id' => 'tmpl_port_c2_h',
                                                                'type' => 'Heading',
                                                                'props' => ['text' => 'AI Studio Interface', 'level' => 3, 'align' => 'left'],
                                                            ],
                                                            [
                                                                'id' => 'tmpl_port_c2_p',
                                                                'type' => 'RichText',
                                                                'props' => ['content' => '<p>Intuitive canvas design and workflow orchestration for generative creators.</p>'],
                                                            ],
                                                        ],
                                                    ],
                                                    [
                                                        'id' => 'tmpl_port_card3',
                                                        'type' => 'Container',
                                                        'props' => ['maxWidth' => 'full', 'centered' => true],
                                                        'children' => [
                                                            [
                                                                'id' => 'tmpl_port_c3_h',
                                                                'type' => 'Heading',
                                                                'props' => ['text' => 'Sustainable Commerce', 'level' => 3, 'align' => 'left'],
                                                            ],
                                                            [
                                                                'id' => 'tmpl_port_c3_p',
                                                                'type' => 'RichText',
                                                                'props' => ['content' => '<p>Bespoke headless storefront increasing checkout conversion by 34%.</p>'],
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            [
                                'id' => 'tmpl_port_cta_sec',
                                'type' => 'Section',
                                'props' => [
                                    'padding' => 'large',
                                    'background' => 'muted',
                                ],
                                'children' => [
                                    [
                                        'id' => 'tmpl_port_cta_container',
                                        'type' => 'Container',
                                        'props' => ['maxWidth' => 'md', 'centered' => true],
                                        'children' => [
                                            [
                                                'id' => 'tmpl_port_cta_h2',
                                                'type' => 'Heading',
                                                'props' => ['text' => "Let's Build Something Exceptional", 'level' => 2, 'align' => 'center'],
                                            ],
                                            [
                                                'id' => 'tmpl_port_cta_p',
                                                'type' => 'RichText',
                                                'props' => ['content' => '<p class="text-center">Have a project in mind? We partner with ambitious leaders worldwide.</p>'],
                                            ],
                                            [
                                                'id' => 'tmpl_port_cta_spacer',
                                                'type' => 'Spacer',
                                                'props' => ['size' => 'small'],
                                            ],
                                            [
                                                'id' => 'tmpl_port_cta_btn',
                                                'type' => 'Button',
                                                'props' => [
                                                    'label' => 'Start a Conversation',
                                                    'url' => 'https://example.com/contact',
                                                    'variant' => 'primary',
                                                    'size' => 'lg',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            // 4. Product Waitlist
            [
                'name' => 'Product Waitlist',
                'slug' => 'product-waitlist',
                'description' => 'High-conversion pre-launch page with headline, feature highlights, and FAQ accordion.',
                'category' => 'landing',
                'document_json' => [
                    'schemaVersion' => 1,
                    'root' => [
                        'id' => 'tmpl_wait_root',
                        'type' => 'Section',
                        'props' => [
                            'padding' => 'none',
                            'background' => 'transparent',
                        ],
                        'children' => [
                            [
                                'id' => 'tmpl_wait_hero',
                                'type' => 'Hero',
                                'props' => [
                                    'title' => 'The Next Era of Team Productivity',
                                    'subtitle' => 'A unified workspace built for deep focus, structured collaboration, and intelligent automation. Early access opening soon.',
                                    'align' => 'center',
                                    'ctaText' => 'Request Early Access',
                                    'ctaUrl' => 'https://example.com/waitlist',
                                ],
                            ],
                            [
                                'id' => 'tmpl_wait_benefits_sec',
                                'type' => 'Section',
                                'props' => [
                                    'padding' => 'large',
                                    'background' => 'muted',
                                ],
                                'children' => [
                                    [
                                        'id' => 'tmpl_wait_benefits_container',
                                        'type' => 'Container',
                                        'props' => ['maxWidth' => 'lg', 'centered' => true],
                                        'children' => [
                                            [
                                                'id' => 'tmpl_wait_benefits_h2',
                                                'type' => 'Heading',
                                                'props' => ['text' => 'Why Join the Priority Beta?', 'level' => 2, 'align' => 'center'],
                                            ],
                                            [
                                                'id' => 'tmpl_wait_benefits_spacer',
                                                'type' => 'Spacer',
                                                'props' => ['size' => 'medium'],
                                            ],
                                            [
                                                'id' => 'tmpl_wait_benefits_cols',
                                                'type' => 'Columns',
                                                'props' => ['count' => 2, 'gap' => 'large'],
                                                'children' => [
                                                    [
                                                        'id' => 'tmpl_wait_bcol_1',
                                                        'type' => 'Container',
                                                        'props' => ['maxWidth' => 'full', 'centered' => true],
                                                        'children' => [
                                                            [
                                                                'id' => 'tmpl_wait_b1_h',
                                                                'type' => 'Heading',
                                                                'props' => ['text' => 'Lifetime Founder Pricing', 'level' => 3, 'align' => 'left'],
                                                            ],
                                                            [
                                                                'id' => 'tmpl_wait_b1_p',
                                                                'type' => 'RichText',
                                                                'props' => ['content' => '<p>Lock in grandfathered founder rates with unlimited team members and storage forever.</p>'],
                                                            ],
                                                        ],
                                                    ],
                                                    [
                                                        'id' => 'tmpl_wait_bcol_2',
                                                        'type' => 'Container',
                                                        'props' => ['maxWidth' => 'full', 'centered' => true],
                                                        'children' => [
                                                            [
                                                                'id' => 'tmpl_wait_b2_h',
                                                                'type' => 'Heading',
                                                                'props' => ['text' => 'Co-Create the Roadmap', 'level' => 3, 'align' => 'left'],
                                                            ],
                                                            [
                                                                'id' => 'tmpl_wait_b2_p',
                                                                'type' => 'RichText',
                                                                'props' => ['content' => '<p>Work directly with our founders and lead engineers during private weekly feedback sessions.</p>'],
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            [
                                'id' => 'tmpl_wait_faq_sec',
                                'type' => 'Section',
                                'props' => [
                                    'padding' => 'large',
                                    'background' => 'transparent',
                                ],
                                'children' => [
                                    [
                                        'id' => 'tmpl_wait_faq_container',
                                        'type' => 'Container',
                                        'props' => ['maxWidth' => 'md', 'centered' => true],
                                        'children' => [
                                            [
                                                'id' => 'tmpl_wait_faq_h2',
                                                'type' => 'Heading',
                                                'props' => ['text' => 'Frequently Asked Questions', 'level' => 2, 'align' => 'center'],
                                            ],
                                            [
                                                'id' => 'tmpl_wait_faq_spacer',
                                                'type' => 'Spacer',
                                                'props' => ['size' => 'medium'],
                                            ],
                                            [
                                                'id' => 'tmpl_wait_faq_list',
                                                'type' => 'FAQ',
                                                'props' => [
                                                    'items' => [
                                                        [
                                                            'question' => 'When will invitations begin rolling out?',
                                                            'answer' => '<p>We are opening private access in weekly cohorts starting next month. Priority is based on registration timestamp.</p>',
                                                        ],
                                                        [
                                                            'question' => 'How is our data protected?',
                                                            'answer' => '<p>All workspace data is strictly isolated per tenant, encrypted at rest and in transit, with automated daily backups.</p>',
                                                        ],
                                                        [
                                                            'question' => 'Will there be a free tier at launch?',
                                                            'answer' => '<p>Yes, individual creators and small teams will have access to a robust free tier with core features.</p>',
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            // 5. Lead Generation
            [
                'name' => 'Lead Generation',
                'slug' => 'lead-generation',
                'description' => 'Conversion-focused marketing layout tailored for service inquiries and consultations.',
                'category' => 'lead_gen',
                'document_json' => [
                    'schemaVersion' => 1,
                    'root' => [
                        'id' => 'tmpl_lead_root',
                        'type' => 'Section',
                        'props' => [
                            'padding' => 'none',
                            'background' => 'transparent',
                        ],
                        'children' => [
                            [
                                'id' => 'tmpl_lead_hero',
                                'type' => 'Hero',
                                'props' => [
                                    'title' => 'Unlock High-Velocity Revenue Growth',
                                    'subtitle' => 'We help B2B technology companies optimize sales funnels, increase pipeline conversions, and maximize annual contract value.',
                                    'align' => 'center',
                                    'ctaText' => 'Book Free Consultation',
                                    'ctaUrl' => 'https://example.com/consult',
                                ],
                            ],
                            [
                                'id' => 'tmpl_lead_process_sec',
                                'type' => 'Section',
                                'props' => [
                                    'padding' => 'large',
                                    'background' => 'muted',
                                ],
                                'children' => [
                                    [
                                        'id' => 'tmpl_lead_process_container',
                                        'type' => 'Container',
                                        'props' => ['maxWidth' => 'lg', 'centered' => true],
                                        'children' => [
                                            [
                                                'id' => 'tmpl_lead_process_h2',
                                                'type' => 'Heading',
                                                'props' => ['text' => 'Our 3-Phase Transformation Framework', 'level' => 2, 'align' => 'center'],
                                            ],
                                            [
                                                'id' => 'tmpl_lead_process_spacer',
                                                'type' => 'Spacer',
                                                'props' => ['size' => 'medium'],
                                            ],
                                            [
                                                'id' => 'tmpl_lead_cols',
                                                'type' => 'Columns',
                                                'props' => ['count' => 3, 'gap' => 'large'],
                                                'children' => [
                                                    [
                                                        'id' => 'tmpl_lead_p1',
                                                        'type' => 'Container',
                                                        'props' => ['maxWidth' => 'full', 'centered' => true],
                                                        'children' => [
                                                            [
                                                                'id' => 'tmpl_lead_p1_h',
                                                                'type' => 'Heading',
                                                                'props' => ['text' => '1. Pipeline Diagnostic', 'level' => 3, 'align' => 'left'],
                                                            ],
                                                            [
                                                                'id' => 'tmpl_lead_p1_t',
                                                                'type' => 'RichText',
                                                                'props' => ['content' => '<p>Identify drop-off friction points, unqualified leads, and missed expansion opportunities.</p>'],
                                                            ],
                                                        ],
                                                    ],
                                                    [
                                                        'id' => 'tmpl_lead_p2',
                                                        'type' => 'Container',
                                                        'props' => ['maxWidth' => 'full', 'centered' => true],
                                                        'children' => [
                                                            [
                                                                'id' => 'tmpl_lead_p2_h',
                                                                'type' => 'Heading',
                                                                'props' => ['text' => '2. Funnel Architecture', 'level' => 3, 'align' => 'left'],
                                                            ],
                                                            [
                                                                'id' => 'tmpl_lead_p2_t',
                                                                'type' => 'RichText',
                                                                'props' => ['content' => '<p>Build high-converting landing pages, structured qualification flows, and automated nurture sequences.</p>'],
                                                            ],
                                                        ],
                                                    ],
                                                    [
                                                        'id' => 'tmpl_lead_p3',
                                                        'type' => 'Container',
                                                        'props' => ['maxWidth' => 'full', 'centered' => true],
                                                        'children' => [
                                                            [
                                                                'id' => 'tmpl_lead_p3_h',
                                                                'type' => 'Heading',
                                                                'props' => ['text' => '3. Scale & Optimize', 'level' => 3, 'align' => 'left'],
                                                            ],
                                                            [
                                                                'id' => 'tmpl_lead_p3_t',
                                                                'type' => 'RichText',
                                                                'props' => ['content' => '<p>Execute rigorous conversion experiments and track closing velocity against revenue goals.</p>'],
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            [
                                'id' => 'tmpl_lead_cta_sec',
                                'type' => 'Section',
                                'props' => [
                                    'padding' => 'large',
                                    'background' => 'transparent',
                                ],
                                'children' => [
                                    [
                                        'id' => 'tmpl_lead_cta_container',
                                        'type' => 'Container',
                                        'props' => ['maxWidth' => 'md', 'centered' => true],
                                        'children' => [
                                            [
                                                'id' => 'tmpl_lead_cta_h2',
                                                'type' => 'Heading',
                                                'props' => ['text' => 'Schedule Your 30-Minute Growth Audit', 'level' => 2, 'align' => 'center'],
                                            ],
                                            [
                                                'id' => 'tmpl_lead_cta_text',
                                                'type' => 'RichText',
                                                'props' => ['content' => '<p class="text-center">Receive actionable recommendations tailored specifically to your sales model. No pressure, no generic sales pitch.</p>'],
                                            ],
                                            [
                                                'id' => 'tmpl_lead_cta_spacer',
                                                'type' => 'Spacer',
                                                'props' => ['size' => 'small'],
                                            ],
                                            [
                                                'id' => 'tmpl_lead_cta_btn',
                                                'type' => 'Button',
                                                'props' => [
                                                    'label' => 'Reserve Your Session',
                                                    'url' => 'https://example.com/reserve',
                                                    'variant' => 'primary',
                                                    'size' => 'lg',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
