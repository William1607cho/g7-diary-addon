<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Plugins\G7\Diary\Addon\Support\DiaryFieldRules;
use Plugins\G7\Diary\Addon\Support\DiarySettings;

/**
 * Weather key / category id / diary date checks.
 */
class DiaryFieldRulesTest extends TestCase
{
    public function test_active_category_ids_are_accepted(): void
    {
        $active = [1, 2, 4];
        $this->assertTrue(DiaryFieldRules::isActiveCategory(4, $active));
        $this->assertTrue(DiaryFieldRules::isActiveCategory('2', $active));
    }

    public function test_unknown_deleted_or_malformed_category_is_rejected(): void
    {
        $active = [1, 2, 4];
        foreach ([3, 0, -1, '04', '1.0', 'x', 1.0, true, null, [1]] as $bad) {
            $this->assertFalse(DiaryFieldRules::isActiveCategory($bad, $active));
        }
    }

    public function test_default_weather_keys_are_accepted(): void
    {
        $keys = DiarySettings::weatherKeys(DiarySettings::normalize([]));
        $this->assertSame(['sunny', 'partly_cloudy', 'cloudy', 'rain', 'thunderstorm', 'snow'], $keys);
        foreach ($keys as $k) {
            $this->assertTrue(DiaryFieldRules::isWeatherKey($k, $keys));
        }
    }

    public function test_unknown_or_case_changed_weather_is_rejected(): void
    {
        $keys = DiarySettings::weatherKeys(DiarySettings::normalize([]));
        foreach (['Sunny', 'fog', '맑음', '', 1, null] as $bad) {
            $this->assertFalse(DiaryFieldRules::isWeatherKey($bad, $keys));
        }
    }

    public function test_date_format(): void
    {
        $this->assertTrue(DiaryFieldRules::isDate('2026-10-22'));
        $this->assertTrue(DiaryFieldRules::isDate('2024-02-29'));
        foreach (['2026-02-30', '2026-13-01', '2026-1-1', '20261022', '2026-10-22 00:00', '', null, 20261022] as $bad) {
            $this->assertFalse(DiaryFieldRules::isDate($bad));
        }
    }

    public function test_future_date_is_rejected(): void
    {
        $this->assertTrue(DiaryFieldRules::isNotFuture('2026-10-22', '2026-10-22'));
        $this->assertTrue(DiaryFieldRules::isNotFuture('2025-12-31', '2026-10-22'));
        $this->assertFalse(DiaryFieldRules::isNotFuture('2026-10-23', '2026-10-22'));
    }
}
