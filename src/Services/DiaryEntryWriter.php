<?php

namespace Plugins\G7\Diary\Addon\Services;

use Illuminate\Support\Facades\DB;
use Modules\Sirsoft\Board\Models\Post;
use Plugins\G7\Diary\Addon\Support\BackfillStore;
use Plugins\G7\Diary\Addon\Support\PhotoExtractor;
use Plugins\G7\Diary\Addon\Support\SiteClock;

/**
 * Writes the diary meta row and the photo index of one post.
 *
 * Called from the after-create / after-update hooks (outside the post transaction),
 * so the post and its attachments are already committed. Every save marks the row
 * `origin = normal`, so a row made by the backfill command becomes the author's own once
 * the post is edited (and `--undo` leaves it alone).
 */
class DiaryEntryWriter
{
    /** Table names before the DB prefix (physical: g7_g7_…). */
    public const ENTRIES = 'g7_diary_addon_entries';

    public const PHOTOS = 'g7_diary_addon_photos';

    /** `origin` of rows written by the save hook (the backfill writes BackfillPlan::ORIGIN). */
    public const ORIGIN_NORMAL = 'normal';

    public function __construct(private readonly BackfillStore $sources) {}

    /**
     * Inserts or updates the entry row, then rebuilds the photo index.
     *
     * @param  array<string, mixed>  $fields  Diary fields that were sent (category_id, weather, diary_date).
     * @param  int|null  $titleAuto  New title_auto value; null keeps the stored one (0 for a new row).
     */
    public function save(Post $post, array $fields, ?int $titleAuto): void
    {
        DB::transaction(function () use ($post, $fields, $titleAuto): void {
            $postId = (int) $post->id;
            $existing = DB::table(self::ENTRIES)->where('post_id', $postId)->first();
            $now = now();

            if ($existing === null) {
                $diaryDate = $fields['diary_date'] ?? $this->defaultDate($post);
                DB::table(self::ENTRIES)->insert([
                    'post_id' => $postId,
                    'board_id' => (int) $post->board_id,
                    'category_id' => $fields['category_id'] ?? null,
                    'weather' => $fields['weather'] ?? null,
                    'diary_date' => $diaryDate,
                    'title_auto' => $titleAuto ?? 0,
                    'origin' => self::ORIGIN_NORMAL,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $diaryDate = $fields['diary_date'] ?? (string) $existing->diary_date;
                $update = ['board_id' => (int) $post->board_id, 'diary_date' => $diaryDate, 'origin' => self::ORIGIN_NORMAL, 'updated_at' => $now];
                if (array_key_exists('category_id', $fields)) {
                    $update['category_id'] = $fields['category_id'];
                }
                if (array_key_exists('weather', $fields)) {
                    $update['weather'] = $fields['weather'];
                }
                if ($titleAuto !== null) {
                    $update['title_auto'] = $titleAuto;
                }
                DB::table(self::ENTRIES)->where('post_id', $postId)->update($update);
            }

            $this->reindexPhotos($post, $diaryDate);
        });
    }

    /**
     * Removes the rows of permanently deleted posts.
     *
     * @param  array<int, int>  $postIds
     */
    public function purge(array $postIds): void
    {
        $postIds = array_values(array_filter(array_map('intval', $postIds), static fn (int $id): bool => $id > 0));
        if ($postIds === []) {
            return;
        }

        DB::table(self::PHOTOS)->whereIn('post_id', $postIds)->delete();
        DB::table(self::ENTRIES)->whereIn('post_id', $postIds)->delete();
    }

    /**
     * Replaces the photo rows of the post (editor images, then image attachments).
     */
    private function reindexPhotos(Post $post, string $diaryDate): void
    {
        $postId = (int) $post->id;
        $hashes = PhotoExtractor::editorHashes((string) $post->content);

        // Image attachments in display order — one rule for the save hook and the backfill (D31).
        $attachmentIds = $this->sources->imageAttachments([$postId])[$postId] ?? [];

        DB::table(self::PHOTOS)->where('post_id', $postId)->delete();

        $rows = [];
        foreach (PhotoExtractor::rows($hashes, $attachmentIds) as $row) {
            $rows[] = $row + [
                'post_id' => $postId,
                'board_id' => (int) $post->board_id,
                'diary_date' => $diaryDate,
            ];
        }

        if ($rows !== []) {
            DB::table(self::PHOTOS)->insert($rows);
        }
    }

    /**
     * Date for a new row: the post's creation day in the site time zone (today for a new post).
     */
    private function defaultDate(Post $post): string
    {
        $created = $post->created_at;

        return $created instanceof \DateTimeInterface ? SiteClock::dateOf($created) : SiteClock::today();
    }
}
