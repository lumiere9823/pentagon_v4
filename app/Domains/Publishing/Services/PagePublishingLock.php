<?php

namespace App\Domains\Publishing\Services;

use App\Domains\Publishing\Exceptions\PublishConcurrencyException;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Domain service enforcing page-level publishing serialization (D-032).
 *
 * Prevents concurrent public-state operations on the same page using a
 * PostgreSQL session advisory lock with namespace tag 0x505542 ('PUB').
 *
 * The session lock deliberately spans render and filesystem I/O without
 * holding an open database transaction. The lock is always released in a
 * finally block and is scoped to the selected database connection.
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
     * Run a complete page public-state operation under a non-blocking lock.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $operation
     * @return TResult
     *
     * @throws PublishConcurrencyException
     */
    public function runExclusive(int $pageId, Closure $operation, ?string $connection = null): mixed
    {
        $conn = DB::connection($connection);

        if (! $this->isDriverSupported($connection)) {
            return $operation();
        }

        $key = $this->generateLockKey($pageId);
        $result = $conn->selectOne('SELECT pg_try_advisory_lock(?) AS acquired', [$key]);

        if (! (bool) ($result->acquired ?? false)) {
            throw new PublishConcurrencyException('Another public-state operation is already in progress for this page.');
        }

        try {
            return $operation();
        } finally {
            try {
                $released = $conn->selectOne('SELECT pg_advisory_unlock(?) AS released', [$key]);

                if (! (bool) ($released->released ?? false)) {
                    Log::critical('Page publishing advisory lock was not owned during release.', [
                        'page_id' => $pageId,
                        'connection' => $connection ?? config('database.default'),
                    ]);
                }
            } catch (Throwable $releaseError) {
                Log::critical('Page publishing advisory lock release failed.', [
                    'page_id' => $pageId,
                    'connection' => $connection ?? config('database.default'),
                    'exception' => $releaseError,
                ]);
            }
        }
    }
}
