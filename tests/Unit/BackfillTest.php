<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Plugins\G7\Diary\Addon\Services\DiaryBackfill;
use Plugins\G7\Diary\Addon\Support\BackfillPlan;
use Plugins\G7\Diary\Addon\Support\BackfillStore;

/**
 * Bundle 5: `g7-diary-addon:backfill` — date conversion, target selection, undo, repeat
 * runs, batch rollback (fake store) and the dry-run report.
 */
class BackfillTest extends TestCase
{
    public function test_creation_time_becomes_the_site_zone_date(): void
    {
        // KST midnight boundary (UTC+9)
        $this->assertSame('2026-10-26', BackfillPlan::diaryDate('2026-10-26 14:59:59', 'Asia/Seoul'));
        $this->assertSame('2026-10-27', BackfillPlan::diaryDate('2026-10-26 15:00:00', 'Asia/Seoul'));
        // Year end
        $this->assertSame('2026-01-01', BackfillPlan::diaryDate('2025-12-31 15:00:00', 'Asia/Seoul'));
        $this->assertSame('2025-12-31', BackfillPlan::diaryDate('2025-12-31 14:59:59', 'Asia/Seoul'));
        // UTC site, a zone behind UTC, an invalid zone (UTC)
        $this->assertSame('2025-12-31', BackfillPlan::diaryDate('2025-12-31 23:59:59', 'UTC'));
        $this->assertSame('2025-12-31', BackfillPlan::diaryDate('2026-01-01 04:00:00', 'America/New_York'));
        $this->assertSame('2026-01-01', BackfillPlan::diaryDate('2026-01-01 00:00:00', 'Nowhere/City'));
        // Leap day
        $this->assertSame('2028-02-29', BackfillPlan::diaryDate('2028-02-28 15:00:00', 'Asia/Seoul'));
    }

    public function test_live_rule_keeps_secret_and_blinded_and_drops_deleted(): void
    {
        $this->assertTrue(BackfillPlan::live(null, 'published'));
        $this->assertTrue(BackfillPlan::live(null, 'blinded'));
        $this->assertFalse(BackfillPlan::live(null, 'deleted'));
        $this->assertFalse(BackfillPlan::live('2026-10-01 00:00:00', 'published'));
    }

    public function test_run_fills_posts_without_meta_and_skips_the_rest(): void
    {
        $store = FakeBackfillStore::sample();
        $r = (new DiaryBackfill($store))->run('j', false, 'Asia/Seoul', 2, '2026-10-27 00:00:00');
        $this->assertTrue($r['ok']);
        // Live 1,2,3,5,6 (4 soft-deleted, 7 deleted by status); 2 already has an entry.
        $this->assertSame([1, 2, 3, 5, 6], array_keys($store->entries));
        $this->assertSame('normal', $store->entries[2]['origin']);
        $this->assertSame(4, $r['tally']['targets']);
        $this->assertSame(1, $r['tally']['skipped']);
        $this->assertSame(3, $r['batches']);
        $e = $store->entries[1];
        $this->assertSame(['backfill', null, null, 0, '2026-10-02'], [$e['origin'], $e['category_id'], $e['weather'], $e['title_auto'], $e['diary_date']]);
        $this->assertSame('2025-01-01', $store->entries[6]['diary_date']);
        // Photos: post 3 = 2 editor images then 1 image attachment; post 5 = attachment only.
        $p3 = array_values(array_filter($store->photos, static fn (array $p): bool => $p['post_id'] === 3));
        $this->assertSame([['editor', 'aaaaaaaaaaaa', 0], ['editor', 'bbbbbbbbbbbb', 1], ['attachment', '31', 2]],
            array_map(static fn (array $p): array => [$p['source'], $p['ref'], $p['position']], $p3));
        $this->assertSame(['editor' => 2, 'attachment' => 2], ['editor' => $r['tally']['editor'], 'attachment' => $r['tally']['attachment']]);
        $this->assertSame('2026-10-03', $p3[0]['diary_date']);
    }

    public function test_second_run_adds_nothing_and_changes_nothing(): void
    {
        $store = FakeBackfillStore::sample();
        $svc = new DiaryBackfill($store);
        $svc->run('j', false, 'Asia/Seoul', 200, '2026-10-27 00:00:00');
        $before = [$store->entries, $store->photos];
        $r = $svc->run('j', false, 'Asia/Seoul', 200, '2026-10-28 00:00:00');
        $this->assertTrue($r['ok']);
        $this->assertSame(0, $r['tally']['targets']);
        $this->assertSame(5, $r['tally']['skipped']);
        $this->assertSame($before, [$store->entries, $store->photos]);
    }

