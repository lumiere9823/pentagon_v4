<?php

namespace App\Domains\Websites\Services;

use App\Domains\DocumentEngine\DocumentLimits;
use App\Domains\DocumentEngine\Validation\DocumentValidator;
use InvalidArgumentException;
use RuntimeException;

class StarterSetCatalog
{
    public function __construct(
        protected DocumentValidator $validator
    ) {}

    /**
     * Retrieve the starter pages for a given set identifier.
     *
     * @return list<array{
     *     title: string,
     *     slug: string,
     *     is_homepage: bool,
     *     document: array<string, mixed>,
     *     summary: string
     * }>
     */
    public function get(string $name = 'default'): array
    {
        return match ($name) {
            'default' => $this->getDefaultStarterSet(),
            default => throw new InvalidArgumentException("Unknown starter set: {$name}"),
        };
    }

    /**
     * Canonical single Home starter definition.
     *
     * @return array{
     *     title: string,
     *     slug: string,
     *     is_homepage: bool,
     *     document: array<string, mixed>,
     *     summary: string
     * }
     */
    public function getCanonicalHomeDefinition(): array
    {
        $homeDoc = [
            'schemaVersion' => DocumentLimits::CURRENT_SCHEMA_VERSION,
            'root' => [
                'id' => 'sec_starter_home',
                'type' => 'Section',
                'props' => [
                    'padding' => 'large',
                    'background' => 'transparent',
                ],
                'children' => [
                    [
                        'id' => 'cnt_starter_home',
                        'type' => 'Container',
                        'props' => [
                            'maxWidth' => 'lg',
                            'centered' => true,
                        ],
                        'children' => [
                            [
                                'id' => 'hd_starter_home',
                                'type' => 'Heading',
                                'props' => [
                                    'text' => 'Welcome to your new website',
                                    'level' => 1,
                                    'align' => 'center',
                                ],
                            ],
                            [
                                'id' => 'rt_starter_home',
                                'type' => 'RichText',
                                'props' => [
                                    'content' => '<p class="text-center text-muted-foreground">This is your homepage. Start editing this content in the page builder.</p>',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'pageSettings' => [
                'title' => 'Home',
                'description' => 'Welcome to our website',
            ],
        ];

        return [
            'title' => 'Home',
            'slug' => 'home',
            'is_homepage' => true,
            'document' => $this->validateAndCanonicalize($homeDoc, 'Home'),
            'summary' => 'Initial starter Home page created',
        ];
    }

    /**
     * Default starter set containing Home, About, and Contact.
     *
     * @return list<array{
     *     title: string,
     *     slug: string,
     *     is_homepage: bool,
     *     document: array<string, mixed>,
     *     summary: string
     * }>
     */
    protected function getDefaultStarterSet(): array
    {
        $aboutDoc = [
            'schemaVersion' => DocumentLimits::CURRENT_SCHEMA_VERSION,
            'root' => [
                'id' => 'sec_starter_about',
                'type' => 'Section',
                'props' => [
                    'padding' => 'large',
                    'background' => 'transparent',
                ],
                'children' => [
                    [
                        'id' => 'cnt_starter_about',
                        'type' => 'Container',
                        'props' => [
                            'maxWidth' => 'lg',
                            'centered' => true,
                        ],
                        'children' => [
                            [
                                'id' => 'hd_starter_about',
                                'type' => 'Heading',
                                'props' => [
                                    'text' => 'About Us',
                                    'level' => 1,
                                    'align' => 'center',
                                ],
                            ],
                            [
                                'id' => 'rt_starter_about',
                                'type' => 'RichText',
                                'props' => [
                                    'content' => '<p class="text-center text-muted-foreground">Learn more about our mission, vision, and team.</p>',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'pageSettings' => [
                'title' => 'About',
                'description' => 'Learn more about our team and mission',
            ],
        ];

        $contactDoc = [
            'schemaVersion' => DocumentLimits::CURRENT_SCHEMA_VERSION,
            'root' => [
                'id' => 'sec_starter_contact',
                'type' => 'Section',
                'props' => [
                    'padding' => 'large',
                    'background' => 'transparent',
                ],
                'children' => [
                    [
                        'id' => 'cnt_starter_contact',
                        'type' => 'Container',
                        'props' => [
                            'maxWidth' => 'lg',
                            'centered' => true,
                        ],
                        'children' => [
                            [
                                'id' => 'hd_starter_contact',
                                'type' => 'Heading',
                                'props' => [
                                    'text' => 'Contact Us',
                                    'level' => 1,
                                    'align' => 'center',
                                ],
                            ],
                            [
                                'id' => 'rt_starter_contact',
                                'type' => 'RichText',
                                'props' => [
                                    'content' => '<p class="text-center text-muted-foreground">Get in touch with us. We would love to hear from you.</p>',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'pageSettings' => [
                'title' => 'Contact',
                'description' => 'Get in touch with us',
            ],
        ];

        return [
            $this->getCanonicalHomeDefinition(),
            [
                'title' => 'About',
                'slug' => 'about',
                'is_homepage' => false,
                'document' => $this->validateAndCanonicalize($aboutDoc, 'About'),
                'summary' => 'Initial starter About page created',
            ],
            [
                'title' => 'Contact',
                'slug' => 'contact',
                'is_homepage' => false,
                'document' => $this->validateAndCanonicalize($contactDoc, 'Contact'),
                'summary' => 'Initial starter Contact page created',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    protected function validateAndCanonicalize(array $document, string $label): array
    {
        $result = $this->validator->validate($document);

        if (! $result->isValid()) {
            $errorMsgs = array_map(fn ($e) => "[{$e->getPath()}] {$e->getMessage()}", $result->getErrors());
            throw new RuntimeException("Starter document '{$label}' failed Document Schema v1 validation: ".implode(', ', $errorMsgs));
        }

        $canonical = $result->getSanitizedDocument();
        if ($canonical === null) {
            throw new RuntimeException("Starter document '{$label}' could not be canonicalized.");
        }

        return $canonical;
    }
}
