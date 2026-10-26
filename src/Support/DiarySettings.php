<?php

namespace Plugins\G7\Diary\Addon\Support;

/**
 * Diary add-on settings: defaults and a normalized reader.
 *
 * Plugin settings are admin input, so readers never use raw values — they go through
 * {@see self::normalize()}, which falls back to the default for anything out of range.
 * `config/settings/defaults.json` mirrors {@see self::DEFAULTS} (a unit test keeps them equal).
 */
class DiarySettings
{
    public const IDENTIFIER = 'g7-diary-addon';

    public const MODE_PAGINATE = 'paginate';

    public const MODE_INFINITE = 'infinite';

    /** @var array<int, string> */
    public const MODES = [self::MODE_PAGINATE, self::MODE_INFINITE];

    /** Weather keys: lowercase ASCII, fixed; the label is separate. */
    public const WEATHER_KEY_PATTERN = '/^[a-z][a-z0-9_]{0,31}$/';

    public const DEFAULTS = [
        'weather_options' => [
            ['key' => 'sunny', 'emoji' => '☀️', 'label' => '맑음'],
            ['key' => 'partly_cloudy', 'emoji' => '🌤️', 'label' => '구름 조금'],
            ['key' => 'cloudy', 'emoji' => '☁️', 'label' => '흐림'],
            ['key' => 'rain', 'emoji' => '🌧️', 'label' => '비'],
            ['key' => 'thunderstorm', 'emoji' => '⛈️', 'label' => '천둥번개'],
            ['key' => 'snow', 'emoji' => '❄️', 'label' => '눈'],
        ],
        'auto_title_length' => 30,
        'photo_diary_title' => '(사진 일기)',
        'memories_empty_text' => '힘내서 일기 좀 더 써 주세요',
        'list_display_mode' => self::MODE_PAGINATE,
        'list_page_size' => 0,
        'photo_display_mode' => self::MODE_INFINITE,
        'photo_page_size' => 30,
    ];

    /** Posts per list load; 0 = the board's own list size. */
    private const LIST_PAGE_MAX = 100;

    private const AUTO_TITLE_MIN = 5;

    private const AUTO_TITLE_MAX = 100;

    private const PHOTO_PAGE_MIN = 6;

    private const PHOTO_PAGE_MAX = 120;

    private const TEXT_MAX = 100;

    /**
     * Normalized current settings.
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        $raw = function_exists('plugin_settings') ? plugin_settings(self::IDENTIFIER) : [];

        return self::normalize(is_array($raw) ? $raw : []);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public static function normalize(array $raw): array
    {
        $d = self::DEFAULTS;

        return [
            'weather_options' => self::weatherOptions($raw['weather_options'] ?? null),
            'auto_title_length' => self::intIn($raw['auto_title_length'] ?? null, self::AUTO_TITLE_MIN, self::AUTO_TITLE_MAX, $d['auto_title_length']),
            'photo_diary_title' => self::text($raw['photo_diary_title'] ?? null, $d['photo_diary_title']),
            'memories_empty_text' => self::text($raw['memories_empty_text'] ?? null, $d['memories_empty_text']),
            'list_display_mode' => self::mode($raw['list_display_mode'] ?? null, $d['list_display_mode']),
            'list_page_size' => self::intIn($raw['list_page_size'] ?? null, 0, self::LIST_PAGE_MAX, $d['list_page_size']),
            'photo_display_mode' => self::mode($raw['photo_display_mode'] ?? null, $d['photo_display_mode']),
            'photo_page_size' => self::intIn($raw['photo_page_size'] ?? null, self::PHOTO_PAGE_MIN, self::PHOTO_PAGE_MAX, $d['photo_page_size']),
        ];
    }

    /**
     * Allowed weather keys.
     *
     * @param  array<string, mixed>|null  $settings  Normalized settings (read when null).
     * @return array<int, string>
     */
    public static function weatherKeys(?array $settings = null): array
    {
        $settings ??= self::all();

        return array_values(array_map(static fn (array $w): string => $w['key'], $settings['weather_options']));
    }

    /**
     * Valid weather entries; an empty or fully invalid list falls back to the defaults.
     *
     * @return array<int, array{key: string, emoji: string, label: string}>
     */
    private static function weatherOptions(mixed $value): array
    {
        if (! is_array($value)) {
            return self::DEFAULTS['weather_options'];
        }

        $out = [];
        $seen = [];
        foreach ($value as $item) {
            if (! is_array($item)) {
                continue;
            }
            $key = $item['key'] ?? null;
            if (! is_string($key) || preg_match(self::WEATHER_KEY_PATTERN, $key) !== 1 || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = [
                'key' => $key,
                'emoji' => self::text($item['emoji'] ?? null, ''),
                'label' => self::text($item['label'] ?? null, $key),
            ];
        }

        return $out === [] ? self::DEFAULTS['weather_options'] : $out;
    }

    private static function intIn(mixed $value, int $min, int $max, int $default): int
    {
        if (is_string($value) && preg_match('/^\d{1,4}$/', $value) === 1) {
            $value = (int) $value;
        }

        return is_int($value) && $value >= $min && $value <= $max ? $value : $default;
    }

    private static function mode(mixed $value, string $default): string
    {
        return is_string($value) && in_array($value, self::MODES, true) ? $value : $default;
    }

    /**
     * Single-line text; layout expression delimiters removed (values reach layout props later).
     */
    private static function text(mixed $value, string $default): string
    {
        if (! is_string($value)) {
            return $default;
        }

        $value = str_replace(['{{', '}}'], '', $value);
        $value = preg_replace('/[\x00-\x1f]+/', ' ', $value) ?? '';
        $value = trim($value);

        return $value === '' ? $default : mb_substr($value, 0, self::TEXT_MAX);
    }
}