    public function test_undo_removes_only_backfill_rows(): void
    {
        $store = FakeBackfillStore::sample();
        $svc = new DiaryBackfill($store);
        $svc->run('j', false, 'Asia/Seoul', 200, '2026-10-27 00:00:00');
        // The author edits post 3 → the save hook marks it normal.
        $store->entries[3]['origin'] = 'normal';
        $store->entries[3]['category_id'] = 5;
        $r = $svc->undo('j', 2);
        $this->assertTrue($r['ok']);
        $this->assertSame([2, 3], array_keys($store->entries));
        $this->assertSame(3, $r['entries']);   // 1, 5 (first batch) and 6 (second batch)
        $this->assertSame(2, $r['batches']);
        $this->assertSame(1, $r['photos']);    // post 5's attachment; post 3's photos stay
        $this->assertSame(3, count(array_filter($store->photos, static fn (array $p): bool => $p['post_id'] === 3)));
        // Run again → back to the state before undo, except post 3.
        $svc->run('j', false, 'Asia/Seoul', 200, '2026-10-27 00:00:00');
        $this->assertSame([1, 2, 3, 5, 6], array_keys($store->entries));
        $this->assertSame('normal', $store->entries[3]['origin']);
    }

    public function test_a_failed_batch_rolls_back_only_itself(): void
    {
        $store = FakeBackfillStore::sample();
        $store->failOnInsertCall = 2;
        $r = (new DiaryBackfill($store))->run('j', false, 'Asia/Seoul', 2, '2026-10-27 00:00:00');
        $this->assertFalse($r['ok']);
        $this->assertSame('batch_failed', $r['error']);
        // Batch 1 = posts 1, 2 (written); batch 2 = posts 3, 5 (rolled back); batch 3 not reached.
        $this->assertSame(['from' => 3, 'to' => 5, 'exception' => \RuntimeException::class], $r['failed']);
        $this->assertSame([1, 2], array_keys($store->entries));
        $this->assertSame([], array_filter($store->photos, static fn (array $p): bool => in_array($p['post_id'], [3, 5], true)));
        $store->failOnInsertCall = 0;
        $this->assertTrue((new DiaryBackfill($store))->run('j', false, 'Asia/Seoul', 2)['ok']);
        $this->assertSame([1, 2, 3, 5, 6], array_keys($store->entries));
    }

    public function test_dry_run_writes_nothing_and_reports_numbers_only(): void
    {
        $store = FakeBackfillStore::sample('basic');
        $svc = new DiaryBackfill($store);
        $this->assertSame('not_diary', $svc->run('j', false, 'Asia/Seoul')['error']);
        $this->assertSame(0, $store->transactions);
        $r = $svc->run('j', true, 'Asia/Seoul');
        $this->assertTrue($r['ok']);
        $this->assertSame(0, $store->transactions);
        $this->assertSame([2], array_keys($store->entries));
        $lines = BackfillPlan::lines($r['tally']);
        $this->assertSame([
            '대상 글 4편 · 건너뜀(이미 메타 있음) 1편',
            '일기 날짜 2025-01-01 ~ 2026-10-04',
            '연도별 2025:1 2026:3',
            '사진 색인 4장(에디터 2 · 첨부 2)',
        ], $lines);
        foreach ($lines as $line) {
            $this->assertStringNotContainsString('SECRET-BODY', $line);
        }
        // Undo works on any board type (after the board was switched back).
        $this->assertTrue($svc->undo('j')['ok']);
        $this->assertSame('board_unknown', $svc->run('nope', true, 'UTC')['error']);
    }

    public function test_empty_board_reports_dashes(): void
    {
        $this->assertSame(['대상 글 0편 · 건너뜀(이미 메타 있음) 0편', '일기 날짜 - ~ -', '연도별 -', '사진 색인 0장(에디터 0 · 첨부 0)'], BackfillPlan::lines([]));
    }
}

class FakeBackfillStore implements BackfillStore
{
    /** @var array<int, array<string, mixed>> */
    public array $posts = [];

