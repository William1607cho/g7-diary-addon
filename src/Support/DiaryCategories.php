<?php

namespace Plugins\G7\Diary\Addon\Support;

use Illuminate\Support\Facades\DB;

/**
 * Diary categories table access (fixed ids, soft delete).
 */
class DiaryCategories
{
    /** Table name before the DB prefix (physical: g7_g7_diary_addon_categories). */
    public const TABLE = 'g7_diary_addon_categories';

    /**
     * Default categories. Ids are fixed so posts keep their category across reinstalls.
     *
     * @var array<int, array{id: int, icon: string, name: string, color: string, default: bool}>
     */
    public const DEFAULTS = [
        ['id' => 1, 'icon' => '😊', 'name' => '행복일기', 'color' => '#EAB308', 'default' => false],
        ['id' => 2, 'icon' => '😢', 'name' => '우울일기', 'color' => '#3B82F6', 'default' => false],
        ['id' => 3, 'icon' => '😡', 'name' => '개빡치는 날', 'color' => '#EF4444', 'default' => false],
        ['id' => 4, 'icon' => '😌', 'name' => '평온한 하루', 'color' => '#22C55E', 'default' => true],
        ['id' => 5, 'icon' => '🎉', 'name' => '스페샬 이벤트', 'color' => '#A855F7', 'default' => false],
        ['id' => 6, 'icon' => '🍜', 'name' => '맛집탐방', 'color' => '#F97316', 'default' => false],
        ['id' => 7, 'icon' => '📸', 'name' => '포토스팟', 'color' => '#EC4899', 'default' => false],
    ];

    /**
     * Inserts the default categories that are missing. Existing rows (even edited or
     * soft-deleted ones) are never overwritten.
     *
     * @return int Rows inserted.
     */
    public static function ensureDefaults(): int
    {
        if (! DB::getSchemaBuilder()->hasTable(self::TABLE)) {
            return 0;
        }

        $now = now();
        $rows = [];
        foreach (self::DEFAULTS as $i => $c) {
            $rows[] = [
                'id' => $c['id'],
                'name' => json_encode(['ko' => $c['name']], JSON_UNESCAPED_UNICODE),
                'icon_type' => 'emoji',
                'icon_value' => $c['icon'],
                'color' => $c['color'],
                'sort_order' => $i + 1,
                'is_default' => $c['default'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return DB::table(self::TABLE)->insertOrIgnore($rows);
    }

    /**
     * Ids of categories that can be chosen (not deleted).
     *
     * @return array<int, int>
     */
    public static function activeIds(): array
    {
        return DB::table(self::TABLE)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Categories for display, in order.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listActive(): array
    {
        return DB::table(self::TABLE)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name', 'icon_type', 'icon_value', 'color', 'sort_order', 'is_default'])
            ->map(static function ($row): array {
                $name = json_decode((string) $row->name, true);

                return [
                    'id' => (int) $row->id,
                    'name' => is_array($name) ? $name : [],
                    'icon_type' => (string) $row->icon_type,
                    'icon_value' => (string) $row->icon_value,
                    'color' => (string) $row->color,
                    'sort_order' => (int) $row->sort_order,
                    'is_default' => (bool) $row->is_default,
                ];
            })
            ->all();
    }
}
