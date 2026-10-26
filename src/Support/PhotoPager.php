<?php

namespace Plugins\G7\Diary\Addon\Support;

/**
 * One page of the photo gallery from the ordered cell list (pure, for unit tests). D15.
 *
 * Cells come newest diary date first. The cursor is a month (`YYYY-MM`, start of that month,
 * or of the first older month that has photos) with an optional offset inside the month
 * (`YYYY-MM.N`) — so a page can start in the middle of a month. Each page is grouped by
 * month; a month cut by a page boundary gets its heading again at the top of the next page,
 * and every heading carries the month's full count.
 */
final class PhotoPager
{
    private const CURSOR = '/^((?:19|2[0-9])[0-9]{2}-(?:0[1-9]|1[0-2]))(?:\.([1-9][0-9]{0,5}))?$/D';

    /**
     * @param  array<int, array<string, mixed>>  $cells  Each with `month` (YYYY-MM), newest first.
     * @return array{groups: array<int, array{month: string, label: string, total: int, cells: array<int, array<string, mixed>>}>, next_cursor: string|null, prev_cursor: string|null, has_more: bool, page: int, pages: int, total: int}
     */
    public static function page(array $cells, ?string $cursor, int $size): array
    {
        $cells = array_values($cells);
        $size = max(1, $size);
        $n = count($cells);
        [$firstOf, $totals] = self::monthIndex($cells);
        $start = self::start($cursor, $cells, $firstOf);

        $groups = [];
        foreach (array_slice($cells, $start, $size) as $cell) {
            $last = array_key_last($groups);
            if ($last === null || $groups[$last]['month'] !== $cell['month']) {
                $groups[] = ['month' => $cell['month'], 'label' => self::monthLabel($cell['month']), 'total' => $totals[$cell['month']], 'cells' => []];
                $last = array_key_last($groups);
            }
            $groups[$last]['cells'][] = $cell;
        }

        $end = $start + $size;

        return [
            'groups' => $groups,
            'next_cursor' => $end < $n ? self::cursorAt($cells, $firstOf, $end) : null,
            'prev_cursor' => $start > 0 ? self::cursorAt($cells, $firstOf, max(0, $start - $size)) : null,
            'has_more' => $end < $n,
            'page' => intdiv($start, $size) + 1,
            'pages' => max(1, (int) ceil($n / $size)),
            'total' => $n,
        ];
    }

    public static function monthLabel(string $month): string
    {
        return ((int) substr($month, 0, 4)).'년 '.((int) substr($month, 5, 2)).'월';
    }

    /**
     * @param  array<int, array<string, mixed>>  $cells
     * @return array{0: array<string, int>, 1: array<string, int>} first index and count per month
     */
    private static function monthIndex(array $cells): array
    {
        $first = [];
        $totals = [];
        foreach ($cells as $i => $cell) {
            $first[$cell['month']] ??= $i;
            $totals[$cell['month']] = ($totals[$cell['month']] ?? 0) + 1;
        }

        return [$first, $totals];
    }

    /**
     * @param  array<int, array<string, mixed>>  $cells
     * @param  array<string, int>  $firstOf
     */
    private static function start(?string $cursor, array $cells, array $firstOf): int
    {
        if ($cursor === null || preg_match(self::CURSOR, $cursor, $m) !== 1) {
            return 0;
        }
        $offset = isset($m[2]) ? (int) $m[2] : 0;
        if (isset($firstOf[$m[1]])) {
            return min($firstOf[$m[1]] + $offset, count($cells));
        }
        foreach ($cells as $i => $cell) {
            if ($cell['month'] < $m[1]) {
                return $i;
            }
        }

        return count($cells);
    }

    /**
     * @param  array<int, array<string, mixed>>  $cells
     * @param  array<string, int>  $firstOf
     */
    private static function cursorAt(array $cells, array $firstOf, int $index): string
    {
        $month = $cells[$index]['month'];
        $offset = $index - $firstOf[$month];

        return $offset === 0 ? $month : $month.'.'.$offset;
    }
}
