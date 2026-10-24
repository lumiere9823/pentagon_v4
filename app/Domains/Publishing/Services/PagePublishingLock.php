<?php

namespace App\Domains\Publishing\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Domain service enforcing page-level publishing serialization (D-032).
 *
 * Prevents concurrent publishing operations on the same page using PostgreSQL
 * transaction-scoped advisory locks with namespace tag 0x505542 ('PUB').
 */
class PagePublishingLock
{
    /**
     * 24-bit namespace tag derived from ASCII 'PUB' (0x505542 = 5,264,706).
     * Occupies bits 40-63 of the 64-bit PostgreSQL advisory lock key.
     */
    public const LOCK_NAMESPACE_TAG = 0x505542;

    /**
     * Maximum supported page ID (40 bits: 2^40 - 1 = 1,099,511,627,775).
     */
    public const MAX_PAGE_ID = 0xFFFFFFFFFF;

    /**
     * Generate a collision-free 64-bit integer advisory lock key for a page ID.
     */
    public function generateLockKey(int $pageId): int
    {
        if ($pageId < 1 || $pageId > self::MAX_PAGE_ID) {
            throw new InvalidArgumentException("Page ID {$pageId} is out of allowable advisory lock range [1, ".self::MAX_PAGE_ID.'].');
        }

        return (self::LOCK_NAMESPACE_TAG << 40) | $pageId;
    }

    /**
     * Determine whether the given database connection supports PostgreSQL advisory locks.
     */
    public function isDriverSupported(?string $connection = null): bool
    {
        return DB::connection($connection)->getDriverName() === 'pgsql';
    }

    /**
     * Attempt to acquire a non-blocking transaction-scoped advisory lock for publishing a page.
     *
     * Returns true if lock was acquired (or driver is non-pgsql), false if locked by another transaction.
     *
     * @throws RuntimeException If not within an active database transaction on pgsql.
     */
    public function tryLockPage(int $pageId, ?string $connection = null): bool
    {
        $conn = DB::connection($connection);

        if (! $this->isDriverSupported($connection)) {
            return true;
        }

        if ($conn->transactionLevel() <= 0) {
            throw new RuntimeException('PagePublishingLock requires an active database transaction. Transaction-scoped locks cannot be held outside a transaction.');
        }

        $key = $this->generateLockKey($pageId);
        $result = $conn->selectOne('SELECT pg_try_advisory_xact_lock(?) AS acquired', [$key]);

        return (bool) ($result->acquired ?? false);
    }
}
