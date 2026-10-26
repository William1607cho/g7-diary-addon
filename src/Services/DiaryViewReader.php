<?php

namespace Plugins\G7\Diary\Addon\Services;

use Illuminate\Support\Facades\DB;
use Plugins\G7\Diary\Addon\Support\DiaryFilterSql;

/**
 * Read-only queries of the calendar, photo and "지난 오늘" screens (bundle 3).
 *
 * Every query keeps live posts only: not soft-deleted and not blinded / deleted by status
 * (bundle 3 spec — blinded and deleted posts are left out of these screens). No table
 * alias in raw expressions — there is no raw SQL here (canon 3장 U).
 */
class DiaryViewReader
{
    /** Post statuses left out of the bundle 3 screens. */
    public const HIDDEN_STATUSES = ['blinded', 'deleted'];

    private const POST_COLUMNS = ['board_posts.id', 'board_posts.user_id', 'board_posts.is_secret', 'board_posts.created_at'];

    /**
     * Live posts with their diary date and category; with a range, only posts whose diary
     * date (or, without an entry, creation time in the given UTC range) falls in it.
     *
     * @param  array{0: string, 1: string, 2: string, 3: string}|null  $range  [from date, to date, from UTC, to UTC (exclusive)]
     * @return array<int, object>
     */
    public function posts(int $boardId, ?array $range = null): array
    {
        $entries = DiaryPostReader::ENTRIES;
        $query = $this->live($boardId)
            ->leftJoin($entries, $entries.'.post_id', '=', 'board_posts.id')
            ->select(array_merge(self::POST_COLUMNS, [$entries.'.category_id', $entries.'.diary_date']));
        if ($range !== null) {
            [$from, $to, $utcFrom, $utcTo] = $range;
            $query->where(static function ($q) use ($entries, $from, $to, $utcFrom, $utcTo): void {
                $q->whereBetween($entries.'.diary_date', [$from, $to])
                    ->orWhere(static function ($w) use ($entries, $utcFrom, $utcTo): void {
                        $w->whereNull($entries.'.post_id')
                            ->where('board_posts.created_at', '>=', $utcFrom)->where('board_posts.created_at', '<', $utcTo);
                    });
            });
        }

        return $query->get()->all();
    }

    /**
     * Titles of posts (only ever called for posts the viewer may open).
     *
     * @param  array<int, int>  $ids
     * @return array<int, string>
     */
    public function titles(array $ids): array
    {
        return $ids === [] ? [] : DB::table('board_posts')->whereIn('id', $ids)->pluck('title', 'id')->map(static fn ($t): string => (string) $t)->all();
    }

    /**
     * Photo index rows of live posts, newest diary date first, then post, then position.
     * An optional filter plan (category) narrows the posts.
     *
     * @param  array<int, array<int, mixed>>  $plan
     * @return array<int, object>
     */
    public function photos(int $boardId, array $plan = []): array
    {
        $photos = DiaryPostReader::PHOTOS;
        $query = $this->live($boardId)
            ->join($photos, $photos.'.post_id', '=', 'board_posts.id')
            ->select(array_merge(self::POST_COLUMNS, [$photos.'.source', $photos.'.ref', $photos.'.position', $photos.'.diary_date']))
            ->orderByDesc($photos.'.diary_date')->orderByDesc('board_posts.id')->orderBy($photos.'.position');
        DiaryFilterSql::apply($query, $plan, [
            'id' => 'board_posts.id', 'is_secret' => 'board_posts.is_secret',
            'user_id' => 'board_posts.user_id', 'created_at' => 'board_posts.created_at',
        ]);

        return $query->get()->all();
    }

    /**
     * First photo of each post (position order), editor or attachment.
     *
     * @param  array<int, int>  $ids
     * @return array<int, array{source: string, ref: string}>
     */
    public function firstPhotos(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $out = [];
        $rows = DB::table(DiaryPostReader::PHOTOS)->whereIn('post_id', $ids)->orderBy('post_id')->orderBy('position')->get(['post_id', 'source', 'ref']);
        foreach ($rows as $row) {
            $out[(int) $row->post_id] ??= ['source' => (string) $row->source, 'ref' => (string) $row->ref];
        }

        return $out;
    }

    /**
     * Image attachments by id that still exist: id => hash.
     *
     * @param  array<int, int>  $ids
     * @return array<int, string>
     */
    public function attachmentHashes(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('board_attachments')->whereIn('id', $ids)->whereNull('deleted_at')
            ->where('mime_type', 'like', 'image/%')->where('mime_type', '!=', 'image/svg+xml')
            ->pluck('hash', 'id')->map(static fn ($h): string => (string) $h)->all();
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    private function live(int $boardId): mixed
    {
        return DB::table('board_posts')
            ->where('board_posts.board_id', $boardId)
            ->whereNull('board_posts.deleted_at')
            ->whereNotIn('board_posts.status', self::HIDDEN_STATUSES);
    }
}
