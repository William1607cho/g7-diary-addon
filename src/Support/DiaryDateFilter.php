<?php

namespace Plugins\G7\Diary\Addon\Support;

/**
 * The `diary_date` list filter (pure, for unit tests). Panel month calendar, bundle 3.
 *
 * A post belongs to a date by its diary date; a post without an entry row falls back to
 * its creation time as a site-zone date (D27), so the plan also carries that day's UTC
 * range. The date reveals nothing a card does not show (locked cards keep their date), so
 * this filter needs no secret step of its own; with a category filter the category plan
 * adds it (S1 / D56).
 */
final class DiaryDateFilter
{
    public const PARAM = 'diary_date';

    /**
     * A valid Y-m-d (1900–2999) or null — anything else is ignored (no filter).
     */
    public static function parse(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^((?:19|2[0-9])[0-9]{2})-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])$/D', $value, $m) !== 1) {
            return null;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $value : null;
    }

    /**
     * Plan step: `['on_date', [date, utcFrom, utcTo]]` — the UTC range is [from, to).
     *
     * @return array{0: string, 1: array{0: string, 1: string, 2: string}}
     */
    public static function step(string $date, string $timezone): array
    {
        $zone = new \DateTimeZone(SiteClock::isValidZone($timezone) ? $timezone : 'UTC');
        $utc = new \DateTimeZone('UTC');
        $from = new \DateTimeImmutable($date.' 00:00:00', $zone);

        return ['on_date', [$date, $from->setTimezone($utc)->format('Y-m-d H:i:s'), $from->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s')]];
    }

    /**
     * Count-cache part of the date.
     */
    public static function cachePart(string $date): string
    {
        return 'g7da_date:'.$date;
    }
}
