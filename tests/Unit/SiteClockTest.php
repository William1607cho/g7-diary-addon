<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Plugins\G7\Diary\Addon\Support\DiaryFieldRules;
use Plugins\G7\Diary\Addon\Support\SiteClock;

/**
 * "Today" depends on the site time zone, not on UTC.
 */
class SiteClockTest extends TestCase
{
    public function test_same_instant_is_a_different_day_in_different_zones(): void
    {
        // 2026-10-22 16:30 UTC = 2026-10-23 01:30 in Seoul, 2026-10-22 09:30 in Los Angeles.
        $now = new \DateTimeImmutable('2026-10-22 16:30:00', new \DateTimeZone('UTC'));

        $this->assertSame('2026-10-23', SiteClock::todayIn('Asia/Seoul', $now));
        $this->assertSame('2026-10-22', SiteClock::todayIn('UTC', $now));
        $this->assertSame('2026-10-22', SiteClock::todayIn('America/Los_Angeles', $now));
    }

    public function test_future_check_follows_the_site_zone(): void
    {
        $now = new \DateTimeImmutable('2026-10-22 16:30:00', new \DateTimeZone('UTC'));

        // In Seoul it is already the 23rd, so the 23rd is not in the future …
        $this->assertTrue(DiaryFieldRules::isNotFuture('2026-10-23', SiteClock::todayIn('Asia/Seoul', $now)));
        // … but in UTC it still is.
        $this->assertFalse(DiaryFieldRules::isNotFuture('2026-10-23', SiteClock::todayIn('UTC', $now)));
    }

    public function test_midnight_boundary(): void
    {
        $justBefore = new \DateTimeImmutable('2026-10-22 14:59:59', new \DateTimeZone('UTC'));
        $atMidnight = new \DateTimeImmutable('2026-10-22 15:00:00', new \DateTimeZone('UTC'));

        $this->assertSame('2026-10-22', SiteClock::todayIn('Asia/Seoul', $justBefore));
        $this->assertSame('2026-10-23', SiteClock::todayIn('Asia/Seoul', $atMidnight));
    }

    public function test_invalid_zone_falls_back_to_utc(): void
    {
        $now = new \DateTimeImmutable('2026-10-22 16:30:00', new \DateTimeZone('UTC'));

        $this->assertFalse(SiteClock::isValidZone('Mars/Olympus'));
        $this->assertFalse(SiteClock::isValidZone(''));
        $this->assertSame('2026-10-22', SiteClock::todayIn('Mars/Olympus', $now));
    }

    public function test_timezone_reads_core_setting_without_framework_falls_back(): void
    {
        // Outside the framework (no g7_core_settings / config helpers) the zone is UTC.
        if (function_exists('g7_core_settings')) {
            $this->markTestSkipped('framework helpers present');
        }
        $this->assertSame('UTC', SiteClock::timezone());
    }
}
