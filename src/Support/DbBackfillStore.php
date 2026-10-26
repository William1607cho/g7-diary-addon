<?php

namespace Plugins\G7\Diary\Addon\Support;

use Illuminate\Support\Facades\DB;
use Modules\Sirsoft\Board\Models\Attachment;
use Plugins\G7\Diary\Addon\Services\DiaryEntryWriter;

/**
 * DB implementation of {@see BackfillStore}. Reads `boards`, `board_posts` and the
 * attachments model; writes the add-on's entry and photo tables only. No raw SQL.
 */
class DbBackfillStore implements BackfillStore
{
    /** Rows per insert statement. */
    private const INSERT_CHUNK = 500;

    public function board(string $slug): ?object
    {
        return DB::table('boards')->where('slug', $slug)->first(['id', 'slug', 'type']);
    }

    public function posts(int $boardId, int $afterId, int $limit): array
    {
        return DB::table('board_posts')
            ->where('board_id', $boardId)
            // Same rule as BackfillPlan::live().
            ->whereNull('deleted_at')
            ->where('status', '!=', BackfillPlan::DELETED_STATUS)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'created_at', 'content'])
            ->map(static fn (object $r): array => ['id' => (int) $r->id, 'created_at' => (string) $r->created_at, 'content' => (string) $r->content])
            ->all();
    }

    public function withEntry(array $postIds): array
    {
        if ($postIds === []) {
            return [];
        }

        return DB::table(DiaryEntryWriter::ENTRIES)->whereIn('post_id', $postIds)->pluck('post_id')
            ->map(static fn ($id): int => (int) $id)->all();
    }

    public function imageAttachments(array $postIds): array
    {
        if ($postIds === []) {
            return [];
        }

        $out = [];
        $rows = Attachment::query()->whereIn('post_id', $postIds)
            ->orderBy('post_id')->orderBy('order')->orderBy('id')
            ->get(['id', 'post_id', 'mime_type']);
        foreach ($rows as $a) {
            if ($a->is_image) {
                $out[(int) $a->post_id][] = (int) $a->id;
            }
        }

        return $out;
    }

    public function transaction(callable $work): mixed
    {
        return DB::transaction($work);
    }

    public function insert(array $entries, array $photos): void
    {
        foreach (array_chunk($entries, self::INSERT_CHUNK) as $chunk) {
            DB::table(DiaryEntryWriter::ENTRIES)->insert($chunk);
        }
        foreach (array_chunk($photos, self::INSERT_CHUNK) as $chunk) {
            DB::table(DiaryEntryWriter::PHOTOS)->insert($chunk);
        }
    }

    public function backfilled(int $boardId, int $afterId, int $limit): array
    {
        return DB::table(DiaryEntryWriter::ENTRIES)
            ->where('board_id', $boardId)
            ->where('origin', BackfillPlan::ORIGIN)
            ->where('post_id', '>', $afterId)
            ->orderBy('post_id')
            ->limit($limit)
            ->pluck('post_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    public function removeBackfilled(int $boardId, array $postIds): array
    {
        // Check the mark again inside the transaction — an author may have saved the post meanwhile.
        $ids = DB::table(DiaryEntryWriter::ENTRIES)->where('board_id', $boardId)
            ->where('origin', BackfillPlan::ORIGIN)->whereIn('post_id', $postIds)
            ->lockForUpdate()->pluck('post_id')->map(static fn ($id): int => (int) $id)->all();
        if ($ids === []) {
            return ['entries' => 0, 'photos' => 0];
        }

        $photos = DB::table(DiaryEntryWriter::PHOTOS)->whereIn('post_id', $ids)->delete();
        $entries = DB::table(DiaryEntryWriter::ENTRIES)->whereIn('post_id', $ids)->where('origin', BackfillPlan::ORIGIN)->delete();

        return ['entries' => (int) $entries, 'photos' => (int) $photos];
    }
}
