<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Plugins\G7\Diary\Addon\Layout\Fragments;
use Plugins\G7\Diary\Addon\Layout\FormInjector;
use Plugins\G7\Diary\Addon\Layout\IndexInjector;
use Plugins\G7\Diary\Addon\Layout\ShowInjector;
use Plugins\G7\Diary\Addon\Layout\Tree;

/**
 * Layout injection by shape — anchor found / not found, idempotent, other add-ons' nodes kept.
 * Trees are trimmed copies of the wc-community shapes (partials already inlined).
 */
class LayoutInjectionTest extends TestCase
{
    private function fragments(): Fragments
    {
        return new Fragments(dirname(__DIR__, 2).'/resources/layout');
    }

    /** @return array<int, mixed> */
    private function indexTree(string $fallbackList = "'gallery','card','webzine'"): array
    {
        return [[
            'type' => 'layout', 'name' => 'Container', 'children' => [[
                'type' => 'basic', 'name' => 'Div', 'children' => [
                    ['type' => 'basic', 'name' => 'Div', 'if' => "{{posts?.data?.board?.type === 'basic' || ![{$fallbackList}].includes(posts?.data?.board?.type ?? 'basic')}}", 'children' => []],
                    ['type' => 'basic', 'name' => 'Div', 'if' => "{{posts?.data?.board?.type === 'gallery'}}", 'children' => []],
                    ['id' => 'g7_webzine_addon_list_branch', 'type' => 'basic', 'name' => 'Div', 'if' => "{{posts?.data?.board?.type === 'webzine'}}"],
                ],
            ]],
        ]];
    }

    public function test_index_branch_is_added_and_fallback_excludes_diary(): void
    {
        [$out, $report] = (new IndexInjector($this->fragments()))->apply($this->indexTree());
        $branches = $out[0]['children'][0]['children'];

        $this->assertSame(['branch' => 'applied'], $report);
        $this->assertStringContainsString("!['gallery','card','webzine','diary'].includes(", $branches[0]['if']);
        $this->assertSame(IndexInjector::BRANCH_ID, $branches[1]['id']);
        $this->assertSame('g7_webzine_addon_list_branch', $branches[3]['id']); // other add-on node kept
        $this->assertCount(4, $branches);
    }

    public function test_index_branch_contains_no_unresolved_include_or_slot(): void
    {
        [$out] = (new IndexInjector($this->fragments()))->apply($this->indexTree("'gallery','card'"));
        $json = json_encode($out, JSON_UNESCAPED_UNICODE);

        $this->assertStringNotContainsString(Fragments::INCLUDE_KEY, $json);
        $this->assertStringNotContainsString(Fragments::SLOT_KEY, $json);
        $this->assertStringNotContainsString('"title"', $json); // custom tooltips, no title attribute
        $this->assertStringNotContainsString('ck-content', $json);
        $this->assertStringContainsString("!['gallery','card','diary'].includes(", $out[0]['children'][0]['children'][0]['if']);
    }

    public function test_index_is_idempotent_and_safe_without_anchor(): void
    {
        $injector = new IndexInjector($this->fragments());
        [$once] = $injector->apply($this->indexTree());
        [$twice, $report] = $injector->apply($once);

        $this->assertSame($once, $twice);
        $this->assertSame(['branch' => 'already'], $report);

        $plain = [['type' => 'basic', 'name' => 'Div', 'if' => "{{posts?.data?.board?.type === 'basic'}}"]];
        [$same, $none] = $injector->apply($plain);
        $this->assertSame($plain, $same);
        $this->assertSame(['branch' => 'no_anchor'], $none);
    }

    /** @return array<int, mixed> */
    private function showTree(): array
    {
        return [[
            'type' => 'basic', 'name' => 'Div', 'props' => ['className' => 'bg-white'], 'children' => [[
                'type' => 'basic', 'name' => 'Div', 'props' => ['className' => 'p-6 pb-2 border-b'], 'children' => [
                    ['type' => 'basic', 'name' => 'Div', 'children' => [
                        ['type' => 'basic', 'name' => 'Div', 'children' => [
                            ['type' => 'basic', 'name' => 'H1', 'text' => "{{post?.data?.title ?? ''}}"],
                        ]],
                    ]],
                    ['type' => 'basic', 'name' => 'Div', 'children' => [
                        ['type' => 'basic', 'name' => 'Div', 'children' => [
                            ['type' => 'basic', 'name' => 'Span', 'props' => ['title' => '{{post?.data?.created_at}}'], 'text' => "{{post?.data?.created_at_formatted ?? ''}}"],
                            ['type' => 'basic', 'name' => 'Span', 'if' => '{{post?.data?.view_count}}', 'text' => '{{post.data.view_count}}'],
                        ]],
                    ]],
                ],
            ]],
        ]];
    }

    public function test_show_meta_above_title_and_created_time_hidden_on_diary(): void
    {
        [$out, $report] = (new ShowInjector($this->fragments()))->apply($this->showTree());
        $header = $out[0]['children'][0];

        $this->assertSame(['meta' => 'applied', 'created_at' => 'applied'], $report);
        $this->assertSame(ShowInjector::META_ID, $header['children'][0]['id']);
        $time = $header['children'][2]['children'][0]['children'][0];
        $this->assertSame('{{'.ShowInjector::NOT_DIARY.'}}', $time['if']);
        $views = $header['children'][2]['children'][0]['children'][1];
        $this->assertSame('{{post?.data?.view_count}}', $views['if']); // view count untouched
    }

