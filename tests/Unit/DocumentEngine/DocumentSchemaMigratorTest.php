<?php

namespace Tests\Unit\DocumentEngine;

use App\Domains\DocumentEngine\DocumentLimits;
use App\Domains\DocumentEngine\Exceptions\DocumentMigrationException;
use App\Domains\DocumentEngine\Migration\DocumentSchemaMigrator;
use PHPUnit\Framework\TestCase;

class DocumentSchemaMigratorTest extends TestCase
{
    public function test_current_schema_is_an_idempotent_identity_migration(): void
    {
        $document = json_decode(
            (string) file_get_contents(__DIR__.'/../../Fixtures/Documents/minimal_valid.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $migrator = new DocumentSchemaMigrator;

        $first = $migrator->migrateToCurrent($document);
        $second = $migrator->migrateToCurrent($first);

        $this->assertSame(DocumentLimits::CURRENT_SCHEMA_VERSION, $first['schemaVersion']);
        $this->assertSame($document, $first);
        $this->assertSame($first, $second);
    }

    public function test_future_schema_fails_closed(): void
    {
        $migrator = new DocumentSchemaMigrator;

        $this->expectException(DocumentMigrationException::class);
        $this->expectExceptionMessage('newer than this application supports');

        $migrator->migrateToCurrent(['schemaVersion' => DocumentLimits::CURRENT_SCHEMA_VERSION + 1]);
    }

    public function test_missing_schema_version_fails_closed(): void
    {
        $migrator = new DocumentSchemaMigrator;

        $this->expectException(DocumentMigrationException::class);
        $migrator->migrateToCurrent(['root' => []]);
    }
}
