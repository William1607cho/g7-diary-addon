<?php

namespace Plugins\G7\Diary\Addon\Support;

/**
 * Calendar grids (pure, for unit tests). Weeks start on Sunday.
 *
 * - Year heatmap: one column per week, from the week holding 1 January to the week holding
 *   31 December — 53 columns, 54 when a leap year starts on Saturday (D79). Days outside the
 *   year are null. A column whose days include the 1st of a month carries that month label.
 * - Month: the weeks (rows of 7) covering the month, with the days of the neighbouring
 *   months marked `in_month = false`.
 */
final class CalendarGrid
{
    /**
     * @return array<int, array{month: string, days: array<int, string|null>}>
     */
    public static function yearColumns(int $year): array
    {
        $first = new \DateTimeImmutable(sprintf('%04d-01-01', $year), new \DateTimeZone('UTC'));
        $day = $first->modify('-'.$first->format('w').' days');
        $columns = [];
        while ((int) $day->format('Y') <= $year) {
            $column = ['month' => '', 'days' => []];
            for ($i = 0; $i < 7; $i++) {
                $in = (int) $day->format('Y') === $year;
                $column['days'][] = $in ? $day->format('Y-m-d') : null;
                if ($in && $day->format('d') === '01') {
                    $column['month'] = ((int) $day->format('n')).'월';
                }
                $day = $day->modify('+1 day');
            }
            $columns[] = $column;
        }

        return $columns;
    }

    /**
     * @param  string  $month  YYYY-MM
     * @return array<int, array<int, array{date: string, day: int, in_month: bool}>>
     */
    public static function monthWeeks(string $month): array
    {
        $first = new \DateTimeImmutable($month.'-01', new \DateTimeZone('UTC'));
        $day = $first->modify('-'.$first->format('w').' days');
        $weeks = [];
        do {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $week[] = ['date' => $day->format('Y-m-d'), 'day' => (int) $day->format('j'), 'in_month' => $day->format('Y-m') === $month];
                $day = $day->modify('+1 day');
            }
            $weeks[] = $week;
        } while ($day->format('Y-m') === $month);

        return $weeks;
    }

    /**
     * Valid YYYY-MM within 1900–2999, or null.
     */
    public static function month(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^(19|2[0-9])[0-9]{2}-(0[1-9]|1[0-2])$/D', $value) === 1 ? $value : null;
    }

    public static function shiftMonth(string $month, int $by): string
    {
        return (new \DateTimeImmutable($month.'-01', new \DateTimeZone('UTC')))->modify(($by >= 0 ? '+' : '').$by.' month')->format('Y-m');
    }

    /**
     * "10월 15일" for Y-m-d.
     */
    public static function shortLabel(string $date): string
    {
        [, $m, $d] = array_map('intval', explode('-', $date));

        return "{$m}월 {$d}일";
    }

    /**
     * Display of one day for the calendar screens.
     *
     * @param  array{count: int, category: array<string, mixed>|null, emojis: array<int, string>, locked: int}|null  $stats
     * @return array<string, mixed>
     */
    public static function cell(string $date, ?array $stats, string $today, int $narrowEmojis = 2): array
    {
        $emojis = $stats['emojis'] ?? [];
        $count = $stats['count'] ?? 0;
        $label = self::shortLabel($date);

        return [
            'date' => $date,
            'day' => (int) substr($date, 8, 2),
            'dow' => (int) (new \DateTimeImmutable($date, new \DateTimeZone('UTC')))->format('w'),
            'count' => $count,
            'today' => $date === $today,
            'future' => $date > $today,
            'color' => $stats['category']['color'] ?? null,
            'locked_only' => $count > 0 && ($stats['category'] ?? null) === null,
            'emojis' => $emojis,
            'emojis_narrow' => array_slice($emojis, 0, $narrowEmojis),
            'more_narrow' => max(0, count($emojis) - $narrowEmojis),
            'tip' => $count > 0 ? $label.' · '.implode('', $emojis).' '.$count.'편' : ($date > $today ? $label : $label.' · 기록 없음'),
        ];
    }
}
