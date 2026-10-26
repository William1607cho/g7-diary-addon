<?php

namespace Plugins\G7\Diary\Addon\Services;

use Illuminate\Support\Facades\DB;
use Plugins\G7\Diary\Addon\Support\DiaryBoards;
use Plugins\G7\Diary\Addon\Support\SiteClock;

/**
 * Read-only queries for the diary screens (one place for the add-on's SELECTs).
 *
 * Soft-deleted posts are filtered by `board_posts.deleted_at` (D23 — entry rows stay when
 * a post is soft-deleted). No table alias is used in raw expressions (canon 3장 U).
 */
class DiaryPostReader
{
    public const ENTRIES = 'g7_diary_addon_entries';

    public const PHOTOS = 'g7_diary_addon_photos';

    /** Body characters read for a preview (enough markup for 320 visible characters). */
    public const PREVIEW_SOURCE_LIMIT = 6000;

    /**
     * Posts of a list page, with the start of the body (one query).
     *
     * @param  array<int, int>  $ids
     * @return array<int, object> id => row
     */
    public function listRows(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('board_posts')
            ->whereIn('id', $ids)
            ->select([
                'id', 'user_id', 'is_secret', 'content_mode', 'created_at', 'deleted_at',
                DB::raw('SUBSTRING(content, 1, '.self::PREVIEW_SOURCE_LIMIT.') as content'),
            ])
            ->get()
            ->keyBy('id')
            ->all();
    }

    /**
     * One post without its body, or null.
     */
    public function postRow(int $id): ?object
    {
        return DB::table('board_posts')
            ->where('id', $id)
            ->first(['id', 'board_id', 'user_id', 'is_secret', 'created_at', 'deleted_at']);
    }

    /**
     * Entry rows as arrays (category_id, weather, diary_date) keyed by post id.
     *
     * @param  array<int, int>  $ids
     * @return array<int, array<string, mixed>>
     */
    public function entries(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $out = [];
        foreach (DB::table(self::ENTRIES)->whereIn('post_id', $ids)->get(['post_id', 'category_id', 'weather', 'diary_date']) as $row) {
            $out[(int) $row->post_id] = (array) $row;
        }

        return $out;
    }

    /**
     * Editor image hashes of each post in body order, from the photo index (one query).
     * Attachments are not included (D14 / bundle 2 instruction 2-7).
     *
     * @param  array<int, int>  $ids
     * @return array<int, array<int, string>> post id => hashes
     */
    public function editorPhotos(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $out = [];
        $rows = DB::table(self::PHOTOS)->whereIn('post_id', $ids)->where('source', 'editor')
            ->orderBy('post_id')->orderBy('position')->get(['post_id', 'ref']);
        foreach ($rows as $row) {
            $out[(int) $row->post_id][] = (string) $row->ref;
        }

        return $out;
    }

    /**
     * Active diary board by slug, or null.
     */
    public function diaryBoard(string $slug): ?object
    {
        return DB::table('boards')
            ->where('slug', $slug)
            ->where('type', DiaryBoards::TYPE)
            ->where('is_active', 1)
            ->first(['id', 'slug', 'is_active']);
    }

    /**
     * Every live post of a board with its entry (left join): id, user_id, is_secret,
     * created_at, category_id, diary_date. Used by the summary.
     *
     * @return array<int, object>
     */
    public function summaryRows(int $boardId): array
    {
        return DB::table('board_posts')
            ->leftJoin(self::ENTRIES, self::ENTRIES.'.post_id', '=', 'board_posts.id')
            ->where('board_posts.board_id', $boardId)
            ->whereNull('board_posts.deleted_at')
            ->get([
                'board_posts.id', 'board_posts.user_id', 'board_posts.is_secret', 'board_posts.created_at',
                self::ENTRIES.'.category_id', self::ENTRIES.'.diary_date',
            ])
            ->all();
    }

    /**
     * Diary date of a row: the entry date, else the creation time as a site-zone date (D27).
     */
    public static function dateOf(?string $diaryDate, mixed $createdAt): string
    {
        if (is_string($diaryDate) && $diaryDate !== '') {
            return substr($diaryDate, 0, 10);
        }
        try {
            $at = new \DateTimeImmutable((string) $createdAt, new \DateTimeZone('UTC'));
        } catch (\Throwable) {
            return SiteClock::today();
        }

        return SiteClock::dateOf($at);
    }
}
