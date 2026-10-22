<?php

namespace Plugins\G7\Diary\Addon\Support;

/**
 * Builds the automatic title of a diary post from its body.
 *
 * Pure (no framework calls) so it can be unit-tested in isolation.
 *
 * Rule: take the first block of the body that has visible text (HTML tags and
 * entities removed, whitespace collapsed) and keep its first N characters. When the
 * text is longer than N, append "…". When no block has text (photo-only post,
 * embed-only post), use the configured photo-diary title instead.
 *
 * "Characters" are grapheme clusters, so an emoji sequence or a Hangul syllable is
 * never cut in half.
 */
class AutoTitle
{
    /** Column limit of board_posts.title. */
    public const TITLE_MAX = 200;

    /** Suffix appended when the text was cut. */
    public const ELLIPSIS = '…';

    /** Elements whose text never counts as a title. */
    private const SKIP_TAGS = ['script', 'style', 'iframe', 'oembed', 'noscript', 'template', 'svg', 'object', 'video', 'audio'];

    /** Elements that start a new block. */
    private const BLOCK_TAGS = [
        'p', 'div', 'section', 'article', 'header', 'footer', 'aside', 'blockquote', 'pre',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'ul', 'ol', 'dl', 'dt', 'dd',
        'table', 'thead', 'tbody', 'tr', 'td', 'th', 'figure', 'figcaption', 'hr', 'br',
    ];

    /**
     * @param  string  $content  Post body.
     * @param  string|null  $contentMode  'html' (default) or 'text'.
     * @param  int  $length  Characters to keep (clamped so the title fits the column).
     * @param  string  $photoTitle  Title used when the body has no text.
     */
    public static function build(string $content, ?string $contentMode, int $length, string $photoTitle): string
    {
        $length = max(1, min($length, self::TITLE_MAX - 1));

        foreach (self::blocks($content, $contentMode) as $block) {
            if ($block !== '') {
                return self::cut($block, $length);
            }
        }

        return self::cut(self::normalize($photoTitle), self::TITLE_MAX - 1);
    }

    /**
     * Visible text of each block, in document order (normalized; may contain '').
     *
     * @return array<int, string>
     */
    public static function blocks(string $content, ?string $contentMode): array
    {
        if ($contentMode === 'text') {
            $lines = preg_split('/\R/u', $content) ?: [];

            return array_map(self::normalize(...), $lines);
        }

        $text = self::htmlToLines($content);

        return array_map(self::normalize(...), explode("\n", $text));
    }

    /**
     * Cuts to $length graphemes and appends the ellipsis when anything was dropped.
     */
    public static function cut(string $text, int $length): string
    {
        if (self::graphemeLength($text) <= $length) {
            return $text;
        }

        return rtrim(self::graphemeSubstr($text, $length)).self::ELLIPSIS;
    }

    /**
     * Trims (including NBSP, zero-width and ideographic spaces) and collapses inner whitespace.
     */
    public static function normalize(string $text): string
    {
        $text = preg_replace('/[\x{00A0}\x{2000}-\x{200B}\x{202F}\x{205F}\x{3000}\x{FEFF}]/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * Walks the DOM and emits text with a newline at every block boundary.
     */
    private static function htmlToLines(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadHTML(
            '<?xml encoding="UTF-8"?><div id="g7da-root">'.$html.'</div>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return strip_tags($html);
        }

        $root = $doc->getElementById('g7da-root');
        if ($root === null) {
            return '';
        }

        $out = '';
        self::collect($root, $out);

        return $out;
    }

    private static function collect(\DOMNode $node, string &$out): void
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMText) {
                $out .= $child->nodeValue;

                continue;
            }

            if (! $child instanceof \DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);
            if (in_array($tag, self::SKIP_TAGS, true)) {
                continue;
            }

            $isBlock = in_array($tag, self::BLOCK_TAGS, true);
            if ($isBlock) {
                $out .= "\n";
            }
            self::collect($child, $out);
            if ($isBlock) {
                $out .= "\n";
            }
        }
    }

    private static function graphemeLength(string $text): int
    {
        if (function_exists('grapheme_strlen')) {
            $len = grapheme_strlen($text);
            if (is_int($len)) {
                return $len;
            }
        }

        return mb_strlen($text, 'UTF-8');
    }

    private static function graphemeSubstr(string $text, int $length): string
    {
        if (function_exists('grapheme_substr')) {
            $part = grapheme_substr($text, 0, $length);
            if (is_string($part)) {
                return $part;
            }
        }

        return mb_substr($text, 0, $length, 'UTF-8');
    }
}
