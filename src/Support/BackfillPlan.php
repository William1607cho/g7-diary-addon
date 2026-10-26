<?php

namespace Plugins\G7\Diary\Addon\Support;

/**
 * Rows the backfill writes for posts without diary metadata (pure, for unit tests).
 *
 * Diary date = the creation time (stored in UTC, the app time zone) as a date in the site
 * time zone — the same day the screens already show for such posts (D27). Category and
 * weather stay empty (default category, no weather), the title is the author's
 * (`title_auto` 0). Photos follow the save hook: editor images in body order, then image
 * attachments (D31).
 */
class BackfillPlan
{
    public const ORIGIN = 'backfill';

    /** Post status that counts as deleted, besides the soft delete. */
    public const DELETED_STATUS = 'deleted';

    /**
     * Whether a post is a backfill target: not soft-deleted and not deleted by status
     * (secret and blinded posts are targets — the screens hide them by D6).
     */
    public static function live(?string $deletedAt, string $status): bool
    {
        return ($deletedAt === null || $deletedAt === '') && $status !== self::DELETED_STATUS;
    }

    /**
     * Site-zone date of a stored creation time.
     */
    public static function diaryDate(string $createdAt, string $timezone): string
    {
        try {
            $at = new \DateTimeImmutable($createdAt, new \DateTimeZone('UTC'));
        } catch (\Throwable) {
            throw new \InvalidArgumentException('created_at');
        }

        return SiteClock::todayIn($timezone, $at);
    }

    /**
     * Entry and photo rows for one batch.
     *
     * @param  array<int, array{id: int, created_at: string, content: string}>  $posts  Posts without an entry.
     * @param  array<int, array<int, int>>  $attachments  Image attachment ids per post.
     * @return array{entries: array<int, array<string, mixed>>, photos: array<int, array<string, mixed>>}
     */
    public static function rows(array $posts, array $attachments, int $boardId, string $timezone, string $now): array
    {
        $entries = $photos = [];
        foreach ($posts as $post) {
            $id = (int) $post['id'];
            $date = self::diaryDate((string) $post['created_at'], $timezone);
            $entries[] = [
                'post_id' => $id,
                'board_id' => $boardId,
                'category_id' => null,
                'weather' => null,
                'diary_date' => $date,
                'title_auto' => 0,
                'origin' => self::ORIGIN,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $found = PhotoExtractor::rows(PhotoExtractor::editorHashes((string) $post['content']), $attachments[$id] ?? []);
            foreach ($found as $row) {
                $photos[] = $row + ['post_id' => $id, 'board_id' => $boardId, 'diary_date' => $date];
            }
        }

        return ['entries' => $entries, 'photos' => $photos];
    }

    /**
     * Adds a batch to the dry-run tally (numbers only — no title or body).
     *
     * @param  array<string, mixed>  $tally
     * @param  array{entries: array<int, array<string, mixed>>, photos: array<int, array<string, mixed>>}  $rows
     * @return array<string, mixed>
     */
    public static function tally(array $tally, array $rows, int $skipped): array
    {
        $tally += ['targets' => 0, 'skipped' => 0, 'from' => null, 'to' => null, 'years' => [], 'editor' => 0, 'attachment' => 0];
        $tally['skipped'] += $skipped;
        foreach ($rows['entries'] as $entry) {
            $date = (string) $entry['diary_date'];
            $tally['targets']++;
            $tally['from'] = $tally['from'] === null || $date < $tally['from'] ? $date : $tally['from'];
            $tally['to'] = $tally['to'] === null || $date > $tally['to'] ? $date : $tally['to'];
            $year = substr($date, 0, 4);
            $tally['years'][$year] = ($tally['years'][$year] ?? 0) + 1;
        }
        foreach ($rows['photos'] as $photo) {
            $tally[$photo['source'] === PhotoExtractor::SOURCE_EDITOR ? 'editor' : 'attachment']++;
        }
        ksort($tally['years']);

        return $tally;
    }

    /**
     * Report lines of a tally (numbers, dates and the slug only).
     *
     * @param  array<string, mixed>  $tally
     * @return array<int, string>
     */
    public static function lines(array $tally): array
    {
        $tally += ['targets' => 0, 'skipped' => 0, 'from' => null, 'to' => null, 'years' => [], 'editor' => 0, 'attachment' => 0];
        $years = [];
        foreach ($tally['years'] as $year => $n) {
            $years[] = $year.':'.$n;
        }

        return [
            '대상 글 '.$tally['targets'].'편 · 건너뜀(이미 메타 있음) '.$tally['skipped'].'편',
            '일기 날짜 '.($tally['from'] ?? '-').' ~ '.($tally['to'] ?? '-'),
            '연도별 '.($years === [] ? '-' : implode(' ', $years)),
            '사진 색인 '.($tally['editor'] + $tally['attachment']).'장(에디터 '.$tally['editor'].' · 첨부 '.$tally['attachment'].')',
        ];
    }
}
