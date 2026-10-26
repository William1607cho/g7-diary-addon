<?php

namespace Plugins\G7\Diary\Addon\Support;

/**
 * Checks of the category batch saved on the admin screen (pure, D99 / D100).
 *
 * A batch is the whole list in display order: existing rows carry their id, new rows have
 * none. Active ids that are missing from the batch are soft-deleted. Errors are message
 * codes keyed by the request field (`categories.2.name`), translated by the controller;
 * the admin screen applies the same rules before it sends (dist/js/admin-state.js).
 */
final class CategoryRules
{
    public const MAX_ROWS = 30;

    public const NAME_MAX = 20;

    private const COLOR = '/^#[0-9A-Fa-f]{6}$/';

    /**
     * @param  mixed  $rows  Request value of `categories`.
     * @param  array<int, int>  $activeIds  Ids that exist and are not deleted.
     * @return array{rows: array<int, array{id: int|null, emoji: string, name: string, color: string, is_default: bool}>, errors: array<string, string>}
     */
    public static function check(mixed $rows, array $activeIds): array
    {
        if (! is_array($rows) || ! array_is_list($rows) || $rows === []) {
            return ['rows' => [], 'errors' => ['categories' => 'categories_required']];
        }
        if (count($rows) > self::MAX_ROWS) {
            return ['rows' => [], 'errors' => ['categories' => 'categories_too_many']];
        }

        $out = [];
        $errors = [];
        $seenIds = [];
        $seenNames = [];
        foreach ($rows as $i => $row) {
            $row = is_array($row) ? $row : [];
            $clean = self::row($row);
            $errors += self::rowErrors($i, $row, $clean, $activeIds, $seenIds, $seenNames);
            $out[] = $clean;
        }

        $defaults = count(array_filter($out, static fn (array $r): bool => $r['is_default']));
        if ($defaults !== 1) {
            $errors['categories'] = $defaults === 0 ? 'default_missing' : 'default_many';
        }

        return ['rows' => $errors === [] ? $out : [], 'errors' => $errors];
    }

    /**
     * Why a single category may not be deleted, or null when it may.
     *
     * @param  array<int, int>  $activeIds
     */
    public static function deleteError(int $id, array $activeIds, ?int $defaultId): ?string
    {
        if (! in_array($id, $activeIds, true)) {
            return 'category_unknown';
        }
        if ($id === $defaultId) {
            return 'delete_default';
        }

        return count($activeIds) <= 1 ? 'delete_last' : null;
    }

    /**
     * Name as stored: white space removed at both ends (Unicode spaces included).
     */
    public static function cleanName(mixed $value): string
    {
        if (! is_string($value) || preg_match('//u', $value) !== 1) {
            return '';
        }

        return preg_replace('/^\s+|\s+$/u', '', $value) ?? '';
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{id: int|null, emoji: string, name: string, color: string, is_default: bool}
     */
    private static function row(array $row): array
    {
        $id = $row['id'] ?? null;
        if (is_string($id) && preg_match('/^[1-9][0-9]{0,9}$/', $id) === 1) {
            $id = (int) $id;
        }

        return [
            'id' => is_int($id) && $id > 0 ? $id : null,
            'emoji' => is_string($row['emoji'] ?? null) ? $row['emoji'] : '',
            'name' => self::cleanName($row['name'] ?? null),
            'color' => is_string($row['color'] ?? null) ? strtoupper($row['color']) : '',
            'is_default' => ($row['is_default'] ?? false) === true || ($row['is_default'] ?? null) === 1 || ($row['is_default'] ?? null) === '1',
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  array{id: int|null, emoji: string, name: string, color: string, is_default: bool}  $clean
     * @param  array<int, int>  $activeIds
     * @param  array<int, bool>  $seenIds
     * @param  array<string, bool>  $seenNames
     * @return array<string, string>
     */
    private static function rowErrors(int $i, array $raw, array $clean, array $activeIds, array &$seenIds, array &$seenNames): array
    {
        $errors = [];
        $hasId = array_key_exists('id', $raw) && $raw['id'] !== null && $raw['id'] !== '';
        if ($hasId && ($clean['id'] === null || ! in_array($clean['id'], $activeIds, true) || isset($seenIds[$clean['id']]))) {
            $errors["categories.{$i}.id"] = 'category_unknown';
        }
        if ($clean['id'] !== null) {
            $seenIds[$clean['id']] = true;
        }
        if (! EmojiCheck::isSingle($clean['emoji'])) {
            $errors["categories.{$i}.emoji"] = 'emoji_single';
        }

        $len = Graphemes::length($clean['name']);
        $key = mb_strtolower($clean['name'], 'UTF-8');
        // `{{ }}` is refused like in the text settings: names end up in layout props.
        if ($len < 1 || $len > self::NAME_MAX || preg_match('/[\x00-\x1f\x7f]|\{\{|\}\}/', $clean['name']) === 1) {
            $errors["categories.{$i}.name"] = 'name_length';
        } elseif (isset($seenNames[$key])) {
            $errors["categories.{$i}.name"] = 'name_duplicate';
        }
        $seenNames[$key] = true;

        if (preg_match(self::COLOR, $clean['color']) !== 1) {
            $errors["categories.{$i}.color"] = 'color_hex';
        }

        return $errors;
    }
}
