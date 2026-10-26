<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Plugins\G7\Diary\Addon\Layout\Fragments;
use Plugins\G7\Diary\Addon\Layout\ScreenInjector;
use Plugins\G7\Diary\Addon\Services\CategoryAdmin;
use Plugins\G7\Diary\Addon\Support\CategoryCounts;
use Plugins\G7\Diary\Addon\Support\CategoryStore;
use Plugins\G7\Diary\Addon\Support\DiaryCategoryFilter;
use Plugins\G7\Diary\Addon\Support\WeatherKeys;

/**
 * Bundle 4: the category batch save (one transaction, rollback on failure — fake store),
 * single delete, the settings save hook, the category-set cache axis and the admin screen root.
 */
class AdminSaveTest extends TestCase
{
    public function test_batch_save_updates_inserts_reorders_and_soft_deletes(): void
    {
        $store = new FakeCategoryStore();
        $admin = new CategoryAdmin($store);
        $r = $admin->save([
            ['id' => 3, 'emoji' => '😡', 'name' => '분노일기', 'color' => '#EF4444', 'is_default' => false],
            ['id' => 1, 'emoji' => '😊', 'name' => '행복일기', 'color' => '#eab308', 'is_default' => true],
            ['id' => null, 'emoji' => '🌧️', 'name' => '비 오는 날', 'color' => '#14B8A6', 'is_default' => false],
        ]);
        $this->assertTrue($r['ok']);
        $this->assertSame([3, 1, 8], array_column($store->activeRows(), 'id'));
        $this->assertSame([2, 4, 5, 6, 7], $store->deleted);
        $this->assertSame(2, $store->rows[1]['sort_order']);
        $this->assertSame('#EAB308', $store->rows[1]['color']);
        $this->assertTrue($store->rows[1]['is_default']);
        $this->assertSame('c8', $r['rows'][2]['uid']);
        $this->assertSame(2, $r['rows'][0]['posts']);
        // D115: the default (now 1) also holds posts without a category (0) and of deleted ids (2 now, 99).
        $this->assertSame(5 + 4 + 3 + 1, $r['rows'][1]['posts']);
        $this->assertSame(0, $r['rows'][2]['posts']);
        $this->assertSame(1, $store->transactions);
    }

    public function test_admin_counts_follow_the_panel_rule(): void
    {
        $cats = [['id' => 1, 'is_default' => false], ['id' => 4, 'is_default' => true], ['id' => 7, 'is_default' => false]];
        $this->assertSame([1 => 6, 4 => 2 + 7 + 3, 7 => 0], CategoryCounts::fromTotals([1 => 6, 4 => 2, 0 => 7, 99 => 3], $cats));
        // Same numbers as the per-post panel count of the same posts.
        $posts = array_merge(array_fill(0, 6, 1), array_fill(0, 2, 4), array_fill(0, 7, null), array_fill(0, 3, 99));
        $panel = array_column(CategoryCounts::count($posts, $cats), 'count', 'id');
        $this->assertSame($panel, CategoryCounts::fromTotals([1 => 6, 4 => 2, 0 => 7, 99 => 3], $cats));
    }

    public function test_a_failure_inside_the_transaction_rolls_everything_back(): void
    {
        $store = new FakeCategoryStore();
        $store->failOnInsert = true;
        $before = $store->rows;
        $thrown = false;
        try {
            (new CategoryAdmin($store))->save([
                ['id' => 1, 'emoji' => '🎈', 'name' => '바뀐 이름', 'color' => '#000000', 'is_default' => true],
                ['id' => null, 'emoji' => '🌧️', 'name' => '새 분류', 'color' => '#14B8A6', 'is_default' => false],
            ]);
        } catch (\RuntimeException $e) {
            $thrown = true;
        }
        $this->assertTrue($thrown);
        $this->assertSame($before, $store->rows);
        $this->assertSame([], $store->deleted);
    }

    public function test_invalid_batch_writes_nothing(): void
    {
        $store = new FakeCategoryStore();
        $r = (new CategoryAdmin($store))->save([['id' => 1, 'emoji' => '😊😊', 'name' => 'a', 'color' => '#000000', 'is_default' => false]]);
        $this->assertFalse($r['ok']);
        $this->assertSame('emoji_single', $r['errors']['categories.0.emoji']);
        $this->assertSame('default_missing', $r['errors']['categories']);
        $this->assertSame(0, $store->transactions);
    }

    public function test_single_delete_refuses_default_and_soft_deletes_others(): void
    {
        $store = new FakeCategoryStore();
        $admin = new CategoryAdmin($store);
        $this->assertSame(['id' => 'delete_default'], $admin->delete(4)['errors']);
        $this->assertTrue($admin->delete(7)['ok']);
        $this->assertSame([7], $store->deleted);
        $this->assertSame(['id' => 'category_unknown'], $admin->delete(7)['errors']);
    }

