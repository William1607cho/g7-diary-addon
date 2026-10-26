<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Plugins\G7\Diary\Addon\Support\DiaryCategoryFilter;
use Plugins\G7\Diary\Addon\Support\PreviewEligibility;

/**
 * S1: a category-filtered list must not reveal the category of secret posts the viewer
 * cannot open (D6). D55: blinded posts carry no preview.
 */
class FilterSecretTest extends TestCase
{
    public function test_guest_filter_keeps_only_public_posts(): void
    {
        $plan = DiaryCategoryFilter::plan(4, [1, 2, 3, 5, 6, 7], false, null);

        $this->assertSame(['not_in_categories', [1, 2, 3, 5, 6, 7]], $plan[0]);
        $this->assertSame(['secret_visible_to', null], $plan[1] ?? null);
        $this->assertCount(2, $plan);
    }

    public function test_member_filter_keeps_public_and_own_secret_posts(): void
    {
        $plan = DiaryCategoryFilter::plan(5, null, false, 2);

        $this->assertSame(['in_category', 5], $plan[0]);
        $this->assertSame(['secret_visible_to', 2], $plan[1] ?? null);
    }

    public function test_board_manager_filter_has_no_secret_step(): void
    {
        $plan = DiaryCategoryFilter::plan(5, null, true, 5);

        $this->assertSame([['in_category', 5]], $plan);
    }

    public function test_count_cache_is_split_by_viewer(): void
    {
        $guest = DiaryCategoryFilter::cacheParts(4, false, null);
        $member = DiaryCategoryFilter::cacheParts(4, false, 2);
        $other = DiaryCategoryFilter::cacheParts(4, false, 3);
        $manager = DiaryCategoryFilter::cacheParts(4, true, 5);

        $this->assertSame('g7da_cat:4', $guest[0]);
        $this->assertCount(4, array_unique(array_map('json_encode', [$guest, $member, $other, $manager])));
        $this->assertSame($manager, DiaryCategoryFilter::cacheParts(4, true, 9)); // managers share one key
    }

    public function test_blinded_posts_get_no_preview(): void
    {
        $this->assertFalse(PreviewEligibility::allows(['status' => 'blinded'], false));
        $this->assertTrue(PreviewEligibility::allows(['status' => 'published'], false));
        $this->assertFalse(PreviewEligibility::allows(['status' => 'published'], true));
    }
}
