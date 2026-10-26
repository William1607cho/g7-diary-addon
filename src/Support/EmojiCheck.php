<?php

namespace Plugins\G7\Diary\Addon\Support;

/**
 * "Exactly one emoji" check for category and weather icons (pure, D103).
 *
 * One grapheme cluster (so flags, skin tones, ZWJ families and keycaps count as one) that
 * carries an emoji: an Extended_Pictographic code point, a regional indicator pair (flag)
 * or the keycap mark U+20E3. Letters, digits and two emoji are refused. The admin screen
 * applies the same rule with `Intl.Segmenter` (dist/js/admin-state.js).
 */
final class EmojiCheck
{
    private const EMOJI = '/\p{Extended_Pictographic}|\p{Regional_Indicator}|\x{20E3}/u';

    /** Longest accepted value in bytes (a ZWJ family of four with skin tones stays well below). */
    private const MAX_BYTES = 64;

    public static function isSingle(mixed $value): bool
    {
        if (! is_string($value) || $value === '' || strlen($value) > self::MAX_BYTES || trim($value) !== $value) {
            return false;
        }
        if (preg_match('//u', $value) !== 1) {
            return false;
        }

        return Graphemes::length($value) === 1 && preg_match(self::EMOJI, $value) === 1;
    }
}