    public function test_settings_save_gives_new_weather_a_key_and_drops_screen_fields(): void
    {
        $out = WeatherKeys::forSave(['weather_options' => [
            ['uid' => 'w:sunny', 'key' => 'sunny', 'emoji' => '☀️', 'label' => '해', 'posts' => 4],
            ['uid' => 'n1', 'key' => '', 'emoji' => '🌫️', 'label' => '안개', 'posts' => 0],
        ], 'auto_title_length' => 40]);
        $this->assertSame(['key' => 'sunny', 'emoji' => '☀️', 'label' => '해'], $out['weather_options'][0]);
        $this->assertTrue(preg_match('/^w_[a-z0-9]{6}$/', $out['weather_options'][1]['key']) === 1);
        $this->assertSame(40, $out['auto_title_length']);
        $this->assertSame(['x' => 1], WeatherKeys::forSave(['x' => 1]));
        $this->assertSame(['weather_options' => []], WeatherKeys::forSave(['weather_options' => []]));
        // A list that still fails is not written (the stored one stays).
        $this->assertSame(['y' => 2], WeatherKeys::forSave(['weather_options' => [['key' => 'Bad', 'emoji' => 'a', 'label' => '']], 'y' => 2]));
    }

    public function test_category_set_changes_move_the_filtered_count_cache(): void
    {
        $v1 = DiaryCategoryFilter::setVersion([1, 2, 3, 4], 4);
        $this->assertSame($v1, DiaryCategoryFilter::setVersion([4, 3, 2, 1], 4));
        $this->assertTrue($v1 !== DiaryCategoryFilter::setVersion([1, 2, 3, 4], 1));
        $this->assertTrue($v1 !== DiaryCategoryFilter::setVersion([1, 2, 4], 4));
        $this->assertSame(['g7da_cat:4', 'g7da_v:guest', 'g7da_cs:'.$v1], DiaryCategoryFilter::cacheParts(4, false, null, $v1));
        $this->assertSame(['g7da_cat:4', 'g7da_v:guest'], DiaryCategoryFilter::cacheParts(4, false, null));
    }

    public function test_admin_screen_root_is_filled_and_other_plugins_screens_are_left(): void
    {
        $this->assertSame('plugin_settings', ScreenInjector::screenOf('g7-diary-addon.plugin_settings'));
        $this->assertSame('plugin_settings', ScreenInjector::screenOf('plugin_settings'));
        $ours = [['id' => 'page', 'children' => [['id' => 'g7da_admin_root', 'type' => 'basic', 'name' => 'Div']]]];
        [$out, $report] = (new ScreenInjector(Fragments::plugin(), 'plugin_settings'))->apply($ours);
        $this->assertSame(['screen' => 'applied'], $report);
        $body = json_encode($out, JSON_UNESCAPED_UNICODE);
        foreach (['g7da-adm-tabs', 'g7da-adm-panel-cats', 'g7da-adm-panel-weather', 'g7da-adm-panel-disp', 'g7da-adm-picker', 'g7da-adm-dialog', 'g7da-adm-crow'] as $mark) {
            $this->assertStringContainsString($mark, $body);
        }
        $this->assertStringNotContainsString('g7da_include', $body);
        $other = [['id' => 'home_widgets_settings_content', 'children' => []]];
        $this->assertSame([$other, ['screen' => 'absent']], (new ScreenInjector(Fragments::plugin(), 'plugin_settings'))->apply($other));
    }
}

/**
 * In-memory {@see CategoryStore}: the defaults 1–7 (4 = default), ids from 8 on, a snapshot
 * transaction that restores the rows when the work throws.
 */
class FakeCategoryStore implements CategoryStore
{
    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    /** @var array<int, int> */
    public array $deleted = [];

    public int $transactions = 0;

    public bool $failOnInsert = false;

    private int $next = 8;

    public function __construct()
    {
        foreach (['😊', '😢', '😡', '😌', '🎉', '🍜', '📸'] as $i => $e) {
            $this->rows[$i + 1] = ['id' => $i + 1, 'emoji' => $e, 'name' => 'c'.($i + 1), 'color' => '#EAB308', 'sort_order' => $i + 1, 'is_default' => $i === 3, 'deleted' => false];
        }
    }

    public function activeRows(): array
    {
        $live = array_filter($this->rows, static fn (array $r): bool => ! $r['deleted']);
        usort($live, static fn (array $a, array $b): int => [$a['sort_order'], $a['id']] <=> [$b['sort_order'], $b['id']]);

        return array_map(static fn (array $r): array => ['id' => $r['id'], 'emoji' => $r['emoji'], 'name' => $r['name'], 'color' => $r['color'], 'is_default' => $r['is_default']], $live);
    }

    public function transaction(callable $work): mixed
    {
        $this->transactions++;
        $snapshot = [$this->rows, $this->deleted, $this->next];
        try {
            return $work();
        } catch (\Throwable $e) {
            [$this->rows, $this->deleted, $this->next] = $snapshot;
            throw $e;
        }
    }

    public function update(int $id, array $fields): void
    {
        $this->rows[$id] = $fields + ['id' => $id, 'deleted' => false];
    }

    public function insert(array $fields): int
    {
        if ($this->failOnInsert) {
            throw new \RuntimeException('insert failed');
        }
        $id = $this->next++;
        $this->rows[$id] = $fields + ['id' => $id, 'deleted' => false];

        return $id;
    }

    public function softDelete(array $ids): void
    {
        foreach ($ids as $id) {
            $this->rows[$id]['deleted'] = true;
            $this->rows[$id]['is_default'] = false;
            $this->deleted[] = $id;
        }
    }

    public function postsPerCategory(): array
    {
        return [3 => 2, 1 => 5, 2 => 3, 0 => 4, 99 => 1];
    }

    public function postsPerWeather(): array
    {
        return ['sunny' => 4];
    }
}
