<?php

namespace Plugins\G7\Diary\Addon\Support;

/**
 * Whether a list item may carry the preview HTML (pure).
 */
final class PreviewEligibility
{
    /**
     * @param  array<string, mixed>  $item  List item from the sirsoft-board response.
     */
    public static function allows(array $item, bool $locked): bool
    {
        // D55: blinded posts keep their row but not their body — as sirsoft-board's own list preview.
        return ! $locked && ($item['status'] ?? null) !== 'blinded';
    }
}
