<?php

namespace Plugins\G7\Diary\Addon\Support;

/**
 * Display meta of one diary post (pure, for unit tests). D8 / D6.
 *
 * Shape: `locked`, `date`, `weekday`, `date_label` ("10월 21일 (수)"), `date_long`
 * ("2026년 10월 21일 수요일"), `category` and `weather` (null when locked or absent).
 * A missing, null or deleted category shows as the default category.
 */
final class DiaryMeta
{
    private const WEEKDAYS = ['일', '월', '화', '수', '목', '금', '토'];

    /**
     * @param  array{category_id?: mixed, weather?: mixed, diary_date?: mixed}|null  $entry
     * @param  array<int, array<string, mixed>>  $categoriesById  Active categories (public shape, {@see self::category()}).
     * @param  array<int, array{key: string, emoji: string, label: string}>  $weatherOptions
     * @return array<string, mixed>
     */
    public static function present(?array $entry, string $fallbackDate, array $categoriesById, ?int $defaultId, array $weatherOptions, bool $locked): array
    {
        $date = is_string($entry['diary_date'] ?? null) && DiaryFieldRules::isDate(substr($entry['diary_date'], 0, 10))
            ? substr($entry['diary_date'], 0, 10)
            : $fallbackDate;

        $meta = ['locked' => $locked] + self::dateLabels($date);
        $meta['category'] = $locked ? null : self::pickCategory($entry['category_id'] ?? null, $categoriesById, $defaultId);
        $meta['weather'] = $locked ? null : self::pickWeather($entry['weather'] ?? null, $weatherOptions);

        return $meta;
    }

    /**
     * @return array{date: string, weekday: string, date_label: string, date_long: string}
     */
    public static function dateLabels(string $date): array
    {
        [$y, $m, $d] = array_map('intval', explode('-', $date) + [0, 0, 0]);
        $w = self::WEEKDAYS[(int) (new \DateTimeImmutable($date, new \DateTimeZone('UTC')))->format('w')];

        return [
            'date' => $date,
            'weekday' => $w,
            'date_label' => "{$m}월 {$d}일 ({$w})",
            'date_long' => "{$y}년 {$m}월 {$d}일 {$w}요일",
        ];
    }

    /**
     * Public shape of a category row from {@see DiaryCategories::listActive()}.
     *
     * @param  array<string, mixed>  $row
     * @return array{id: int, emoji: string, name: string, color: string, is_default: bool}
     */
    public static function category(array $row): array
    {
        $names = is_array($row['name'] ?? null) ? $row['name'] : [];
        $name = $names['ko'] ?? (reset($names) ?: '');

        return [
            'id' => (int) $row['id'],
            'emoji' => ($row['icon_type'] ?? 'emoji') === 'emoji' ? (string) ($row['icon_value'] ?? '') : '',
            'name' => (string) $name,
            'label' => self::label((string) $name),
            'color' => self::color((string) ($row['color'] ?? '')),
            'is_default' => (bool) ($row['is_default'] ?? false),
        ];
    }

    /**
     * Name for small boxes (D70): lines may break only at spaces, and a one-character word
     * must not end up alone on a line — the space next to a one-character first or last word
     * becomes a no-break space, so "개빡치는 날" stays on one line instead of leaving "날".
     */
    public static function label(string $name): string
    {
        $words = preg_split('/ +/u', trim($name)) ?: [];
        $n = count($words);
        if ($n < 2) {
            return trim($name);
        }
        $out = $words[0];
        for ($i = 1; $i < $n; $i++) {
            $glue = (Graphemes::length($words[$i - 1]) === 1 && $i === 1) || (Graphemes::length($words[$i]) === 1 && $i === $n - 1) ? "\u{00A0}" : ' ';
            $out .= $glue.$words[$i];
        }

        return $out;
    }

    /**
     * `#RRGGBB` or a neutral grey — the value is put into inline styles.
     */
    public static function color(string $value): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1 ? strtoupper($value) : '#9CA3AF';
    }

    /**
     * @param  array<int, array<string, mixed>>  $categoriesById
     * @return array<string, mixed>|null
     */
    private static function pickCategory(mixed $id, array $categoriesById, ?int $defaultId): ?array
    {
        $id = is_numeric($id) ? (int) $id : 0;
        if (isset($categoriesById[$id])) {
            return $categoriesById[$id];
        }

        return $defaultId !== null ? ($categoriesById[$defaultId] ?? null) : null;
    }

    /**
     * @param  array<int, array{key: string, emoji: string, label: string}>  $options
     * @return array{key: string, emoji: string, label: string}|null
     */
    private static function pickWeather(mixed $key, array $options): ?array
    {
        if (! is_string($key) || $key === '') {
            return null;
        }
        foreach ($options as $option) {
            if ($option['key'] === $key) {
                return $option;
            }
        }

        return null;
    }
}