    public function test_show_without_title_is_unchanged(): void
    {
        $tree = [['type' => 'basic', 'name' => 'Div', 'children' => [['type' => 'basic', 'name' => 'Span', 'text' => 'x']]]];
        [$out, $report] = (new ShowInjector($this->fragments()))->apply($tree);

        $this->assertSame($tree, $out);
        $this->assertSame('no_anchor', $report['meta']);
    }

    /** @return array<int, mixed> */
    private function formTree(): array
    {
        return [[
            'type' => 'basic', 'name' => 'Form', 'children' => [
                ['type' => 'basic', 'name' => 'Div', 'if' => "{{form_meta?.data?.board?.user_abilities?.can_manage || form_meta?.data?.board?.secret_mode === 'enabled'}}", 'children' => []],
                ['type' => 'basic', 'name' => 'Div', 'children' => [
                    ['type' => 'basic', 'name' => 'Label', 'children' => [
                        ['type' => 'basic', 'name' => 'Span', 'text' => '$t:board.form.title'],
                        ['type' => 'basic', 'name' => 'Span', 'text' => '*'],
                    ]],
                    ['type' => 'basic', 'name' => 'Input', 'props' => ['type' => 'text', 'name' => 'title', 'placeholder' => '$t:board.form.title_placeholder']],
                ]],
            ],
        ]];
    }

    public function test_form_fields_replace_option_cards_and_title_is_optional_looking(): void
    {
        [$out, $report] = (new FormInjector($this->fragments()))->apply($this->formTree());
        $children = $out[0]['children'];

        $this->assertSame(['fields' => 'applied', 'title' => 'applied'], $report);
        $this->assertSame(FormInjector::FIELDS_ID, $children[0]['id']);
        $this->assertStringContainsString(FormInjector::NOT_DIARY, $children[1]['if']);
        $this->assertStringContainsString('user_abilities?.can_manage', $children[1]['if']);
        $title = $children[2];
        $this->assertSame('{{'.FormInjector::NOT_DIARY.'}}', $title['children'][0]['children'][1]['if']);
        $this->assertSame(
            "{{form_meta?.data?.board?.type === 'diary' ? \$t('g7-diary-addon.title_placeholder') : \$t(\"board.form.title_placeholder\")}}",
            $title['children'][1]['props']['placeholder']
        );
    }

    public function test_form_without_option_cards_adds_no_fields(): void
    {
        $tree = $this->formTree();
        array_shift($tree[0]['children']);
        [$out, $report] = (new FormInjector($this->fragments()))->apply($tree);

        $this->assertSame('no_anchor', $report['fields']);
        $this->assertFalse(Tree::hasId($out, FormInjector::FIELDS_ID));
        $this->assertSame('applied', $report['title']);
    }

    public function test_list_data_source_forwards_the_filter_parameter(): void
    {
        $sources = [
            ['id' => 'other', 'endpoint' => '/x', 'params' => []],
            ['id' => 'posts', 'endpoint' => '/api/modules/sirsoft-board/boards/{{route.slug}}/posts', 'params' => ['page' => '{{query.page ?? 1}}']],
        ];
        [$out, $r] = IndexInjector::withFilterParam($sources);

        $this->assertSame('applied', $r);
        $this->assertSame(['page' => '{{query.page ?? 1}}', 'diary_category' => "{{query.diary_category ?? ''}}", 'diary_date' => "{{query.diary_date ?? ''}}"], $out[1]['params']);
        $this->assertSame($sources[0], $out[0]);
        $this->assertSame('already', IndexInjector::withFilterParam($out)[1]);
        $this->assertSame('no_anchor', IndexInjector::withFilterParam([$sources[0]])[1]);
    }

    public function test_grids_repeat_their_items_not_themselves(): void
    {
        // The engine repeats the node that carries `iteration`; a grid / row container must not carry it.
        $f = $this->fragments();
        foreach (['index/mobile-categories', 'index/panel-categories', 'form/weather'] as $name) {
            $node = $f->get($name);
            $this->assertFalse(isset($node['iteration']), $name);
            $iterated = array_values(array_filter($node['children'], static fn (array $c): bool => isset($c['iteration'])));
            $this->assertCount(1, $iterated, $name);
        }
        // D67: 'All' comes first and is not repeated.
        foreach (['index/mobile-categories', 'index/panel-categories'] as $name) {
            $first = $f->get($name)['children'][0];
            $this->assertFalse(isset($first['iteration']), $name);
            $this->assertStringContainsString('--all', $first['props']['className'], $name);
        }
        $photos = $f->get('index/card-photos');
        $this->assertFalse(isset($photos['iteration']));
        $this->assertTrue(isset($photos['children'][0]['iteration']));
        $cats = $f->get('form/categories');
        $this->assertFalse(isset($cats['children'][0]['iteration']));
        $this->assertTrue(isset($cats['children'][0]['children'][0]['iteration']));
        $cards = $f->get('index/cards');
        $this->assertFalse(isset($cards['iteration']));
        $this->assertTrue(isset($cards['children'][0]['iteration']));
    }

    public function test_and_condition_keeps_existing_expression(): void
    {
        $this->assertSame("{{(a || b) && x !== 'diary'}}", Tree::andCondition('{{a || b}}', "x !== 'diary'"));
        $this->assertSame('{{y}}', Tree::andCondition(null, 'y'));
    }

    public function test_all_fragments_are_valid_json_and_small(): void
    {
        $dir = dirname(__DIR__, 2).'/resources/layout';
        $files = array_merge(glob($dir.'/*/*.json') ?: []);
        $this->assertTrue(count($files) >= 18);
        foreach ($files as $file) {
            $this->assertTrue(is_array(json_decode((string) file_get_contents($file), true)), basename($file));
            $this->assertTrue(count(file($file)) <= 300, basename($file));
        }
    }
}
