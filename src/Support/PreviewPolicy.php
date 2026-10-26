<?php

namespace Plugins\G7\Diary\Addon\Support;

/**
 * Allow-list of the list preview HTML (pure).
 *
 * The preview is rebuilt element by element from this list — anything not listed is
 * either unwrapped (its text kept) or dropped with its content. Attributes are dropped
 * unless listed for the tag, and addresses must be http(s) or site-relative.
 * The visitor page sanitizes once more (DOMPurify in the template's HtmlContent).
 */
final class PreviewPolicy
{
    /** Tags copied as they are (attributes per {@see self::ATTRIBUTES}). */
    public const KEEP = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'del', 'ins', 'mark', 'sub', 'sup',
        'a', 'ul', 'ol', 'li', 'blockquote', 'code', 'pre', 'hr',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
    ];

    /**
     * Tags removed together with everything inside them. Images are removed too — the card
     * shows them in its photo row instead (D64); a figure around an image is unwrapped and
     * keeps only its caption text.
     */
    public const DROP = [
        'script', 'style', 'noscript', 'template', 'object', 'embed', 'param', 'applet',
        'form', 'input', 'button', 'select', 'textarea', 'option', 'svg', 'math', 'canvas',
        'head', 'title', 'meta', 'link', 'base', 'frame', 'frameset', 'img', 'picture',
    ];

    /** Embeds replaced by one plain link line to their address. */
    public const EMBED = ['iframe', 'oembed', 'video', 'audio', 'source', 'track'];

    /** Block-level tags — each one counts as a block of the preview. */
    public const BLOCKS = [
        'p', 'ul', 'ol', 'blockquote', 'pre', 'hr', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'div', 'section', 'article', 'table',
    ];

    /** @var array<string, array<int, string>> */
    public const ATTRIBUTES = [
        'a' => ['href'],
    ];

    /** Attribute names that may carry an embed address, in order of preference. */
    public const EMBED_SOURCES = ['url', 'src', 'data-oembed-url'];

    /**
     * A link or embed address that may stay in the preview, or null.
     *
     * Allowed: `http://`, `https://`, site-relative `/…` (not `//…`), and for links `#…` and
     * `mailto:`. Addresses with control characters or whitespace are rejected.
     */
    public static function safeUrl(string $value, bool $forLink): ?string
    {
        $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($value === '' || preg_match('/[\x00-\x20\x7f]/', $value) === 1) {
            return null;
        }
        if (preg_match('#^https?://[^\s/]+#i', $value) === 1) {
            return $value;
        }
        if (str_starts_with($value, '/') && ! str_starts_with($value, '//')) {
            return $value;
        }
        if ($forLink && (str_starts_with($value, '#') || preg_match('/^mailto:[^\s]+$/i', $value) === 1)) {
            return $value;
        }

        return null;
    }

    public static function isKept(string $tag): bool
    {
        return in_array($tag, self::KEEP, true);
    }

    public static function isDropped(string $tag): bool
    {
        return in_array($tag, self::DROP, true);
    }

    public static function isEmbed(string $tag): bool
    {
        return in_array($tag, self::EMBED, true);
    }

    public static function isBlock(string $tag): bool
    {
        return in_array($tag, self::BLOCKS, true);
    }
}
