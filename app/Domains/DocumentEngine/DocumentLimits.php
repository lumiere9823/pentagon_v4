<?php

namespace App\Domains\DocumentEngine;

final class DocumentLimits
{
    /**
     * Maximum document payload size in bytes (512 KB).
     * Prevents memory exhaustion attacks on constrained servers.
     */
    public const int MAX_PAYLOAD_BYTES = 524_288;

    /**
     * Maximum depth of nested nodes in the document tree.
     * Prevents deep recursion and stack overflow.
     */
    public const int MAX_TREE_DEPTH = 10;

    /**
     * Maximum total number of nodes in a document.
     */
    public const int MAX_TOTAL_NODES = 250;

    /**
     * Maximum number of direct child nodes for a single container.
     */
    public const int MAX_CHILDREN_PER_NODE = 50;

    /**
     * Maximum string length for rich text HTML content.
     */
    public const int MAX_RICH_TEXT_LENGTH = 65_535;

    /**
     * Maximum string length for general text fields (titles, labels, alts).
     */
    public const int MAX_SHORT_STRING_LENGTH = 500;

    /**
     * Maximum string length for custom node labels.
     */
    public const int MAX_LABEL_LENGTH = 64;

    /**
     * Maximum length for URLs.
     */
    public const int MAX_URL_LENGTH = 2_048;

    /**
     * Regex pattern for stable node IDs.
     * Alphanumeric, underscores, hyphens; between 3 and 64 characters.
     */
    public const string NODE_ID_REGEX = '/^[a-zA-Z0-9_-]{3,64}$/';

    /**
     * Canonical schema emitted by persistence and publishing.
     */
    public const int CURRENT_SCHEMA_VERSION = 1;

    /**
     * Versions accepted as migration inputs. Keep an older version here only
     * while DocumentSchemaMigrator has a complete path to CURRENT_SCHEMA_VERSION.
     */
    public const array SUPPORTED_SCHEMA_VERSIONS = [self::CURRENT_SCHEMA_VERSION];
}
