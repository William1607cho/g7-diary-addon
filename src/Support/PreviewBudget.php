<?php

namespace Plugins\G7\Diary\Addon\Support;

/**
 * Running limits while one preview is being built (blocks, characters).
 *
 * Defaults (D37): 6 top-level blocks, 320 characters — about six lines of the card at the
 * list width. The card clips the rest with a fade (CSS), so the server only has to stop
 * early enough that a page of cards stays small. Images are not part of the preview (D64).
 */
final class PreviewBudget
{
    public const MAX_BLOCKS = 6;

    public const MAX_CHARS = 320;

    public const ELLIPSIS = '…';

    public int $blocks = 0;

    public int $chars = 0;

    /** Something was left out. */
    public bool $clipped = false;

    /** Nothing more may be added. */
    public bool $stopped = false;

    public function __construct(
        private readonly int $maxBlocks = self::MAX_BLOCKS,
        private readonly int $maxChars = self::MAX_CHARS,
    ) {}

    /**
     * Opens a new top-level block; false (and stop) when the block limit is reached.
     */
    public function openBlock(): bool
    {
        if ($this->stopped) {
            return false;
        }
        if ($this->blocks >= $this->maxBlocks) {
            $this->clipped = true;
            $this->stopped = true;

            return false;
        }
        $this->blocks++;

        return true;
    }

    /**
     * Takes as much of the text as the character budget allows. A cut ends with "…" and
     * stops the preview. Whitespace-only text is free.
     */
    public function takeText(string $text): string
    {
        if ($this->stopped) {
            return '';
        }
        if (trim($text) === '') {
            return $text;
        }

        $length = Graphemes::length($text);
        $left = $this->maxChars - $this->chars;
        if ($length <= $left) {
            $this->chars += $length;

            return $text;
        }

        $this->chars = $this->maxChars;
        $this->clipped = true;
        $this->stopped = true;

        return rtrim(Graphemes::substr($text, max(0, $left))).self::ELLIPSIS;
    }
}
