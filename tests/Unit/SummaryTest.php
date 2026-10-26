<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Plugins\G7\Diary\Addon\Support\CategoryCounts;
use Plugins\G7\Diary\Addon\Support\DiaryMeta;
use Plugins\G7\Diary\Addon\Support\SiteClock;
use Plugins\G7\Diary\Addon\Support\Streak;

/**
 * Streaks (time zone boundary, gaps, several posts a day), posts per category (D6) and
 * the card / show meta.
 */
class SummaryTest extends TestCase
{
    private const CATS = [
        ['id' => 1, 'emoji' => '😊', 'name' => '행복일기', 'color' => '#EAB308', 'is_default' => false],
        ['id' => 4, 'emoji' => '😌', 'name' => '평온한 하루', 'color' => '#22C55E', 'is_default' => true],
        ['id' => 6, 'emoji' => '🍜', 'name' => '맛집탐방', 'color' => '#F97316', 'is_default' => false],
    ];

    public function test_streak_counts_consecutive_days_to_today(): void
    {
        $r = Streak::compute(['2026-10-24', '2026-10-25', '2026-10-26'], '2026-10-26');

        $this->assertSame(['current' => 3, 'longest' => 3, 'days' => 3], $r);
    }

    public function test_streak_still_counts_when_today_is_not_written_yet(): void
    {
        $this->assertSame(2, Streak::compute(['2026-10-24', '2026-10-25'], '2026-10-26')['current']);
    }

    public function test_streak_breaks_after_a_gap_day(): void
    {
        $r = Streak::compute(['2026-10-20', '2026-10-21', '2026-10-22', '2026-10-24'], '2026-10-26');

        $this->assertSame(0, $r['current']);
        $this->assertSame(3, $r['longest']);
    }

    public function test_several_posts_on_one_day_count_once(): void
    {
        $r = Streak::compute(['2026-10-26', '2026-10-26', '2026-10-25', '2026-10-25', '2026-10-25'], '2026-10-26');

        $this->assertSame(['current' => 2, 'longest' => 2, 'days' => 2], $r);
    }

    public function test_longest_over_month_and_year_boundaries(): void
    {
        $r = Streak::compute(['2025-12-30', '2025-12-31', '2026-01-01', '2026-01-02', '2026-02-28', '2026-03-01'], '2026-03-01');

        $this->assertSame(4, $r['longest']);
        $this->assertSame(2, $r['current']);
    }

    public function test_future_and_invalid_dates_are_ignored(): void
    {
        $r = Streak::compute(['2026-10-27', 'x', '2026-02-30', '2026-10-26'], '2026-10-26');

        $this->assertSame(['current' => 1, 'longest' => 1, 'days' => 1], $r);
    }

    public function test_empty(): void
    {
        $this->assertSame(['current' => 0, 'longest' => 0, 'days' => 0], Streak::compute([], '2026-10-26'));
    }

    public function test_streak_follows_the_site_time_zone_of_today(): void
    {
        // 2026-10-26 15:30 UTC is already the 27th in Seoul: a streak ending on the 26th still counts there.
        $now = new \DateTimeImmutable('2026-10-26 15:30:00', new \DateTimeZone('UTC'));
        $dates = ['2026-10-25', '2026-10-26'];

        $this->assertSame(2, Streak::compute($dates, SiteClock::todayIn('Asia/Seoul', $now))['current']);
        $this->assertSame(2, Streak::compute($dates, SiteClock::todayIn('UTC', $now))['current']);
        // Two days later (the 29th in Seoul) the streak is broken.
        $later = new \DateTimeImmutable('2026-10-28 16:00:00', new \DateTimeZone('UTC'));
        $this->assertSame(0, Streak::compute($dates, SiteClock::todayIn('Asia/Seoul', $later))['current']);
    }

