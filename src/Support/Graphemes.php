<?php

namespace Plugins\G7\Diary\Addon\Support;

/**
 * Character counting by grapheme cluster (emoji sequences and composed Hangul stay whole).
 * Falls back to code points when intl is missing.
 */
final class Graphemes
{
    public static function length(string $text): int
    {
        if (function_exists('grapheme_strlen')) {
            $len = grapheme_strlen($text);
            if (is_int($len)) {
                return $len;
            }
        }

        return mb_strlen($text, 'UTF-8');
    }

    public static function substr(string $text, int $length): string
    {
        if ($length <= 0) {
            return '';
        }
        if (function_exists('grapheme_substr')) {
            $part = grapheme_substr($text, 0, $length);
            if (is_string($part)) {
                return $part;
            }
        }

        return mb_substr($text, 0, $length, 'UTF-8');
    }
}
