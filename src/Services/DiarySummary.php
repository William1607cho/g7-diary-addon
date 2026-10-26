<?php

namespace Plugins\G7\Diary\Addon\Services;

use Illuminate\Http\Request;
use Plugins\G7\Diary\Addon\Support\CategoryCounts;
use Plugins\G7\Diary\Addon\Support\DiaryCatalog;
use Plugins\G7\Diary\Addon\Support\SecretVisibility;
use Plugins\G7\Diary\Addon\Support\SiteClock;
use Plugins\G7\Diary\Addon\Support\Streak;

/**
 * Side panel data of a diary board: writing streak and posts per category (D6 / D38).
 *
 * Every live post counts for the streak and the total (existence is public — the list
 * shows secret posts too). Category counts leave out secret posts the viewer cannot open.
 */
class DiarySummary
{
    public function __construct(
        private readonly DiaryPostReader $reader,
        private readonly DiaryCatalog $catalog,
        private readonly SecretVisibility $visibility,
    ) {}

    /**
     * @return array{streak: array{current: int, longest: int, days: int}, total: int, today: string, categories: array<int, array<string, mixed>>}
     */
    public function forBoard(object $board, Request $request): array
    {
        $rows = $this->reader->summaryRows((int) $board->id);
        $visible = $this->visibility->visibleMap((string) $board->slug, $rows, $request);

        $dates = [];
        $categoryIds = [];
        foreach ($rows as $row) {
            $dates[] = DiaryPostReader::dateOf($row->diary_date, $row->created_at);
            if ($visible[(int) $row->id] ?? false) {
                $categoryIds[] = $row->category_id;
            }
        }

        $today = SiteClock::today();

        return [
            'streak' => Streak::compute($dates, $today),
            'total' => count($rows),
            'today' => $today,
            'categories' => CategoryCounts::count($categoryIds, $this->catalog->categories()),
        ];
    }
}
