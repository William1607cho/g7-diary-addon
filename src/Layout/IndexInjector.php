<?php

namespace Plugins\G7\Diary\Addon\Layout;

/**
 * `board/index`: adds the diary branch next to the template's basic fallback branch and
 * excludes `diary` from that fallback (pure). Same shape-based anchor as g7-webzine-addon
 * — the fallback `if` holds `!['gallery','card', …].includes(posts?.data?.board?.type …`;
 * any list already extended by other add-ons (e.g. `'webzine'`) is kept as it is.
 */
final class IndexInjector
{
    public const BRANCH_ID = 'g7da_list_branch';

    private const FALLBACK = '/!\[((?:\s*\'[^\']*\'\s*,?)*)\]\.includes\(\s*posts\?\.data\?\.board\?\.type/';

    public function __construct(private readonly Fragments $fragments) {}

    /**
     * @param  array<int, mixed>  $components
     * @return array{0: array<int, mixed>, 1: array<string, string>} New components and a report (part => applied|already|no_anchor).
     */
    public function apply(array $components): array
    {
        if (Tree::hasId($components, self::BRANCH_ID)) {
            return [$components, ['branch' => 'already']];
        }

        $path = Tree::findPath($components, static fn (array $n): bool => self::isFallback($n));
        if ($path === null) {
            return [$components, ['branch' => 'no_anchor']];
        }

        $components = Tree::update($components, $path, static fn (array $n): array => self::excludeDiary($n));
        $components = Tree::insertSibling($components, $path, $this->fragments->get('index/branch'), true);

        return [$components, ['branch' => 'applied']];
    }

    /** List filter parameters forwarded from the address to the list data source. */
    public const FILTER_PARAMS = [
        'diary_category' => "{{query.diary_category ?? ''}}",
        'diary_date' => "{{query.diary_date ?? ''}}",
    ];

    /**
     * Forwards `diary_category` (D57) and `diary_date` (bundle 3) from the address to the list
     * data source. The template's `posts` source names its parameters one by one, so without
     * this the filters never reach the API. On other boards the values are empty and the filter
     * listener ignores them.
     *
     * @param  array<int, mixed>  $sources  Layout `data_sources`.
     * @return array{0: array<int, mixed>, 1: string} applied|already|no_anchor
     */
    public static function withFilterParam(array $sources): array
    {
        foreach ($sources as $i => $source) {
            if (! is_array($source) || ($source['id'] ?? null) !== 'posts' || ! Tree::strHas($source['endpoint'] ?? null, '/boards/{{route.slug}}/posts')) {
                continue;
            }
            $params = is_array($source['params'] ?? null) ? $source['params'] : [];
            if (array_diff_key(self::FILTER_PARAMS, $params) === []) {
                return [$sources, 'already'];
            }
            $sources[$i]['params'] = $params + self::FILTER_PARAMS;

            return [$sources, 'applied'];
        }

        return [$sources, 'no_anchor'];
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public static function isFallback(array $node): bool
    {
        return is_string($node['if'] ?? null) && preg_match(self::FALLBACK, $node['if']) === 1;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    public static function excludeDiary(array $node): array
    {
        $node['if'] = preg_replace_callback(self::FALLBACK, static function (array $m): string {
            if (preg_match("/'diary'/", $m[1]) === 1) {
                return $m[0];
            }
            $list = rtrim(trim($m[1]), ',');

            return str_replace('['.$m[1].']', '['.($list === '' ? '' : $list.',')."'diary']", $m[0]);
        }, (string) $node['if'], 1);

        return $node;
    }
}
