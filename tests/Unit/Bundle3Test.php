<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Plugins\G7\Diary\Addon\Layout\Fragments;
use Plugins\G7\Diary\Addon\Layout\ScreenInjector;
use Plugins\G7\Diary\Addon\Support\DiaryCategoryFilter;
use Plugins\G7\Diary\Addon\Support\DiaryDateFilter;
use Plugins\G7\Diary\Addon\Support\DiaryRoutes;
use Plugins\G7\Diary\Addon\Support\MemoryPicker;
use Plugins\G7\Diary\Addon\Support\PhotoPager;

/**
 * Bundle 3: photo month cursor and page-boundary headings, 지난 오늘 picking order,
 * the diary_date filter parameter, the screen routes and the screen injection.
 */
class Bundle3Test extends TestCase
{
    /** @return array<int, array<string, mixed>> 4 in 2026-10, 3 in 2026-09, 2 in 2025-10 */
    private function cells(): array
    {
        $out = [];
        foreach (['2026-10' => 4, '2026-09' => 3, '2025-10' => 2] as $month => $n) {
            for ($i = 0; $i < $n; $i++) {
                $out[] = ['month' => $month, 'n' => $month.'#'.$i];
            }
        }

        return $out;
    }

    public function test_photo_pages_repeat_the_month_heading_across_a_boundary(): void
    {
        $p1 = PhotoPager::page($this->cells(), null, 3);
        $this->assertCount(1, $p1['groups']);
        $this->assertSame('2026년 10월', $p1['groups'][0]['label']);
        $this->assertSame(4, $p1['groups'][0]['total'], 'heading carries the whole month');
        $this->assertSame('2026-10.3', $p1['next_cursor']);
        $this->assertNull($p1['prev_cursor']);
        $this->assertSame([1, 3, 9], [$p1['page'], $p1['pages'], $p1['total']]);

        $p2 = PhotoPager::page($this->cells(), $p1['next_cursor'], 3);
        $this->assertSame(['2026-10', '2026-09'], array_column($p2['groups'], 'month'), 'October again at the top');
        $this->assertSame(['2026-10#3'], array_column($p2['groups'][0]['cells'], 'n'));
        $this->assertSame(4, $p2['groups'][0]['total']);
        $this->assertSame('2026-09.2', $p2['next_cursor']);
        $this->assertSame('2026-10', $p2['prev_cursor']);

        $p3 = PhotoPager::page($this->cells(), $p2['next_cursor'], 3);
        $this->assertSame(['2026-09', '2025-10'], array_column($p3['groups'], 'month'));
        $this->assertFalse($p3['has_more']);
        $this->assertNull($p3['next_cursor']);
    }

    public function test_photo_cursor_values(): void
    {
        $cells = $this->cells();
        $this->assertSame(['2026-09#0'], array_column(PhotoPager::page($cells, '2026-09', 1)['groups'][0]['cells'], 'n'));
        // A month without photos starts at the next older month that has some.
        $this->assertSame('2025-10', PhotoPager::page($cells, '2026-01', 5)['groups'][0]['month']);
        // Invalid cursors start from the top; a cursor past the end gives an empty page.
        foreach (['', 'x', '2026-13', '2026-10.0', '2026-10.-1', '2026-10;1'] as $bad) {
            $this->assertSame(1, PhotoPager::page($cells, $bad, 3)['page'], $bad);
        }
        $this->assertSame([], PhotoPager::page($cells, '2000-01', 3)['groups']);
        $this->assertSame([], PhotoPager::page([], null, 30)['groups']);
        $this->assertSame(0, PhotoPager::page([], null, 30)['total']);
    }

    /** @return callable(array<int, mixed>): array<int, mixed> */
    private function keepOrder(): callable
    {
        return static fn (array $l): array => $l;
    }

    public function test_memories_pick_this_day_then_same_month_then_random(): void
    {
        $c = [
            ['id' => 1, 'date' => '2026-03-01'],
            ['id' => 2, 'date' => '2025-10-03'],
            ['id' => 3, 'date' => '2024-10-26'],
            ['id' => 4, 'date' => '2025-10-26'],
            ['id' => 5, 'date' => '2026-10-26'], // this year, same day: random tier
        ];
        $p = MemoryPicker::pick($c, '2026-10-26', [], 3, $this->keepOrder());

        $this->assertSame([3, 4, 2], array_column($p, 'id'));
        $this->assertSame(['2년 전 오늘', '1년 전 오늘', '지난해 10월'], array_column($p, 'label'));
        $this->assertSame([1, 5], array_column(MemoryPicker::pick($c, '2026-10-26', [3, 4, 2], 3, $this->keepOrder()), 'id'));
        $this->assertSame('랜덤', MemoryPicker::pick($c, '2026-10-26', [3, 4, 2], 1, $this->keepOrder())[0]['label']);
        $this->assertSame([], MemoryPicker::pick($c, '2026-10-26', [1, 2, 3, 4, 5], 3, $this->keepOrder()));
        $this->assertSame('3년 전 10월', MemoryPicker::label(MemoryPicker::SAME_MONTH, '2023-10-02', '2026-10-26'));
    }

