<?php

namespace Plugins\G7\Diary\Addon\Support;

/**
 * List card preview: the start of a post body, cut by DOM and rebuilt from an allow-list
 * (pure, for unit tests). D9 / D37.
 *
 * - The output is a fresh DOM built from {@see PreviewPolicy}; a cut inside an element
 *   still produces closed tags because the serializer closes them.
 * - Scripts, frames, objects and the like are removed with their content. Embeds
 *   (iframe, oembed, video, audio — and a figure holding one) become one plain link line.
 * - Links stay plain `<a href>`; the preview box never gets `.ck-content`, so the
 *   superpack does not turn them into link cards.
 * - Images are left out — the card shows them in its photo row ({@see PhotoStrip}, D64).
 */
final class PreviewHtml
{
    private const ROOT_ID = 'g7da-preview-root';

    /**
     * @return array{html: string, clipped: bool}
     */
    public static function build(string $content, ?string $contentMode, ?PreviewBudget $budget = null): array
    {
        $budget ??= new PreviewBudget;
        if (trim($content) === '') {
            return ['html' => '', 'clipped' => false];
        }

        $html = ($contentMode ?? 'text') === 'html'
            ? self::fromHtml($content, $budget)
            : self::fromText($content, $budget);

        return ['html' => $html, 'clipped' => $budget->clipped];
    }

    private static function fromText(string $content, PreviewBudget $budget): string
    {
        $out = '';
        foreach (preg_split('/\R/u', $content) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }
            if (! $budget->openBlock()) {
                break;
            }
            $out .= '<p>'.htmlspecialchars($budget->takeText($line), ENT_QUOTES | ENT_HTML5, 'UTF-8').'</p>';
            if ($budget->stopped) {
                break;
            }
        }

        return $out;
    }

    private static function fromHtml(string $content, PreviewBudget $budget): string
    {
        $source = self::parse($content);
        if ($source === null) {
            return self::fromText(strip_tags($content), $budget);
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $root = $doc->appendChild($doc->createElement('div'));
        (new PreviewCopier($doc, $budget))->copyChildren($source, $root, true);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out);
    }

    private static function parse(string $content): ?\DOMElement
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadHTML(
            '<?xml encoding="UTF-8"?><div id="'.self::ROOT_ID.'">'.$content.'</div>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? $doc->getElementById(self::ROOT_ID) : null;
    }
}
