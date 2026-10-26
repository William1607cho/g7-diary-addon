<?php

namespace Plugins\G7\Diary\Addon\Support;

/**
 * Posts per category for the side panel (pure, for unit tests). D6.
 *
 * Input rows are only the posts the viewer may see the category of (secret posts the
 * viewer cannot open are left out before this). A post without a category, or whose
 * category was deleted or is unknown, counts for the default category — the same rule
 * the cards use for display.
 */
final class CategoryCounts
{
    /**
     * @param  iterable<int, int|string|null>  $categoryIds  One entry per visible post.
     * @param  array<int, array<string, mixed>>  $categories  Active categories in order (with `id`, `is_default`).
     * @return array<int, array<string, mixed>> The categories with a `count` key added.
     */
    public static function count(iterable $categoryIds, array $categories): array
    {
        $defaultId = self::defaultId($categories);
        $active = [];
        foreach ($categories as $c) {
            $active[(int) $c['id']] = 0;
        }

        foreach ($categoryIds as $id) {
            $id = is_numeric($id) ? (int) $id : 0;
            $key = isset($active[$id]) ? $id : $defaultId;
            if ($key !== null) {
                $active[$key]++;
            }
        }

        $out = [];
        foreach ($categories as $c) {
            $c['count'] = $active[(int) $c['id']];
            $out[] = $c;
        }

        return $out;
    }

    /**
     * Id of the default category (the first flagged one; else the first active one).
     *
     * @param  array<int, array<string, mixed>>  $categories
     */
    public static function defaultId(array $categories): ?int
    {
        foreach ($categories as $c) {
            if (! empty($c['is_default'])) {
                return (int) $c['id'];
            }
        }

        return isset($categories[0]['id']) ? (int) $categories[0]['id'] : null;
    }
}