    public function test_memories_feb_29(): void
    {
        // 28 Feb of a common year: past 29 Februaries count as "this day".
        $this->assertSame(MemoryPicker::YEARS_AGO, MemoryPicker::reason('2024-02-29', '2027-02-28'));
        $this->assertSame(MemoryPicker::YEARS_AGO, MemoryPicker::reason('2025-02-28', '2027-02-28'));
        // In a leap year the 28th is just the 28th; 29 Feb only matches 29 Feb.
        $this->assertSame(MemoryPicker::SAME_MONTH, MemoryPicker::reason('2024-02-29', '2028-02-28'));
        $this->assertSame(MemoryPicker::YEARS_AGO, MemoryPicker::reason('2024-02-29', '2028-02-29'));
        $this->assertSame(MemoryPicker::SAME_MONTH, MemoryPicker::reason('2025-02-28', '2028-02-29'));
        $this->assertSame(MemoryPicker::RANDOM, MemoryPicker::reason('2024-03-01', '2027-02-28'));
    }

    public function test_memories_exclude_parameter(): void
    {
        $this->assertSame([3, 4], MemoryPicker::parseExclude('3,4,3'));
        $this->assertSame([7], MemoryPicker::parseExclude('x, 7 ,-1,0,01'));
        $this->assertSame([], MemoryPicker::parseExclude(null));
        $this->assertCount(50, MemoryPicker::parseExclude(implode(',', range(1, 80))));
    }

    public function test_date_filter_parameter(): void
    {
        $this->assertSame('2026-10-26', DiaryDateFilter::parse('2026-10-26'));
        $this->assertSame('2024-02-29', DiaryDateFilter::parse('2024-02-29'));
        foreach (['', '2026-02-29', '2026-13-01', '2026-10-32', '2026-1-5', '2026-10-26 ', "2026-10-26\n", '1899-12-31', null, ['2026-10-26']] as $bad) {
            $this->assertNull(DiaryDateFilter::parse($bad), var_export($bad, true));
        }
        // Seoul day 2026-10-26 = UTC [10-25 15:00, 10-26 15:00).
        $this->assertSame(['on_date', ['2026-10-26', '2026-10-25 15:00:00', '2026-10-26 15:00:00']], DiaryDateFilter::step('2026-10-26', 'Asia/Seoul'));
        $this->assertSame('g7da_date:2026-10-26', DiaryDateFilter::cachePart('2026-10-26'));
        $this->assertSame([2, 6], DiaryCategoryFilter::othersFor(4, 4, [2, 4, 6]));
        $this->assertNull(DiaryCategoryFilter::othersFor(2, 4, [2, 4, 6]));
    }

    public function test_routes_only_on_user_templates_and_once(): void
    {
        $base = [['path' => '/board/:slug', 'layout' => 'board/index']];
        $user = DiaryRoutes::merge($base, 'user');
        $this->assertCount(3, $user);
        $this->assertSame('/diary/:slug/calendar', $user[1]['path']);
        $this->assertSame('g7-diary-addon.photos', $user[2]['layout']);
        $this->assertFalse($user[1]['auth_required']);
        $this->assertSame($user, DiaryRoutes::merge($user, 'user'), 'no duplicates');
        $this->assertSame($base, DiaryRoutes::merge($base, 'admin'));
    }

    public function test_screen_root_is_filled_once_and_foreign_layouts_are_left(): void
    {
        $f = new Fragments(dirname(__DIR__, 2).'/resources/layout');
        $tree = [['type' => 'layout', 'name' => 'Main', 'children' => [['id' => 'g7da_calendar_root', 'type' => 'basic', 'name' => 'Div']]]];
        [$out, $r] = (new ScreenInjector($f, 'calendar'))->apply($tree);
        $this->assertSame(['screen' => 'applied'], $r);
        $json = json_encode($out, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString(Fragments::INCLUDE_KEY, $json);
        $this->assertStringContainsString('g7daHeat', $json);
        $this->assertSame(['screen' => 'already'], (new ScreenInjector($f, 'calendar'))->apply($out)[1]);
        $this->assertSame(['screen' => 'absent'], (new ScreenInjector($f, 'photos'))->apply($tree)[1]);

        $this->assertSame('calendar', ScreenInjector::screenOf('g7-diary-addon.calendar'));
        $this->assertSame('photos', ScreenInjector::screenOf('photos'));
        $this->assertNull(ScreenInjector::screenOf('board/index'));
    }

    public function test_bundle3_fragments_resolve_and_repeat_items_not_containers(): void
    {
        $f = new Fragments(dirname(__DIR__, 2).'/resources/layout');
        foreach (['calendar/screen', 'photos/screen', 'index/panel-month', 'common/memories'] as $name) {
            $json = json_encode($f->get($name), JSON_UNESCAPED_UNICODE);
            $this->assertStringNotContainsString(Fragments::INCLUDE_KEY, $json, $name);
            $this->assertStringNotContainsString('"title"', $json, $name);
        }
        // Grid containers hold the repeated item; they do not repeat themselves (D60).
        $month = $f->get('calendar/month');
        $this->assertTrue(! isset($month['children'][2]['iteration']) && isset($month['children'][2]['children'][0]['iteration']));
        $pcal = $f->get('index/panel-month');
        $this->assertTrue(! isset($pcal['children'][2]['iteration']) && isset($pcal['children'][2]['children'][0]['iteration']));
        // Screens sit in the board list's frame (Container py-8) and lifecycle includes are resolved.
        foreach (['calendar/screen', 'photos/screen'] as $name) {
            $screen = $f->get($name);
            $this->assertSame(['Container', 'py-8'], [$screen['name'], $screen['props']['className']], $name);
            $this->assertSame('apiCall', $screen['children'][0]['lifecycle']['onMount'][0]['handler'], $name);
        }
    }
}