    /** @var array<int, array<string, mixed>> */
    public array $entries = [];

    /** @var array<int, array<string, mixed>> */
    public array $photos = [];

    /** @var array<int, array<int, int>> */
    public array $attachments = [];

    public string $type = 'diary';

    public int $transactions = 0;

    public int $failOnInsertCall = 0;

    private int $insertCalls = 0;

    public static function sample(string $type = 'diary'): self
    {
        $s = new self();
        $s->type = $type;
        $mk = static fn (int $id, string $at, string $content = '', ?string $del = null, string $status = 'published'): array =>
            ['id' => $id, 'created_at' => $at, 'content' => $content, 'deleted_at' => $del, 'status' => $status];
        $img = static fn (string $h): string => '<p>SECRET-BODY</p><img src="/api/plugins/sirsoft-ckeditor5/images/'.$h.'">';
        $s->posts = [
            $mk(1, '2026-10-01 15:30:00', '<p>SECRET-BODY title text</p>'),
            $mk(2, '2026-10-02 01:00:00'),
            $mk(3, '2026-10-03 03:00:00', $img('aaaaaaaaaaaa').$img('bbbbbbbbbbbb').$img('aaaaaaaaaaaa'), null, 'blinded'),
            $mk(4, '2026-10-03 04:00:00', $img('cccccccccccc'), '2026-10-05 00:00:00'),
            $mk(5, '2026-10-04 05:00:00'),
            $mk(6, '2024-12-31 16:00:00'),
            $mk(7, '2026-10-06 05:00:00', '', null, 'deleted'),
        ];
        $s->entries[2] = ['post_id' => 2, 'board_id' => 9, 'category_id' => 1, 'weather' => 'sunny', 'diary_date' => '2026-09-30', 'title_auto' => 0, 'origin' => 'normal'];
        $s->attachments = [3 => [31], 5 => [51]];

        return $s;
    }

    public function board(string $slug): ?object
    {
        return $slug === 'j' ? (object) ['id' => 9, 'slug' => 'j', 'type' => $this->type] : null;
    }

    public function posts(int $boardId, int $afterId, int $limit): array
    {
        $out = [];
        foreach ($this->posts as $p) {
            if ($p['id'] > $afterId && BackfillPlan::live($p['deleted_at'], $p['status'])) {
                $out[] = ['id' => $p['id'], 'created_at' => $p['created_at'], 'content' => $p['content']];
            }
        }

        return array_slice($out, 0, $limit);
    }

    public function withEntry(array $postIds): array
    {
        return array_values(array_filter($postIds, fn (int $id): bool => isset($this->entries[$id])));
    }

    public function imageAttachments(array $postIds): array
    {
        return array_intersect_key($this->attachments, array_flip($postIds));
    }

    public function transaction(callable $work): mixed
    {
        $this->transactions++;
        $snapshot = [$this->entries, $this->photos];
        try {
            return $work();
        } catch (\Throwable $e) {
            [$this->entries, $this->photos] = $snapshot;
            throw $e;
        }
    }

    public function insert(array $entries, array $photos): void
    {
        $this->insertCalls++;
        foreach ($entries as $e) {
            $this->entries[$e['post_id']] = $e;
        }
        if ($this->insertCalls === $this->failOnInsertCall) {
            throw new \RuntimeException('insert failed');
        }
        foreach ($photos as $p) {
            $this->photos[] = $p;
        }
        ksort($this->entries);
    }

    public function backfilled(int $boardId, int $afterId, int $limit): array
    {
        $ids = [];
        foreach ($this->entries as $id => $e) {
            if ($id > $afterId && $e['origin'] === BackfillPlan::ORIGIN) {
                $ids[] = $id;
            }
        }

        return array_slice($ids, 0, $limit);
    }

    public function removeBackfilled(int $boardId, array $postIds): array
    {
        $ids = array_values(array_filter($postIds, fn (int $id): bool => ($this->entries[$id]['origin'] ?? null) === BackfillPlan::ORIGIN));
        $before = count($this->photos);
        $this->photos = array_values(array_filter($this->photos, static fn (array $p): bool => ! in_array($p['post_id'], $ids, true)));
        foreach ($ids as $id) {
            unset($this->entries[$id]);
        }

        return ['entries' => count($ids), 'photos' => $before - count($this->photos)];
    }
}
