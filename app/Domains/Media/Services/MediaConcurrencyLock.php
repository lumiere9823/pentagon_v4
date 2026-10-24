<?php

namespace App\Domains\Media\Services;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

/**
 * Domain service enforcing the Media Lifecycle Concurrency Contract (D-023).
 *
 * Provides transaction-scoped mutual exclusion between media pruners and document writers
 * (pages, revisions, templates) using PostgreSQL advisory locks with deterministic ordering.
 */
class MediaConcurrencyLock
{
    /**
     * 24-bit namespace tag derived from ASCII 'MED' (0x4D4544 = 5,064,004).
     * Occupies bits 40-63 of the 64-bit PostgreSQL advisory lock key.
     */
    public const LOCK_NAMESPACE_TAG = 0x4D4544;

    /**
     * Maximum supported asset ID (40 bits: 2^40 - 1 = 1,099,511,627,775).
     */
    public const MAX_ASSET_ID = 0xFFFFFFFFFF;

    /**
     * Generate a collision-free 64-bit integer advisory lock key for a media asset ID.
     */
    public function generateLockKey(int $assetId): int
    {
        if ($assetId < 1 || $assetId > self::MAX_ASSET_ID) {
            throw new InvalidArgumentException("Media asset ID {$assetId} is out of allowable advisory lock range [1, ".self::MAX_ASSET_ID.'].');
        }

        return (self::LOCK_NAMESPACE_TAG << 40) | $assetId;
    }

    /**
     * Determine whether the given database connection supports PostgreSQL advisory locks.
     */
    public function isDriverSupported(?string $connection = null): bool
    {
        return DB::connection($connection)->getDriverName() === 'pgsql';
    }

    /**
     * Acquire a transaction-scoped advisory lock for a single media asset.
     */
    public function lockAsset(int $assetId, ?string $connection = null): void
    {
        $this->lockAssets([$assetId], $connection);
    }

    /**
     * Acquire transaction-scoped advisory locks for multiple media assets in deterministic ascending order.
     *
     * @param  list<int>|array<int, int>  $assetIds
     *
     * @throws RuntimeException If not within an active database transaction or driver is unsupported.
     */
    public function lockAssets(array $assetIds, ?string $connection = null): void
    {
        $conn = DB::connection($connection);

        if ($conn->transactionLevel() <= 0) {
            throw new RuntimeException('MediaConcurrencyLock requires an active database transaction. Transaction-scoped locks cannot be held outside a transaction.');
        }

        $driver = $conn->getDriverName();
        if ($driver !== 'pgsql') {
            if (app()->environment('testing')) {
                // SQLite in unit testing does not provide PostgreSQL advisory locks.
                // Log warning and allow non-concurrency functional tests to complete.
                Log::warning("MediaConcurrencyLock: Database driver '{$driver}' does not support PostgreSQL advisory locks. Advisory locking bypassed for testing. DO NOT assume concurrency safety.");

                return;
            }

            throw new RuntimeException("Media concurrency locking requires PostgreSQL ('pgsql'), driver '{$driver}' is not supported.");
        }

        // Normalize and extract unique positive integer IDs
        $uniqueIds = array_values(array_unique(array_filter(
            array_map(fn ($id) => (int) $id, $assetIds),
            fn ($id) => $id > 0
        )));

        if (empty($uniqueIds)) {
            return;
        }

        // Deterministic sort ordering: strictly ascending numerical order (Dijkstra's resource hierarchy)
        // Mathematically eliminates circular wait deadlocks across concurrent multi-asset saves.
        sort($uniqueIds, SORT_NUMERIC);

        foreach ($uniqueIds as $id) {
            $lockKey = $this->generateLockKey($id);
            $conn->select('SELECT pg_advisory_xact_lock(?::bigint)', [$lockKey]);
        }
    }

    /**
     * Attempt to acquire a transaction-scoped advisory lock non-blockingly.
     *
     * @return bool True if the lock was acquired; false if another transaction currently holds it.
     */
    public function tryLockAsset(int $assetId, ?string $connection = null): bool
    {
        $conn = DB::connection($connection);

        if ($conn->transactionLevel() <= 0) {
            throw new RuntimeException('MediaConcurrencyLock requires an active database transaction.');
        }

        $driver = $conn->getDriverName();
        if ($driver !== 'pgsql') {
            if (app()->environment('testing')) {
                return true;
            }

            throw new RuntimeException("Media concurrency locking requires PostgreSQL ('pgsql'), driver '{$driver}' is not supported.");
        }

        $lockKey = $this->generateLockKey($assetId);
        $result = $conn->select('SELECT pg_try_advisory_xact_lock(?::bigint) as locked', [$lockKey]);

        return ! empty($result) && (bool) $result[0]->locked;
    }

    /**
     * Execute a callback wrapped in database transaction and protected by advisory locks.
     *
     * @template T
     *
     * @param  list<int>|array<int, int>  $assetIds
     * @param  Closure(): T  $callback
     * @return T
     */
    public function runWithLocks(array $assetIds, Closure $callback, ?string $connection = null): mixed
    {
        $conn = DB::connection($connection);

        if ($conn->transactionLevel() > 0) {
            $this->lockAssets($assetIds, $connection);

            return $callback();
        }

        return $conn->transaction(function () use ($assetIds, $callback, $connection) {
            $this->lockAssets($assetIds, $connection);

            return $callback();
        });
    }
}
