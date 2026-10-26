<?php

namespace Plugins\G7\Diary\Addon\Support;

/**
 * Picks posts for "지난 오늘" (pure, for unit tests).
 *
 * Order: ① this day in past years (same month and day, earlier years) → ② the same month
 * in past years → ③ any other post at random. Each step is shuffled; excluded ids are
 * skipped. Only posts the viewer may open are passed in (live, not blinded — D6).
 * 29 February: on 28 February of a common year, 29 February of past years counts as
 * "this day" too; on 29 February only past 29 Februaries do (D82).
 */
final class MemoryPicker
{
    public const YEARS_AGO = 'years_ago';

    public const SAME_MONTH = 'same_month';

    public const RANDOM = 'random';

    /**
     * @param  array<int, array{id: int, date: string}>  $candidates
     * @param  array<int, int>  $exclude
     * @param  callable(array<int, mixed>): array<int, mixed>  $shuffle
     * @return array<int, array{id: int, date: string, reason: string, label: string}>
     */
    public static function pick(array $candidates, string $today, array $exclude, int $count, callable $shuffle): array
    {
        $skip = array_flip(array_map('intval', $exclude));
        $tiers = [self::YEARS_AGO => [], self::SAME_MONTH => [], self::RANDOM => []];
        foreach ($candidates as $c) {
            if (! isset($skip[(int) $c['id']])) {
                $tiers[self::reason($c['date'], $today)][] = $c;
            }
        }

        $out = [];
        foreach ($tiers as $reason => $list) {
            foreach ($shuffle($list) as $c) {
                if (count($out) >= $count) {
                    return $out;
                }
                $out[] = ['id' => (int) $c['id'], 'date' => $c['date'], 'reason' => $reason, 'label' => self::label($reason, $c['date'], $today)];
            }
        }

        return $out;
    }

    public static function reason(string $date, string $today): string
    {
        $year = (int) substr($date, 0, 4);
        $thisYear = (int) substr($today, 0, 4);
        if ($year >= $thisYear) {
            return self::RANDOM;
        }
        $md = substr($date, 5, 5);
        $todayMd = substr($today, 5, 5);
        $leapDay = $todayMd === '02-28' && ! self::isLeap($thisYear) && $md === '02-29';
        if ($md === $todayMd || $leapDay) {
            return self::YEARS_AGO;
        }

        return substr($md, 0, 2) === substr($todayMd, 0, 2) ? self::SAME_MONTH : self::RANDOM;
    }

    /**
     * Badge text: "1년 전 오늘", "지난해 10월", "3년 전 10월", "랜덤".
     */
    public static function label(string $reason, string $date, string $today): string
    {
        $years = (int) substr($today, 0, 4) - (int) substr($date, 0, 4);
        $month = (int) substr($date, 5, 2);

        return match ($reason) {
            self::YEARS_AGO => "{$years}년 전 오늘",
            self::SAME_MONTH => $years === 1 ? "지난해 {$month}월" : "{$years}년 전 {$month}월",
            default => '랜덤',
        };
    }

    /**
     * Excluded ids from the query: comma-separated positive integers, at most 50 kept.
     *
     * @return array<int, int>
     */
    public static function parseExclude(mixed $value): array
    {
        if (! is_string($value) || $value === '') {
            return [];
        }
        $ids = [];
        foreach (explode(',', $value) as $part) {
            if (preg_match('/^[1-9][0-9]{0,9}$/D', trim($part)) === 1) {
                $ids[] = (int) trim($part);
            }
        }

        return array_slice(array_values(array_unique($ids)), -50);
    }

    private static function isLeap(int $year): bool
    {
        return ($year % 4 === 0 && $year % 100 !== 0) || $year % 400 === 0;
    }
}
