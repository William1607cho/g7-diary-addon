<?php

namespace Plugins\G7\Diary\Addon\Support;

/**
 * "Today" in the site time zone.
 *
 * The time zone is the core general setting `general.timezone`, read through the core
 * helper `g7_core_settings()` — the same source the core uses for analytics day
 * boundaries (`App\Analytics\AnalyticsClock::siteTimezone()`). The value is never
 * hard-coded here. When the setting is empty or not a valid zone, the app time zone
 * (`config('app.timezone')`, UTC on G7) is used, as the core does.
 */
class SiteClock
{
    /** Core settings key of the site time zone. */
    public const SETTING_KEY = 'general.timezone';

    /**
     * Site time zone name.
     */
    public static function timezone(): string
    {
        $tz = function_exists('g7_core_settings') ? g7_core_settings(self::SETTING_KEY) : null;
        if (is_string($tz) && self::isValidZone($tz)) {
            return $tz;
        }

        $fallback = function_exists('config') ? config('app.timezone', 'UTC') : 'UTC';

        return is_string($fallback) && self::isValidZone($fallback) ? $fallback : 'UTC';
    }

    /**
     * Today's date (Y-m-d) in the site time zone.
     */
    public static function today(): string
    {
        return self::todayIn(self::timezone(), new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
    }

    /**
     * Today's date (Y-m-d) in a given zone at a given instant (pure, for tests).
     */
    public static function todayIn(string $timezone, \DateTimeInterface $now): string
    {
        $zone = new \DateTimeZone(self::isValidZone($timezone) ? $timezone : 'UTC');

        return \DateTimeImmutable::createFromInterface($now)->setTimezone($zone)->format('Y-m-d');
    }

    /**
     * Local date (Y-m-d) of an instant in the site time zone.
     */
    public static function dateOf(\DateTimeInterface $instant): string
    {
        return self::todayIn(self::timezone(), $instant);
    }

    public static function isValidZone(string $timezone): bool
    {
        return in_array($timezone, \DateTimeZone::listIdentifiers(), true);
    }
}
