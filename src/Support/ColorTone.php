<?php

namespace Plugins\G7\Diary\Addon\Support;

/**
 * How a category colour is shown (pure, D104): the "light tint + dark text" rule.
 *
 * The visitor screens draw a category's background as its colour at alpha 0x29 (about 16%)
 * over the card (white, dark mode #1F2937) and keep the theme's text colour on it; calendar
 * cells use 0x29 / 0x33. Because the tint is at most a fifth of the colour, any `#RRGGBB`
 * keeps the theme text readable — the unit test checks the extremes and the swatches at
 * 4.5:1 or more. The admin screen previews each row with {@see self::preview()}.
 */
final class ColorTone
{
    public const LIGHT_BASE = '#FFFFFF';

    public const DARK_BASE = '#1F2937';

    public const LIGHT_TEXT = '#111827';

    public const DARK_TEXT = '#F9FAFB';

    /** Strongest tint the visitor screens use (0x33 on the panel calendar). */
    public const ALPHA = 0x33;

    /** Swatches offered on the admin screen: the seven default colours, then grey and teal. */
    public const SWATCHES = ['#EAB308', '#3B82F6', '#EF4444', '#22C55E', '#A855F7', '#F97316', '#EC4899', '#6B7280', '#14B8A6'];

    /**
     * @return array{bg: string, fg: string, bg_dark: string, fg_dark: string, ratio: float, ratio_dark: float}
     */
    public static function preview(string $hex, int $alpha = self::ALPHA): array
    {
        $color = DiaryMeta::color($hex);
        $bg = self::mix($color, self::LIGHT_BASE, $alpha / 255);
        $bgDark = self::mix($color, self::DARK_BASE, $alpha / 255);

        return [
            'bg' => $bg,
            'fg' => self::LIGHT_TEXT,
            'bg_dark' => $bgDark,
            'fg_dark' => self::DARK_TEXT,
            'ratio' => round(self::contrast(self::LIGHT_TEXT, $bg), 2),
            'ratio_dark' => round(self::contrast(self::DARK_TEXT, $bgDark), 2),
        ];
    }

    /**
     * `$top` laid over `$base` with the given opacity, as `#RRGGBB`.
     */
    public static function mix(string $top, string $base, float $opacity): string
    {
        $a = self::rgb($top);
        $b = self::rgb($base);
        $out = '#';
        for ($i = 0; $i < 3; $i++) {
            $out .= sprintf('%02X', (int) round($a[$i] * $opacity + $b[$i] * (1 - $opacity)));
        }

        return $out;
    }

    /**
     * WCAG contrast ratio of two `#RRGGBB` colours (1 to 21).
     */
    public static function contrast(string $x, string $y): float
    {
        $l1 = self::luminance($x);
        $l2 = self::luminance($y);

        return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
    }

    public static function luminance(string $hex): float
    {
        $c = array_map(static function (int $v): float {
            $s = $v / 255;

            return $s <= 0.03928 ? $s / 12.92 : (($s + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));

        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private static function rgb(string $hex): array
    {
        $hex = ltrim(DiaryMeta::color($hex), '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
