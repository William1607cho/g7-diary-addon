<?php

namespace Plugins\G7\Diary\Addon\Support;

/**
 * Per-day figures of a diary board for the calendar screens (pure, for unit tests). D6.
 *
 * - `count`: every live post of the day (existence is public, secret posts included).
 * - `category`: the representative category — the one with the most posts that day;
 *   on a tie, the tied category whose latest post was written last. Secret posts the
 *   viewer cannot open do not take part (null when nothing visible is left).
 * - `emojis`: one per post in writing order — the category emoji, or a lock for a secret
 *   post the viewer cannot open.
 * A post without a category, or with a deleted / unknown one, counts for the default
 * category, as on the cards (D8).
 */
final class DayStats
{
    public const LOCK = '🔒';

    /**
     * @param  array<int, array{date: string, category_id: mixed, visible: bool, at: string, id: int}>  $posts
     * @param  array<int, array<string, mixed>>  $categoriesById  Active categories (public shape).
     * @return array<string, array{count: int, category: array<string, mixed>|null, emojis: array<int, string>, locked: int}>
     */
    public static function byDate(array $posts, array $categoriesById, ?int $defaultId): array
    {
        usort($posts, static fn (array $a, array $b): int => [$a['at'], $a['id']] <=> [$b['at'], $b['id']]);

        $days = [];
        foreach ($posts as $order => $post) {
            $day = &$days[$post['date']];
            $day ??= ['count' => 0, 'tally' => [], 'emojis' => [], 'locked' => 0];
            $day['count']++;
            if (! $post['visible']) {
                $day['emojis'][] = self::LOCK;
                $day['locked']++;

                continue;
            }
            $category = self::resolve($post['category_id'], $categoriesById, $defaultId);
            $day['emojis'][] = $category['emoji'] ?? '';
            if ($category !== null) {
                $id = (int) $category['id'];
                $day['tally'][$id] = [($day['tally'][$id][0] ?? 0) + 1, $order];
            }
        }
        unset($day);

        $out = [];
        foreach ($days as $date => $day) {
            $rep = self::representative($day['tally']);
            $out[$date] = [
                'count' => $day['count'],
                'category' => $rep === null ? null : $categoriesById[$rep],
                'emojis' => $day['emojis'],
                'locked' => $day['locked'],
            ];
        }

        return $out;
    }

    /**
     * Category id with the most posts; ties go to the one written last.
     *
     * @param  array<int, array{0: int, 1: int}>  $tally  id => [posts, order of its latest post]
     */
    public static function representative(array $tally): ?int
    {
        $best = null;
        foreach ($tally as $id => [$n, $last]) {
            if ($best === null || $n > $best[1] || ($n === $best[1] && $last > $best[2])) {
                $best = [$id, $n, $last];
            }
        }

        return $best[0] ?? null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $categoriesById
     * @return array<string, mixed>|null
     */
    private static function resolve(mixed $id, array $categoriesById, ?int $defaultId): ?array
    {
        $id = is_numeric($id) ? (int) $id : 0;

        return $categoriesById[$id] ?? ($defaultId !== null ? ($categoriesById[$defaultId] ?? null) : null);
    }
}
