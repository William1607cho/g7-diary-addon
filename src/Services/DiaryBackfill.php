<?php

namespace Plugins\G7\Diary\Addon\Services;

use Plugins\G7\Diary\Addon\Support\BackfillPlan;
use Plugins\G7\Diary\Addon\Support\BackfillStore;
use Plugins\G7\Diary\Addon\Support\DiaryBoards;

/**
 * Fills diary metadata and the photo index for posts written before a board became a diary
 * (`g7-diary-addon:backfill`), and removes what it made (`--undo`).
 *
 * Posts are read in id order in batches; each batch is one transaction (entries + photos),
 * so a failure rolls back that batch only and the run stops there — earlier batches stay,
 * and running again continues (posts that already have an entry are skipped). Only the
 * add-on tables are written: no post column, no save path, no hooks.
 */
class DiaryBackfill
{
    public const DEFAULT_BATCH = 200;

    public function __construct(private readonly BackfillStore $store) {}

    /**
     * @return array{ok: bool, error?: string, board?: object, tally?: array<string, mixed>, batches?: int, failed?: array<string, mixed>}
     */
    public function run(string $slug, bool $dryRun, string $timezone, int $batch = self::DEFAULT_BATCH, ?string $now = null): array
    {
        $board = $this->store->board($slug);
        if ($board === null) {
            return ['ok' => false, 'error' => 'board_unknown'];
        }
        if (! $dryRun && (string) $board->type !== DiaryBoards::TYPE) {
            return ['ok' => false, 'error' => 'not_diary', 'board' => $board];
        }

        $batch = max(1, $batch);
        $now ??= gmdate('Y-m-d H:i:s');
        $tally = [];
        $done = 0;
        $after = 0;
        while (($posts = $this->store->posts((int) $board->id, $after, $batch)) !== []) {
            $ids = array_map(static fn (array $p): int => (int) $p['id'], $posts);
            $last = max($ids);
            try {
                $rows = $dryRun
                    ? $this->plan((int) $board->id, $posts, $timezone, $now)
                    : $this->store->transaction(fn (): array => $this->write((int) $board->id, $posts, $timezone, $now));
            } catch (\Throwable $e) {
                return ['ok' => false, 'error' => 'batch_failed', 'board' => $board, 'tally' => $tally, 'batches' => $done,
                    'failed' => ['from' => min($ids), 'to' => $last, 'exception' => $e::class]];
            }
            $tally = BackfillPlan::tally($tally, $rows['rows'], $rows['skipped']);
            $done++;
            $after = $last;
        }

        return ['ok' => true, 'board' => $board, 'tally' => BackfillPlan::tally($tally, ['entries' => [], 'photos' => []], 0), 'batches' => $done];
    }

    /**
     * Removes the backfill rows of the board (entries still marked backfill, and their photos).
     *
     * @return array{ok: bool, error?: string, board?: object, entries?: int, photos?: int, batches?: int, failed?: array<string, mixed>}
     */
    public function undo(string $slug, int $batch = self::DEFAULT_BATCH): array
    {
        $board = $this->store->board($slug);
        if ($board === null) {
            return ['ok' => false, 'error' => 'board_unknown'];
        }

        $batch = max(1, $batch);
        $entries = $photos = $done = $after = 0;
        while (($ids = $this->store->backfilled((int) $board->id, $after, $batch)) !== []) {
            try {
                $removed = $this->store->transaction(fn (): array => $this->store->removeBackfilled((int) $board->id, $ids));
            } catch (\Throwable $e) {
                return ['ok' => false, 'error' => 'batch_failed', 'board' => $board, 'entries' => $entries, 'photos' => $photos,
                    'batches' => $done, 'failed' => ['from' => min($ids), 'to' => max($ids), 'exception' => $e::class]];
            }
            $entries += $removed['entries'];
            $photos += $removed['photos'];
            $done++;
            $after = max($ids);
        }

        return ['ok' => true, 'board' => $board, 'entries' => $entries, 'photos' => $photos, 'batches' => $done];
    }

    /**
     * @param  array<int, array{id: int, created_at: string, content: string}>  $posts
     * @return array{rows: array{entries: array<int, array<string, mixed>>, photos: array<int, array<string, mixed>>}, skipped: int}
     */
    private function plan(int $boardId, array $posts, string $timezone, string $now): array
    {
        $have = array_flip($this->store->withEntry(array_map(static fn (array $p): int => (int) $p['id'], $posts)));
        $todo = array_values(array_filter($posts, static fn (array $p): bool => ! isset($have[(int) $p['id']])));
        $attachments = $this->store->imageAttachments(array_map(static fn (array $p): int => (int) $p['id'], $todo));

        return [
            'rows' => BackfillPlan::rows($todo, $attachments, $boardId, $timezone, $now),
            'skipped' => count($posts) - count($todo),
        ];
    }

    /**
     * Inside the batch transaction: plan again (an author may have saved meanwhile), then insert.
     *
     * @param  array<int, array{id: int, created_at: string, content: string}>  $posts
     * @return array{rows: array{entries: array<int, array<string, mixed>>, photos: array<int, array<string, mixed>>}, skipped: int}
     */
    private function write(int $boardId, array $posts, string $timezone, string $now): array
    {
        $planned = $this->plan($boardId, $posts, $timezone, $now);
        if ($planned['rows']['entries'] !== []) {
            $this->store->insert($planned['rows']['entries'], $planned['rows']['photos']);
        }

        return $planned;
    }
}
