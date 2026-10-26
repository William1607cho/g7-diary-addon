<?php

namespace Plugins\G7\Diary\Addon\Support;

/**
 * Copies the allowed part of a parsed body into the preview document ({@see PreviewHtml}).
 */
final class PreviewCopier
{
    public function __construct(
        private readonly \DOMDocument $doc,
        private readonly PreviewBudget $budget,
    ) {}

    public function copyChildren(\DOMNode $from, \DOMNode $to, bool $topLevel): void
    {
        foreach ($from->childNodes as $child) {
            if ($this->budget->stopped) {
                return;
            }
            if ($child instanceof \DOMText) {
                $this->copyText($child, $to, $topLevel);
            } elseif ($child instanceof \DOMElement) {
                $this->copyElement($child, $to, $topLevel);
            }
        }
    }

    private function copyText(\DOMText $text, \DOMNode $to, bool $topLevel): void
    {
        $value = (string) $text->nodeValue;
        if ($topLevel && trim($value) !== '' && ! $this->budget->openBlock()) {
            return;
        }
        $kept = $this->budget->takeText($value);
        if ($kept !== '') {
            $to->appendChild($this->doc->createTextNode($kept));
        }
    }

    private function copyElement(\DOMElement $el, \DOMNode $to, bool $topLevel): void
    {
        $tag = strtolower($el->tagName);
        if (PreviewPolicy::isDropped($tag)) {
            return;
        }

        $embed = $this->embedSource($el, $tag);
        if ($embed !== false) {
            $this->appendEmbedLink($embed, $to, $topLevel);

            return;
        }

        if ($topLevel && PreviewPolicy::isBlock($tag) && ! $this->budget->openBlock()) {
            return;
        }

        if (! PreviewPolicy::isKept($tag) || ($tag === 'a' && $this->safeHref($el) === null)) {
            // Unknown or unsafe wrapper: keep its content, drop the element itself.
            $this->copyChildren($el, $to, $topLevel && ! PreviewPolicy::isBlock($tag));

            return;
        }

        $copy = $this->doc->createElement($tag);
        if ($tag === 'a') {
            $copy->setAttribute('href', (string) $this->safeHref($el));
        }
        $to->appendChild($copy);
        $this->copyChildren($el, $copy, false);
    }

    /**
     * Address of an embed (string), '' for an embed without a usable address, or false when
     * the element is not an embed. A figure counts as an embed when it wraps one.
     */
    private function embedSource(\DOMElement $el, string $tag): string|false
    {
        $node = $el;
        if ($tag === 'figure') {
            $node = $this->firstEmbedInside($el);
            if ($node === null) {
                return false;
            }
        } elseif (! PreviewPolicy::isEmbed($tag)) {
            return false;
        }

        foreach (PreviewPolicy::EMBED_SOURCES as $attr) {
            $url = PreviewPolicy::safeUrl($node->getAttribute($attr), true);
            if ($url !== null) {
                return $url;
            }
        }
        foreach ($node->getElementsByTagName('source') as $source) {
            $url = PreviewPolicy::safeUrl($source->getAttribute('src'), true);
            if ($url !== null) {
                return $url;
            }
        }

        return '';
    }

    private function firstEmbedInside(\DOMElement $el): ?\DOMElement
    {
        foreach (PreviewPolicy::EMBED as $embedTag) {
            $found = $el->getElementsByTagName($embedTag)->item(0);
            if ($found instanceof \DOMElement) {
                return $found;
            }
        }

        return null;
    }

    private function appendEmbedLink(string $url, \DOMNode $to, bool $topLevel): void
    {
        if ($url === '' || ($topLevel && ! $this->budget->openBlock())) {
            return;
        }
        $label = $this->budget->takeText($url);
        $link = $this->doc->createElement('a');
        $link->setAttribute('href', $url);
        $link->appendChild($this->doc->createTextNode($label));

        $holder = $topLevel ? $to->appendChild($this->doc->createElement('p')) : $to;
        $holder->appendChild($link);
    }

    private function safeHref(\DOMElement $el): ?string
    {
        return PreviewPolicy::safeUrl($el->getAttribute('href'), true);
    }
}
