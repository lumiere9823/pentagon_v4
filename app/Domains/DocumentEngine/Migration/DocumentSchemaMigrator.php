<?php

namespace App\Domains\DocumentEngine\Migration;

use App\Domains\DocumentEngine\DocumentLimits;
use App\Domains\DocumentEngine\Exceptions\DocumentMigrationException;

/**
 * Upgrades document ASTs through explicit, sequential schema steps.
 *
 * Schema v1 is currently the only released format, so the production path is
 * intentionally an identity migration. A future schema version must add one
 * private vN-to-vN+1 method before it can be declared supported.
 */
final class DocumentSchemaMigrator
{
    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    public function migrateToCurrent(array $document): array
    {
        $version = $document['schemaVersion'] ?? null;
        if (! is_int($version)) {
            throw new DocumentMigrationException('Document schemaVersion must be an integer before migration.');
        }

        if ($version > DocumentLimits::CURRENT_SCHEMA_VERSION) {
            throw new DocumentMigrationException("Document schema version {$version} is newer than this application supports.");
        }

        while ($version < DocumentLimits::CURRENT_SCHEMA_VERSION) {
            $nextVersion = $version + 1;
            $method = "migrateV{$version}ToV{$nextVersion}";

            if (! method_exists($this, $method)) {
                throw new DocumentMigrationException("No document migration path exists from schema v{$version} to v{$nextVersion}.");
            }

            /** @var array<string, mixed> $document */
            $document = $this->{$method}($document);
            if (($document['schemaVersion'] ?? null) !== $nextVersion) {
                throw new DocumentMigrationException("Document migration {$method} did not produce schema v{$nextVersion}.");
            }

            $version = $nextVersion;
        }

        return $document;
    }
}
