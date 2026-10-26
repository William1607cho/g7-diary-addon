<?php

namespace Plugins\G7\Diary\Addon\Support;

use Plugins\G7\Diary\Addon\Services\DiaryPostReader;

/**
 * Turns a filter plan ({@see DiaryCategoryFilter::plan()}, {@see DiaryDateFilter::step()})
 * into query builder calls on a query over posts. Used by the list filter listener
 * (sirsoft-board's Eloquent list query) and the photo gallery (query builder). Columns are
 * passed in qualified; no raw SQL.
 */
final class DiaryFilterSql
{
    /**
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     * @param  array<int, array<int, mixed>>  $plan
     * @param  array{id: string, is_secret: string, user_id: string, created_at: string}  $cols
     */
    public static function apply(mixed $query, array $plan, array $cols): void
    {
        $entries = static fn (string $op, string $column, mixed $value) => static fn ($sub) => $sub->select('post_id')->from(DiaryPostReader::ENTRIES)->{$op}($column, $value);

        foreach ($plan as [$step, $value]) {
            match ($step) {
                'in_category' => $query->whereIn($cols['id'], $entries('where', 'category_id', $value)),
                'not_in_categories' => $query->whereNotIn($cols['id'], $entries('whereIn', 'category_id', $value)),
                'secret_visible_to' => $query->where(static function ($q) use ($cols, $value): void {
                    $q->where($cols['is_secret'], false);
                    if ($value !== null) {
                        $q->orWhere($cols['user_id'], $value);
                    }
                }),
                'on_date' => $query->where(static function ($q) use ($cols, $value, $entries): void {
                    [$date, $from, $to] = $value;
                    $q->whereIn($cols['id'], $entries('where', 'diary_date', $date))
                        ->orWhere(static function ($w) use ($cols, $from, $to): void {
                            $w->whereNotIn($cols['id'], static fn ($sub) => $sub->select('post_id')->from(DiaryPostReader::ENTRIES))
                                ->where($cols['created_at'], '>=', $from)->where($cols['created_at'], '<', $to);
                        });
                }),
                default => null,
            };
        }
    }
}
