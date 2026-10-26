<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Plugins\G7\Diary\Addon\Support\CalendarGrid;
use Plugins\G7\Diary\Addon\Support\DayStats;

/**
 * Bundle 3 calendar: representative category (most posts, tie → written last, secret
 * posts left out — D6), year heatmap grid (start weekday, leap years, future days) and the
 * month cells (every emoji, narrow 2 + "+N").
 */
class CalendarTest extends TestCase
{
    /** @return array<int, array<string, mixed>> */
    private function cats(): array
    {
        $c = static fn (int $id, string $e, string $color, bool $def = false): array => ['id' => $id, 'emoji' => $e, 'name' => "c{$id}", 'label' => "c{$id}", 'color' => $color, 'is_default' => $def];

        return [1 => $c(1, '😊', '#F59E0B'), 2 => $c(2, '😢', '#3B82F6'), 4 => $c(4, '😌', '#10B981', true), 6 => $c(6, '🍜', '#F97316')];
    }

    /** @return array<string, mixed> */
    private function post(int $id, string $at, mixed $cat, bool $visible = true, string $date = '2026-10-15'): array
    {
        return ['id' => $id, 'at' => $at, 'date' => $date, 'category_id' => $cat, 'visible' => $visible];
    }

    public function test_representative_is_the_category_with_most_posts(): void
    {
        $s = DayStats::byDate([
            $this->post(1, '2026-10-15 01:00:00', 1),
            $this->post(2, '2026-10-15 02:00:00', 6),
            $this->post(3, '2026-10-15 03:00:00', 6),
        ], $this->cats(), 4)['2026-10-15'];

        $this->assertSame(6, $s['category']['id']);
        $this->assertSame(3, $s['count']);
        $this->assertSame(['😊', '🍜', '🍜'], $s['emojis']);
    }

    public function test_tie_goes_to_the_category_written_last(): void
    {
        // 1, 6, 1, 6 → 2:2, the last post (id 4) is category 6. Input order must not matter.
        $posts = [
            $this->post(4, '2026-10-15 04:00:00', 6),
            $this->post(1, '2026-10-15 01:00:00', 1),
            $this->post(3, '2026-10-15 03:00:00', 1),
            $this->post(2, '2026-10-15 02:00:00', 6),
        ];
        $this->assertSame(6, DayStats::byDate($posts, $this->cats(), 4)['2026-10-15']['category']['id']);

        // Same creation time: the higher id was written later.
        $same = [$this->post(8, '2026-10-15 05:00:00', 1), $this->post(9, '2026-10-15 05:00:00', 2)];
        $this->assertSame(2, DayStats::byDate($same, $this->cats(), 4)['2026-10-15']['category']['id']);
        $this->assertSame(2, DayStats::representative([1 => [2, 3], 2 => [2, 5], 6 => [1, 9]]));
        $this->assertNull(DayStats::representative([]));
    }

    public function test_secret_posts_the_viewer_cannot_open_count_but_do_not_colour(): void
    {
        $s = DayStats::byDate([
            $this->post(1, '2026-10-15 01:00:00', 1),
            $this->post(2, '2026-10-15 02:00:00', 6, false),
            $this->post(3, '2026-10-15 03:00:00', 6, false),
        ], $this->cats(), 4)['2026-10-15'];

        $this->assertSame(1, $s['category']['id'], 'locked posts take no part in the representative');
        $this->assertSame(3, $s['count']);
        $this->assertSame(2, $s['locked']);
        $this->assertSame(['😊', DayStats::LOCK, DayStats::LOCK], $s['emojis']);

        $only = DayStats::byDate([$this->post(5, '2026-10-15 01:00:00', 6, false)], $this->cats(), 4)['2026-10-15'];
        $this->assertNull($only['category']);
        $cell = CalendarGrid::cell('2026-10-15', $only, '2026-10-26');
        $this->assertTrue($cell['locked_only']);
        $this->assertNull($cell['color']);
    }

    public function test_missing_or_deleted_category_counts_as_default(): void
    {
        $s = DayStats::byDate([$this->post(1, '2026-10-15 01:00:00', null), $this->post(2, '2026-10-15 02:00:00', 99)], $this->cats(), 4)['2026-10-15'];

        $this->assertSame(4, $s['category']['id']);
        $this->assertSame(['😌', '😌'], $s['emojis']);
    }

