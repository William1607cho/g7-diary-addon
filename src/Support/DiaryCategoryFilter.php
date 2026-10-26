<?php

namespace Plugins\G7\Diary\Addon\Support;

/**
 * What the `diary_category` list filter adds to a list query, and its cache-key parts (pure).
 *
 * The plan is a list of steps that {@see \Plugins\G7\Diary\Addon\Listeners\DiaryListFilterListener}
 * turns into query builder calls:
 * - `['in_category', id]`: posts whose entry carries that category;
 * - `['not_in_categories', ids]`: the default category — posts that carry none of the other
 *   active categories (no category, deleted category, no entry row);
 * - `['secret_visible_to', viewerId|null]`: unless the viewer is a board manager, only
 *   public posts and the viewer's own secret posts (S1 / D56).
 */
final class DiaryCategoryFilter
{
    /**
     * @param  array<int, int>|null  $others  For the default category: the other active ids; null otherwise.
     * @return array<int, array<int, mixed>>
     */
    public static function plan(int $category, ?array $others, bool $boardWide, ?int $viewerId): array
    {
        $plan = [
            $others === null ? ['in_category', $category] : ['not_in_categories', $others === [] ? [0] : array_values($others)],
        ];
        if (! $boardWide) {
            // S1 / D6: belonging to a category list would reveal a secret post's category.
            // Keep public posts and the viewer's own; secret posts opened through a scoped
            // read-secret grant cannot be judged in SQL, so they are left out (closed side).
            $plan[] = ['secret_visible_to', $viewerId];
        }

        return $plan;
    }

    /**
     * The `$others` argument of {@see self::plan()}: for the default category, the other
     * active category ids; null for any other category.
     *
     * @param  array<int, int>  $activeIds
     * @return array<int, int>|null
     */
    public static function othersFor(int $category, ?int $defaultId, array $activeIds): ?array
    {
        return $category === $defaultId ? array_values(array_diff(array_map('intval', $activeIds), [$category])) : null;
    }

    /**
     * Count-cache parts: the category and who is looking (the filtered count differs per
     * viewer — board managers share one key, members get their own, guests one).
     *
     * @return array<int, string>
     */
    public static function cacheParts(int $category, bool $boardWide, ?int $viewerId): array
    {
        $viewer = $boardWide ? 'all' : ($viewerId === null ? 'guest' : 'u'.$viewerId);

        return ['g7da_cat:'.$category, 'g7da_v:'.$viewer];
    }
}