    public function test_category_counts_put_missing_and_deleted_into_the_default(): void
    {
        // Visible posts only: 1, 1, 6, null (no category), 99 (deleted), '4'.
        $out = CategoryCounts::count([1, 1, 6, null, 99, '4'], self::CATS);

        $this->assertSame([2, 3, 1], array_column($out, 'count'));
        $this->assertSame(4, CategoryCounts::defaultId(self::CATS));
    }

    public function test_category_counts_leave_out_hidden_secret_posts(): void
    {
        // The caller passes only categories of posts the viewer may see (D6) — a hidden secret
        // post of category 6 is simply not in the input, so category 6 stays 0.
        $visible = [1, 4];
        $this->assertSame([1, 1, 0], array_column(CategoryCounts::count($visible, self::CATS), 'count'));
    }

    public function test_meta_for_card_and_show(): void
    {
        $byId = array_column(self::CATS, null, 'id');
        $weather = [['key' => 'rain', 'emoji' => '🌧️', 'label' => '비']];
        $m = DiaryMeta::present(['category_id' => 6, 'weather' => 'rain', 'diary_date' => '2026-10-21'], '2026-10-26', $byId, 4, $weather, false);

        $this->assertSame('10월 21일 (수)', $m['date_label']);
        $this->assertSame('2026년 10월 21일 수요일', $m['date_long']);
        $this->assertSame(6, $m['category']['id']);
        $this->assertSame('비', $m['weather']['label']);
        $this->assertFalse($m['locked']);
    }

    public function test_meta_default_category_and_locked(): void
    {
        $byId = array_column(self::CATS, null, 'id');
        $null = DiaryMeta::present(['category_id' => null, 'weather' => null, 'diary_date' => '2026-10-26'], '2026-10-26', $byId, 4, [], false);
        $deleted = DiaryMeta::present(['category_id' => 99, 'weather' => 'fog', 'diary_date' => '2026-10-26'], '2026-10-26', $byId, 4, [], false);
        $noEntry = DiaryMeta::present(null, '2026-10-20', $byId, 4, [], false);
        $locked = DiaryMeta::present(['category_id' => 6, 'weather' => 'rain', 'diary_date' => '2026-10-21'], '2026-10-26', $byId, 4, [], true);

        $this->assertSame(4, $null['category']['id']);
        $this->assertNull($null['weather']);
        $this->assertSame(4, $deleted['category']['id']);
        $this->assertNull($deleted['weather']);
        $this->assertSame('2026-10-20', $noEntry['date']);
        $this->assertSame(4, $noEntry['category']['id']);
        $this->assertTrue($locked['locked']);
        $this->assertNull($locked['category']);
        $this->assertNull($locked['weather']);
        $this->assertSame('2026-10-21', $locked['date']);
    }

    public function test_box_label_never_leaves_one_character_alone_on_a_line(): void
    {
        $nb = "\u{00A0}";
        $this->assertSame("개빡치는{$nb}날", DiaryMeta::label('개빡치는 날'));     // last word one char
        $this->assertSame("새{$nb}일기 모음", DiaryMeta::label('새 일기 모음'));   // first word one char
        $this->assertSame('스페샬 이벤트', DiaryMeta::label('스페샬 이벤트'));      // normal break kept
        $this->assertSame('평온한 하루', DiaryMeta::label(' 평온한  하루 '));
        $this->assertSame('분노일기', DiaryMeta::label('분노일기'));
    }

    public function test_category_public_shape_and_colour_guard(): void
    {
        $c = DiaryMeta::category(['id' => '2', 'name' => ['ko' => '우울일기'], 'icon_type' => 'emoji', 'icon_value' => '😢', 'color' => 'red;background:url(x)', 'is_default' => 0]);

        $this->assertSame(['id' => 2, 'emoji' => '😢', 'name' => '우울일기', 'label' => '우울일기', 'color' => '#9CA3AF', 'is_default' => false], $c);
        $this->assertSame('#3B82F6', DiaryMeta::color('#3b82f6'));
    }
}
