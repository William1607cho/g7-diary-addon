<?php

namespace Plugins\G7\Diary\Addon\Support;

/**
 * Writing streaks from diary dates (pure, for unit tests). D38.
 *
 * - Days are diary dates (Y-m-d, site time zone). Several posts on one day count once.
 * - Current streak: consecutive days ending today; if nothing is written today yet, the
 *   streak that ended yesterday still counts (it is only broken once today is over).
 * - Longest: the longest run of consecutive days ever. Dates after today are ignored.
 */
final class Streak
{
    /**
     * @param  iterable<int, string>  $dates  Diary dates, any order, duplicates allowed.
     * @return array{current: int, longest: int, days: int}
     */
    public static function compute(iterable $dates, string $today): array
    {
        $days = self::distinctDays($dates, $today);
        if ($days === []) {
            return ['current' => 0, 'longest' => 0, 'days' => 0];
        }

        $longest = 1;
        $run = 1;
        for ($i = 1, $n = count($days); $i < $n; $i++) {
            $run = $days[$i] - $days[$i - 1] === 1 ? $run + 1 : 1;
            $longest = max($longest, $run);
        }

        return ['current' => self::current($days, self::dayNumber($today)), 'longest' => $longest, 'days' => count($days)];
    }

    /**
     * @param  array<int, int>  $days  Sorted distinct day numbers.
     */
    private static function current(array $days, int $today): int
    {
        $last = end($days);
        if ($last !== $today && $last !== $today - 1) {
            return 0;
        }

        $count = 1;
        for ($i = count($days) - 1; $i > 0; $i--) {
            if ($days[$i] - $days[$i - 1] !== 1) {
                break;
            }
            $count++;
        }

        return $count;
    }

    /**
     * @param  iterable<int, string>  $dates
     * @return array<int, int> Sorted distinct day numbers, today or earlier.
     */
    private static function distinctDays(iterable $dates, string $today): array
    {
        $limit = self::dayNumber($today);
        $set = [];
        foreach ($dates as $date) {
            if (! is_string($date) || ! DiaryFieldRules::isDate($date)) {
                continue;
            }
            $day = self::dayNumber($date);
            if ($day <= $limit) {
                $set[$day] = true;
            }
        }
        $days = array_keys($set);
        sort($days);

        return $days;
    }

    /**
     * Days since the epoch for a Y-m-d date (calendar arithmetic, no time zone involved).
     */
    private static function dayNumber(string $date): int
    {
        $midnight = new \DateTimeImmutable($date.' 00:00:00', new \DateTimeZone('UTC'));

        return intdiv($midnight->getTimestamp(), 86400);
    }
}
