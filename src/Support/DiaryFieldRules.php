<?php

namespace Plugins\G7\Diary\Addon\Support;

/**
 * Value checks for the diary request fields (pure, for unit tests).
 *
 * The Laravel rules built by {@see \Plugins\G7\Diary\Addon\Listeners\DiaryValidationListener}
 * call these, so the accepted values are decided in one place.
 */
class DiaryFieldRules
{
    /**
     * Category id must be an existing, not deleted category.
     *
     * @param  array<int, int>  $activeIds
     */
    public static function isActiveCategory(mixed $value, array $activeIds): bool
    {
        if (is_string($value) && preg_match('/^[1-9][0-9]{0,18}$/', $value) === 1) {
            $value = (int) $value;
        }

        return is_int($value) && $value > 0 && in_array($value, $activeIds, true);
    }

    /**
     * Weather must be one of the configured keys (exact match, case-sensitive).
     *
     * @param  array<int, string>  $keys
     */
    public static function isWeatherKey(mixed $value, array $keys): bool
    {
        return is_string($value) && in_array($value, $keys, true);
    }

    /**
     * Diary date must be a real Y-m-d date.
     */
    public static function isDate(mixed $value): bool
    {
        if (! is_string($value) || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) !== 1) {
            return false;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    /**
     * Diary date must not be after today (both Y-m-d in the site time zone).
     */
    public static function isNotFuture(string $date, string $today): bool
    {
        return strcmp($date, $today) <= 0;
    }
}
