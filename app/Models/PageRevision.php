<?php

namespace App\Models;

use Database\Factories\PageRevisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $page_id
 * @property int $revision_number
 * @property int $schema_version
 * @property array<string, mixed> $document_json
 * @property string $document_hash
 * @property string|null $change_summary
 * @property bool $is_autosave
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property-read Page $page
 * @property-read User|null $creator
 */
#[Fillable([
    'page_id',
    'revision_number',
    'schema_version',
    'document_json',
    'document_hash',
    'change_summary',
    'is_autosave',
    'created_by',
    'created_at',
])]
class PageRevision extends Model
{
    /** @use HasFactory<PageRevisionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'schema_version' => 'integer',
            'document_json' => 'array',
            'is_autosave' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Compute a deterministic SHA-256 hash of a canonicalized Document Schema v1 AST.
     * Recursively sorts associative array keys to ensure identical hash regardless of key order.
     *
     * @param  array<string, mixed>  $document
     */
    public static function canonicalizeDocument(array $document): string
    {
        self::ksortRecursive($document);

        return hash('sha256', (string) json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Recursively sort associative array keys.
     *
     * @param  array<array-key, mixed>  $array
     */
    private static function ksortRecursive(array &$array): void
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                self::ksortRecursive($value);
            }
        }
        unset($value);

        if (! array_is_list($array)) {
            ksort($array);
        }
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
