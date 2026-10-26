<?php

namespace Plugins\G7\Diary\Addon\Console\Commands;

use Illuminate\Console\Command;
use Plugins\G7\Diary\Addon\Services\DiaryBackfill;
use Plugins\G7\Diary\Addon\Support\BackfillPlan;
use Plugins\G7\Diary\Addon\Support\SiteClock;

/**
 * `g7-diary-addon:backfill {board}` — diary metadata and the photo index for posts written
 * before the board became a diary (category / weather empty, diary date = creation day in
 * the site time zone, title kept). Prints numbers only, never a title or a body.
 *
 *   --dry-run  report only (any board type)
 *   --undo     remove only the rows this command made (a post its author edited since keeps its row)
 *   --batch=N  posts per transaction (default 200)
 */
class BackfillCommand extends Command
{
    protected $signature = 'g7-diary-addon:backfill
        {board : 게시판 slug}
        {--dry-run : 쓰지 않고 숫자만 보고한다(게시판 유형과 무관)}
        {--undo : 이 명령으로 만든 일기 메타·사진 색인만 지운다}
        {--batch=200 : 한 트랜잭션에 처리할 글 수}';

    protected $description = '일기장: 기존 글에 일기 메타와 사진 색인을 채웁니다(--undo로 되돌림)';

    public function handle(DiaryBackfill $backfill): int
    {
        $slug = (string) $this->argument('board');
        $batch = (int) $this->option('batch');
        if ($batch < 1 || $batch > 5000) {
            $this->error('--batch 는 1~5000 이어야 합니다.');

            return self::INVALID;
        }
        if ($this->option('undo') && $this->option('dry-run')) {
            $this->error('--undo 와 --dry-run 은 함께 쓸 수 없습니다.');

            return self::INVALID;
        }

        if ($this->option('undo')) {
            return $this->report($backfill->undo($slug, $batch), true);
        }

        $dryRun = (bool) $this->option('dry-run');
        $result = $backfill->run($slug, $dryRun, SiteClock::timezone(), $batch);
        if (($result['error'] ?? null) === 'not_diary') {
            $this->error("게시판 {$slug} 은 일기 유형이 아니라 실행하지 않았습니다(쓰기 0). --dry-run 은 유형과 무관하게 쓸 수 있습니다.");

            return self::FAILURE;
        }

        return $this->report($result, false, $dryRun);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function report(array $result, bool $undo, bool $dryRun = false): int
    {
        if (($result['error'] ?? null) === 'board_unknown') {
            $this->error('그런 게시판이 없습니다.');

            return self::FAILURE;
        }

        $board = $result['board'];
        $mode = $undo ? '되돌리기' : ($dryRun ? '[모의] 일기화' : '일기화');
        $this->info("{$mode} · 게시판 {$board->slug}(유형 {$board->type}) · 묶음 {$result['batches']}개");
        if ($undo) {
            $this->line("지운 일기 메타 {$result['entries']}행 · 사진 색인 {$result['photos']}행");
        } else {
            foreach (BackfillPlan::lines($result['tally'] ?? []) as $line) {
                $this->line(($dryRun ? '' : '추가 ').$line);
            }
        }

        if (! $result['ok']) {
            $f = $result['failed'];
            $this->error("실패: 글 id {$f['from']}~{$f['to']} 묶음을 되돌리고 멈췄습니다({$f['exception']}). 앞 묶음은 반영됨 — 다시 실행하면 이어서 처리합니다.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
