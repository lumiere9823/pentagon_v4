<?php

namespace App\Domains\Templates\Services;

use App\Domains\DocumentEngine\DocumentLimits;
use App\Domains\DocumentEngine\Validation\DocumentValidator;
use App\Models\PageTemplate;
use InvalidArgumentException;

class TemplateInstantiationService
{
    /**
     * Regex pattern conforming strictly to Document Schema v1 node ID rules.
     */
    public const NODE_ID_PATTERN = '/^[a-zA-Z0-9_-]{3,64}$/';

    public function __construct(
        protected DocumentValidator $validator
    ) {}

    /**
     * Instantiate a template document for a new page, regenerating all node IDs.
     * Guarantees 100% document independence and uniqueness within the tree.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    public function instantiate(PageTemplate $template, string $pageTitle = 'New Page'): array
    {
        $document = $template->document_json;

        if (! isset($document['root']) || ! is_array($document['root'])) {
            throw new InvalidArgumentException("Template {$template->id} contains invalid document JSON.");
        }

        /** @var array<string, true> $seenIds */
        $seenIds = [];
        $clonedRoot = $this->cloneNodeWithFreshIds($document['root'], $seenIds);

        $instantiatedDoc = [
            'schemaVersion' => DocumentLimits::CURRENT_SCHEMA_VERSION,
            'root' => $clonedRoot,
            'pageSettings' => [
                'title' => $pageTitle,
                'description' => $template->description ?? '',
            ],
        ];

        // Authoritatively validate the instantiated tree against Document Schema v1
        $validationResult = $this->validator->validate($instantiatedDoc);
        if (! $validationResult->isValid()) {
            $errorMsgs = array_map(fn ($e) => "[{$e->getPath()}] {$e->getMessage()}", $validationResult->getErrors());
            throw new InvalidArgumentException(
                'Instantiated template failed Document Schema v1 validation: '.implode(', ', $errorMsgs)
            );
        }

        $canonicalDocument = $validationResult->getSanitizedDocument();
        if ($canonicalDocument === null) {
            throw new InvalidArgumentException('Instantiated template could not be canonicalized.');
        }

        return $canonicalDocument;
    }

    /**
     * Recursively clone a node and its children, assigning fresh unique node IDs.
     *
     * @param  array<string, mixed>  $node
     * @param  array<string, true>  $seenIds
     * @return array<string, mixed>
     */
    protected function cloneNodeWithFreshIds(array $node, array &$seenIds): array
    {
        $type = (string) ($node['type'] ?? 'Component');
        $cleanType = strtolower(preg_replace('/[^a-z0-9]/', '', $type) ?? 'comp');
        $cleanType = substr($cleanType, 0, 8);

        // Generate unique node ID adhering to NODE_ID_PATTERN (length ~26 chars <= 64 chars)
        do {
            $freshId = sprintf('node_%s_%s%s', $cleanType, bin2hex(random_bytes(4)), substr(uniqid(), -4));
        } while (isset($seenIds[$freshId]));

        $seenIds[$freshId] = true;

        if (! preg_match(self::NODE_ID_PATTERN, $freshId)) {
            throw new InvalidArgumentException("Generated node ID {$freshId} violates schema contract.");
        }

        $cloned = $node;
        $cloned['id'] = $freshId;

        if (isset($node['children']) && is_array($node['children'])) {
            $cloned['children'] = [];
            foreach ($node['children'] as $child) {
                if (is_array($child)) {
                    $cloned['children'][] = $this->cloneNodeWithFreshIds($child, $seenIds);
                }
            }
        }

        return $cloned;
    }
}
