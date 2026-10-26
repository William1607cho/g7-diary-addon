<?php

namespace Plugins\G7\Diary\Addon\Support;

/**
 * Storage the backfill command reads and writes through (DB in the app, a fake in unit tests).
 *
 * Only the add-on tables are written; posts and attachments are read.
 */
interface BackfillStore
{
    /**
     * Board by slug (any type), or null: {id, slug, type}.
     */
    public function board(string $slug): ?object;

    /**
     * Live posts of the board with an id above $afterId, in id order: not soft-deleted and
     * not deleted by status (secret and blinded posts included).
     *
     * @return array<int, array{id: int, created_at: string, content: string}>
     */
    public function posts(int $boardId, int $afterId, int $limit): array;

    /**
     * Post ids among $postIds that already have an entry row.
     *
     * @param  array<int, int>  $postIds
     * @return array<int, int>
     */
    public function withEntry(array $postIds): array;

    /**
     * Image attachment ids per post, in display order (the save hook's rule, D31).
     *
     * @param  array<int, int>  $postIds
     * @return array<int, array<int, int>>
     */
    public function imageAttachments(array $postIds): array;

    /**
     * Runs the callback in one transaction; any exception rolls it back and is re-thrown.
     *
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    public function transaction(callable $work): mixed;

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @param  array<int, array<string, mixed>>  $photos
     */
    public function insert(array $entries, array $photos): void;

    /**
     * Post ids of the board whose entry row was made by the backfill, above $afterId, in id order.
     *
     * @return array<int, int>
     */
    public function backfilled(int $boardId, int $afterId, int $limit): array;

    /**
     * Removes the backfill entry rows (still marked backfill) of these posts and their photo rows.
     *
     * @param  array<int, int>  $postIds
     * @return array{entries: int, photos: int}
     */
    public function removeBackfilled(int $boardId, array $postIds): array;
}
