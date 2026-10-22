<?php

namespace Plugins\G7\Diary\Addon\Support;

/**
 * Decides whether the title must be filled automatically and what `title_auto` becomes.
 *
 * Pure (no framework calls) so the decision table can be unit-tested.
 *
 * Create:
 *   - blank title            → fill automatically, title_auto = 1
 *   - title written by user  → keep, title_auto = 0
 * Update:
 *   - title key absent       → keep title, title_auto unchanged (null)
 *   - title sent blank       → fill automatically, title_auto = 1
 *   - title sent, different  → keep the new title, title_auto = 0 (never auto-overwritten again)
 *   - title sent, identical  → keep, title_auto unchanged (null)
 */
class TitleAutoDecision
{
    /**
     * @return array{fill: bool, flag: int}
     */
    public static function forCreate(mixed $title): array
    {
        return self::isBlank($title)
            ? ['fill' => true, 'flag' => 1]
            : ['fill' => false, 'flag' => 0];
    }

    /**
     * @param  bool  $titleSent  Whether the request carried the `title` key.
     * @param  mixed  $newTitle  The value sent (when $titleSent).
     * @param  string  $currentTitle  Title stored before this update.
     * @return array{fill: bool, flag: int|null} flag null = leave title_auto as it is.
     */
    public static function forUpdate(bool $titleSent, mixed $newTitle, string $currentTitle): array
    {
        if (! $titleSent) {
            return ['fill' => false, 'flag' => null];
        }

        if (self::isBlank($newTitle)) {
            return ['fill' => true, 'flag' => 1];
        }

        if ((string) $newTitle !== $currentTitle) {
            return ['fill' => false, 'flag' => 0];
        }

        return ['fill' => false, 'flag' => null];
    }

    public static function isBlank(mixed $title): bool
    {
        if ($title === null) {
            return true;
        }

        if (! is_string($title)) {
            return false;
        }

        return AutoTitle::normalize($title) === '';
    }
}
