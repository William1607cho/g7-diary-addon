<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Plugins\G7\Diary\Addon\Support\DiaryCategories;
use Plugins\G7\Diary\Addon\Support\PhotoStrip;

/**
 * D64 photo row cells and "+N" covers; D63 seed name of category 3.
 */
class PhotoStripTest extends TestCase
{
    private const H = ['aaaaaaaaaaa1', 'aaaaaaaaaaa2', 'aaaaaaaaaaa3', 'aaaaaaaaaaa4', 'aaaaaaaaaaa5', 'aaaaaaaaaaa6'];

    public function test_no_photos_no_cells(): void
    {
        $this->assertSame([], PhotoStrip::cells([]));
    }

    public function test_one_and_two_photos_have_no_cover(): void
    {
        $one = PhotoStrip::cells(array_slice(self::H, 0, 1));
        $two = PhotoStrip::cells(array_slice(self::H, 0, 2));

        $this->assertCount(1, $one);
        $this->assertCount(2, $two);
        $this->assertSame('/api/plugins/sirsoft-ckeditor5/images/aaaaaaaaaaa1', $one[0]['src']);
        $this->assertSame([0, 0], array_column($two, 'more_wide'));
        $this->assertSame([0, 0], array_column($two, 'more_narrow'));
    }

    public function test_four_photos_cover_only_on_narrow_screens(): void
    {
        $cells = PhotoStrip::cells(array_slice(self::H, 0, 4));

        $this->assertSame([0, 0, 0, 0], array_column($cells, 'more_wide'));
        $this->assertSame([0, 0, 1, 0], array_column($cells, 'more_narrow')); // narrow shows 3 → +1 on cell 3
    }

    public function test_five_photos_cover_cell_four_wide_and_cell_three_narrow(): void
    {
        $cells = PhotoStrip::cells(array_slice(self::H, 0, 5));

        $this->assertCount(4, $cells);
        $this->assertSame([0, 0, 0, 1], array_column($cells, 'more_wide'));
        $this->assertSame([0, 0, 2, 0], array_column($cells, 'more_narrow'));
    }

    public function test_body_order_and_variant_mapping(): void
    {
        $small = ['/api/plugins/sirsoft-ckeditor5/images/aaaaaaaaaaa2' => '/v/2-240.webp'];
        $cells = PhotoStrip::cells(['aaaaaaaaaaa3', 'aaaaaaaaaaa2', 'aaaaaaaaaaa1'], $small);

        $this->assertSame(['/api/plugins/sirsoft-ckeditor5/images/aaaaaaaaaaa3', '/v/2-240.webp', '/api/plugins/sirsoft-ckeditor5/images/aaaaaaaaaaa1'], array_column($cells, 'src'));
        $this->assertSame(4, count(PhotoStrip::urls(self::H)));
    }

    public function test_new_install_seed_names_category_three_anger_diary(): void
    {
        // D63 (bundle 2 instruction 3): only the seed default changes; existing installs keep their name.
        $this->assertSame(3, DiaryCategories::DEFAULTS[2]['id']);
        $this->assertSame('분노일기', DiaryCategories::DEFAULTS[2]['name']);
        $this->assertSame('😡', DiaryCategories::DEFAULTS[2]['icon']);
        $this->assertSame('#EF4444', DiaryCategories::DEFAULTS[2]['color']);
    }
}
