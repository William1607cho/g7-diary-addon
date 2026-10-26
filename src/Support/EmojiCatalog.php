<?php

namespace Plugins\G7\Diary\Addon\Support;

/**
 * Emoji grid of the admin screen, read from `resources/admin/emoji.json` (D108).
 *
 * Only the admin screen API hands it out, so visitor pages never carry it.
 */
final class EmojiCatalog
{
    /** Group order of the picker; the weather picker opens on `nature` first. */
    public const GROUPS = ['faces', 'people', 'food', 'travel', 'nature', 'objects'];

    /**
     * @return array<int, array{key: string, emojis: array<int, string>}>
     */
    public static function groups(?string $file = null): array
    {
        $file ??= dirname(__DIR__, 2).'/resources/admin/emoji.json';
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        $byKey = [];
        foreach (is_array($data['groups'] ?? null) ? $data['groups'] : [] as $g) {
            if (is_array($g) && is_string($g['key'] ?? null) && is_array($g['emojis'] ?? null)) {
                $byKey[$g['key']] = array_values(array_filter($g['emojis'], [EmojiCheck::class, 'isSingle']));
            }
        }

        $out = [];
        foreach (self::GROUPS as $key) {
            $out[] = ['key' => $key, 'emojis' => $byKey[$key] ?? []];
        }

        return $out;
    }
}
