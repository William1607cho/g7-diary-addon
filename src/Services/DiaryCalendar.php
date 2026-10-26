<?php

namespace Plugins\G7\Diary\Addon\Services;

use Illuminate\Http\Request;
use Plugins\G7\Diary\Addon\Support\CalendarGrid;
use Plugins\G7\Diary\Addon\Support\DayStats;
use Plugins\G7\Diary\Addon\Support\DiaryCatalog;
use Plugins\G7\Diary\Addon\Support\DiaryMeta;
use Plugins\G7\Diary\Addon\Support\SecretVisibility;
use Plugins\G7\Diary\Addon\Support\SiteClock;

/**
 * Year heatmap, month calendar and day list of a diary board (bundle 3, D6).
 *
 * Every live post counts for its day; secret posts the viewer cannot open add to the
 * count only — no category colour, emoji or title (a lock instead). Dates are diary dates,
 * "today" is the site time zone's.
 */
class DiaryCalendar
{
    public function __construct(
        private readonly DiaryViewReader $reader,
        private readonly DiaryPostReader $posts,
        private readonly DiaryCatalog $catalog,
        private readonly SecretVisibility $visibility,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function heatmap(object $board, ?int $year, Request $request): array
    {
        $today = SiteClock::today();
        $year ??= (int) substr($today, 0, 4);
        $stats = $this->stats($board, sprintf('%04d-01-01', $year), sprintf('%04d-12-31', $year), $request);

        $columns = [];
        foreach (CalendarGrid::yearColumns($year) as $column) {
            $days = [];
            foreach ($column['days'] as $date) {
                $days[] = $date === null ? ['out' => true] : ['out' => false] + CalendarGrid::cell($date, $stats[$date] ?? null, $today);
            }
            $columns[] = ['month' => $column['month'], 'days' => $days];
        }

        return [
            'year' => $year,
            'this_year' => $year === (int) substr($today, 0, 4),
            'prev_year' => $year - 1,
            'next_year' => $year + 1,
            'today' => $today,
            'total' => array_sum(array_column($stats, 'count')),
            'has_locked' => array_sum(array_column($stats, 'locked')) > 0,
            'columns' => $columns,
            'legend' => $this->catalog->categories(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function month(object $board, ?string $month, Request $request): array
    {
        $today = SiteClock::today();
        $month ??= substr($today, 0, 7);
        $weeks = CalendarGrid::monthWeeks($month);
        $first = $weeks[0][0]['date'];
        $last = $weeks[array_key_last($weeks)][6]['date'];
        $stats = $this->stats($board, $first, $last, $request);

        $rows = [];
        foreach ($weeks as $week) {
            $rows[] = array_map(static fn (array $d): array => ['in_month' => $d['in_month']] + CalendarGrid::cell($d['date'], $stats[$d['date']] ?? null, $today), $week);
        }

        return [
            'month' => $month,
            'label' => (int) substr($month, 0, 4).'년 '.(int) substr($month, 5, 2).'월',
            'prev' => CalendarGrid::shiftMonth($month, -1),
            'next' => CalendarGrid::shiftMonth($month, 1),
            'today' => $today,
            'weeks' => $rows,
        ];
    }

    /**
     * Posts of one diary date in writing order: emoji, title and weather — or a lock.
     *
     * @return array<string, mixed>
     */
    public function day(object $board, string $date, Request $request): array
    {
        $rows = $this->rowsBetween($board, $date, $date);
        usort($rows, static fn (object $a, object $b): int => [(string) $a->created_at, (int) $a->id] <=> [(string) $b->created_at, (int) $b->id]);
        $visible = $this->visibility->visibleMap((string) $board->slug, $rows, $request);
        $open = array_keys(array_filter($visible));
        $titles = $this->reader->titles($open);
        $entries = $this->posts->entries($open);

        $items = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            $locked = ! ($visible[$id] ?? false);
            $meta = $this->catalog->meta($entries[$id] ?? null, $date, $locked);
            $items[] = [
                'id' => $id,
                'locked' => $locked,
                'title' => $locked ? null : ($titles[$id] ?? ''),
                'emoji' => $locked ? DayStats::LOCK : ($meta['category']['emoji'] ?? ''),
                'category' => $meta['category']['name'] ?? null,
                'weather' => $meta['weather'],
            ];
        }
        $labels = DiaryMeta::dateLabels($date);

        return [
            'date' => $date,
            'label' => (int) substr($date, 5, 2).'월 '.(int) substr($date, 8, 2).'일 '.$labels['weekday'].'요일',
            'count' => count($items),
            'items' => $items,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function stats(object $board, string $from, string $to, Request $request): array
    {
        $rows = $this->rowsBetween($board, $from, $to);
        $visible = $this->visibility->visibleMap((string) $board->slug, $rows, $request);
        $posts = [];
        foreach ($rows as $row) {
            $posts[] = [
                'id' => (int) $row->id,
                'at' => (string) $row->created_at,
                'date' => DiaryPostReader::dateOf($row->diary_date, $row->created_at),
                'category_id' => $row->category_id,
                'visible' => $visible[(int) $row->id] ?? false,
            ];
        }

        return DayStats::byDate($posts, $this->catalog->categoriesById(), $this->catalog->defaultCategoryId());
    }

    /**
     * Live posts whose diary date lies in [from, to] (site-zone dates).
     *
     * @return array<int, object>
     */
    private function rowsBetween(object $board, string $from, string $to): array
    {
        $zone = new \DateTimeZone(SiteClock::timezone());
        $utc = new \DateTimeZone('UTC');
        $utcFrom = (new \DateTimeImmutable($from.' 00:00:00', $zone))->setTimezone($utc)->format('Y-m-d H:i:s');
        $utcTo = (new \DateTimeImmutable($to.' 00:00:00', $zone))->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s');

        return $this->reader->posts((int) $board->id, [$from, $to, $utcFrom, $utcTo]);
    }
}