    public function test_year_grid_starts_on_the_first_sunday_column(): void
    {
        $cols = CalendarGrid::yearColumns(2026); // 2026-01-01 is a Thursday
        $this->assertCount(53, $cols);
        $this->assertSame([null, null, null, null, '2026-01-01', '2026-01-02', '2026-01-03'], $cols[0]['days']);
        $this->assertSame('1월', $cols[0]['month']);
        $this->assertSame('2026-12-31', $cols[52]['days'][4]); // a Thursday
        $this->assertNull($cols[52]['days'][5]);
        $labels = array_values(array_filter(array_column($cols, 'month')));
        $this->assertSame(['1월', '2월', '3월', '4월', '5월', '6월', '7월', '8월', '9월', '10월', '11월', '12월'], $labels);
        $days = array_merge(...array_column($cols, 'days'));
        $this->assertCount(365, array_filter($days));
    }

    public function test_leap_years(): void
    {
        $days2024 = array_filter(array_merge(...array_column(CalendarGrid::yearColumns(2024), 'days')));
        $this->assertCount(366, $days2024);
        $this->assertTrue(in_array('2024-02-29', $days2024, true));
        // A leap year starting on Saturday spans 54 week columns (D79).
        $cols2028 = CalendarGrid::yearColumns(2028);
        $this->assertCount(54, $cols2028);
        $this->assertSame('2028-01-01', $cols2028[0]['days'][6]);
        $this->assertSame('2028-12-31', $cols2028[53]['days'][0]);
    }

    public function test_cells_mark_today_future_and_tooltips(): void
    {
        $today = CalendarGrid::cell('2026-10-26', null, '2026-10-26');
        $this->assertTrue($today['today']);
        $this->assertFalse($today['future']);
        $this->assertSame('10월 26일 · 기록 없음', $today['tip']);
        $this->assertSame(1, $today['dow']); // Monday

        $future = CalendarGrid::cell('2026-10-30', null, '2026-10-26');
        $this->assertTrue($future['future']);
        $this->assertSame('10월 30일', $future['tip']);

        $stats = ['count' => 2, 'category' => $this->cats()[1], 'emojis' => ['😊', '🍜'], 'locked' => 0];
        $this->assertSame('10월 15일 · 😊🍜 2편', CalendarGrid::cell('2026-10-15', $stats, '2026-10-26')['tip']);
        $this->assertSame('#F59E0B', CalendarGrid::cell('2026-10-15', $stats, '2026-10-26')['color']);
    }

    public function test_month_cells_show_every_emoji_and_narrow_two_plus_n(): void
    {
        $stats = ['count' => 5, 'category' => $this->cats()[6], 'emojis' => ['😊', '🍜', '🍜', DayStats::LOCK, '😢'], 'locked' => 1];
        $cell = CalendarGrid::cell('2026-10-15', $stats, '2026-10-26');

        $this->assertSame(['😊', '🍜', '🍜', DayStats::LOCK, '😢'], $cell['emojis']);
        $this->assertSame(['😊', '🍜'], $cell['emojis_narrow']);
        $this->assertSame(3, $cell['more_narrow']);
        $two = CalendarGrid::cell('2026-10-15', ['count' => 2, 'category' => null, 'emojis' => ['😊', '🍜'], 'locked' => 0], '2026-10-26');
        $this->assertSame(0, $two['more_narrow']);
    }

    public function test_month_weeks_cover_the_month(): void
    {
        $weeks = CalendarGrid::monthWeeks('2026-10'); // 1 Oct 2026 = Thursday, 31 Oct = Saturday
        $this->assertCount(5, $weeks);
        $this->assertSame('2026-09-27', $weeks[0][0]['date']);
        $this->assertFalse($weeks[0][0]['in_month']);
        $this->assertTrue($weeks[0][4]['in_month']);
        $this->assertSame('2026-10-31', $weeks[4][6]['date']);
        $this->assertCount(6, CalendarGrid::monthWeeks('2026-08')); // 1 Aug = Saturday → 6 rows
        $this->assertSame('2026-09', CalendarGrid::shiftMonth('2026-10', -1));
        $this->assertSame('2027-01', CalendarGrid::shiftMonth('2026-12', 1));
        $this->assertNull(CalendarGrid::month('2026-13'));
        $this->assertNull(CalendarGrid::month('26-10'));
        $this->assertSame('2026-10', CalendarGrid::month('2026-10'));
    }
}
