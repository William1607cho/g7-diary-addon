<?php

namespace Plugins\G7\Diary\Addon\Support;

/**
 * Weather list saved from the admin screen: checks and fixed keys (pure, D106).
 *
 * Posts store the weather key, so a key never changes once given: an item that comes back
 * with its key keeps it (renaming or a new emoji does not touch it), the six default keys
 * stay as they are, and a new item (empty key) gets `w_` plus six random lowercase letters
 * or digits. An empty list is a valid choice — no weather (the write form hides the row).
 */
final class WeatherKeys
{
    public const MAX_ITEMS = 20;

    public const LABEL_MAX = 20;

    private const ALPHABET = 'abcdefghijklmnopqrstuvwxyz0123456789';

    /**
     * @param  mixed  $items  Request value of `weather_options`.
     * @param  (callable(int): int)|null  $random  Random index source in [0, $max] (tests pass a fixed one).
     * @return array{items: array<int, array{key: string, emoji: string, label: string}>, errors: array<string, string>}
     */
    public static function assign(mixed $items, ?callable $random = null): array
    {
        if (! is_array($items) || ! array_is_list($items)) {
            return ['items' => [], 'errors' => ['weather_options' => 'weather_list']];
        }
        if (count($items) > self::MAX_ITEMS) {
            return ['items' => [], 'errors' => ['weather_options' => 'weather_too_many']];
        }

        $errors = [];
        $out = [];
        $taken = [];
        foreach ($items as $i => $item) {
            $item = is_array($item) ? $item : [];
            $key = is_string($item['key'] ?? null) ? $item['key'] : '';
            if ($key !== '' && (preg_match(DiarySettings::WEATHER_KEY_PATTERN, $key) !== 1 || isset($taken[$key]))) {
                $errors["weather_options.{$i}.key"] = 'weather_key';
            }
            $taken[$key] = true;
            $emoji = is_string($item['emoji'] ?? null) ? $item['emoji'] : '';
            if (! EmojiCheck::isSingle($emoji)) {
                $errors["weather_options.{$i}.emoji"] = 'emoji_single';
            }
            $label = CategoryRules::cleanName($item['label'] ?? null);
            $len = Graphemes::length($label);
            if ($len < 1 || $len > self::LABEL_MAX || preg_match('/[\x00-\x1f\x7f]|\{\{|\}\}/', $label) === 1) {
                $errors["weather_options.{$i}.label"] = 'name_length';
            }
            $out[] = ['key' => $key, 'emoji' => $emoji, 'label' => $label];
        }
        if ($errors !== []) {
            return ['items' => [], 'errors' => $errors];
        }

        foreach ($out as $i => $item) {
            if ($item['key'] === '') {
                $out[$i]['key'] = self::newKey($taken, $random);
                $taken[$out[$i]['key']] = true;
            }
        }

        return ['items' => $out, 'errors' => []];
    }

    /**
     * Settings about to be saved: the weather list gets its keys (validation already ran, so
     * a list that still fails is dropped and the stored one stays). Other keys are untouched.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function forSave(array $settings, ?callable $random = null): array
    {
        if (! array_key_exists('weather_options', $settings)) {
            return $settings;
        }
        $assigned = self::assign(self::withoutExtras($settings['weather_options']), $random);
        if ($assigned['errors'] === []) {
            $settings['weather_options'] = $assigned['items'];
        } else {
            unset($settings['weather_options']);
        }

        return $settings;
    }

    /**
     * The screen sends items as it shows them (with `uid`, `posts`); only key, emoji and label are kept.
     */
    public static function withoutExtras(mixed $items): mixed
    {
        if (! is_array($items)) {
            return $items;
        }

        return array_map(static fn ($w): mixed => is_array($w) ? array_intersect_key($w, ['key' => 1, 'emoji' => 1, 'label' => 1]) : $w, $items);
    }

    /**
     * A key that is not taken yet: `w_` + 6 characters of [a-z0-9].
     *
     * @param  array<string, bool>  $taken
     * @param  (callable(int): int)|null  $random
     */
    public static function newKey(array $taken, ?callable $random = null): string
    {
        $random ??= static fn (int $max): int => random_int(0, $max);
        $max = strlen(self::ALPHABET) - 1;
        for ($try = 0; $try < 50; $try++) {
            $key = 'w_';
            for ($i = 0; $i < 6; $i++) {
                $key .= self::ALPHABET[max(0, min($max, $random($max)))];
            }
            if (! isset($taken[$key])) {
                return $key;
            }
        }

        throw new \RuntimeException('Could not make a free weather key');
    }
}
