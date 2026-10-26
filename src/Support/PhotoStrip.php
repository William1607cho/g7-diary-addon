<?php

namespace Plugins\G7\Diary\Addon\Support;

/**
 * The square thumbnail row under a list card (pure, for unit tests). D64.
 *
 * At most 4 cells (wide screens); narrow screens show the first 3 (CSS). The last shown cell
 * carries a "+N" cover when there are more photos: N = photos not shown at all —
 * wide: count − 4 on cell 4, narrow: count − 3 on cell 3.
 */
final class PhotoStrip
{
    public const CELLS_WIDE = 4;

    public const CELLS_NARROW = 3;

    /** Editor image address of a hash (the same address the body uses). */
    public const EDITOR_URL = '/api/plugins/sirsoft-ckeditor5/images/';

    /**
     * @param  array<int, string>  $hashes  Editor image hashes in body order.
     * @param  array<string, string>  $small  Original address => 240 variant address.
     * @return array<int, array{src: string, more_wide: int, more_narrow: int}>
     */
    public static function cells(array $hashes, array $small = []): array
    {
        $count = count($hashes);
        $cells = [];
        foreach (array_slice(array_values($hashes), 0, self::CELLS_WIDE) as $i => $hash) {
            $url = self::url($hash);
            $cells[] = [
                'src' => $small[$url] ?? $url,
                'more_wide' => $i === self::CELLS_WIDE - 1 && $count > self::CELLS_WIDE ? $count - self::CELLS_WIDE : 0,
                'more_narrow' => $i === self::CELLS_NARROW - 1 && $count > self::CELLS_NARROW ? $count - self::CELLS_NARROW : 0,
            ];
        }

        return $cells;
    }

    /**
     * Addresses of the cells that may go to the variant contract (first 4).
     *
     * @param  array<int, string>  $hashes
     * @return array<int, string>
     */
    public static function urls(array $hashes): array
    {
        return array_map([self::class, 'url'], array_slice(array_values($hashes), 0, self::CELLS_WIDE));
    }

    public static function url(string $hash): string
    {
        return self::EDITOR_URL.strtolower($hash);
    }
}
