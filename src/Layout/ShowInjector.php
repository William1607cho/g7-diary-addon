<?php

namespace Plugins\G7\Diary\Addon\Layout;

/**
 * `board/show`: diary info line above the title and the created time hidden on diary
 * boards (pure). Found by shape (no template anchor, D17): the title is the H1 showing
 * `post?.data?.title`; the post header is its nearest ancestor that also holds the
 * created-time Span (`post?.data?.created_at_formatted`).
 */
final class ShowInjector
{
    public const META_ID = 'g7da_show_meta';

    public const NOT_DIARY = "post?.data?.board?.type !== 'diary'";

    public function __construct(private readonly Fragments $fragments) {}

    /**
     * @param  array<int, mixed>  $components
     * @return array{0: array<int, mixed>, 1: array<string, string>}
     */
    public function apply(array $components): array
    {
        if (Tree::hasId($components, self::META_ID)) {
            return [$components, ['meta' => 'already', 'created_at' => 'already']];
        }

        $header = $this->headerPath($components);
        if ($header === null) {
            return [$components, ['meta' => 'no_anchor', 'created_at' => 'no_anchor']];
        }

        $meta = $this->fragments->get('show/meta');
        $components = Tree::update($components, $header, static function (array $n) use ($meta): array {
            array_unshift($n['children'], $meta);

            return $n;
        });

        $timeIn = Tree::findPath(Tree::get($components, $header)['children'], [self::class, 'isCreatedAt']);
        if ($timeIn === null) {
            return [$components, ['meta' => 'applied', 'created_at' => 'no_anchor']];
        }
        $components = Tree::update($components, array_merge($header, $timeIn), static function (array $n): array {
            $n['if'] = Tree::andCondition($n['if'] ?? null, self::NOT_DIARY);

            return $n;
        });

        return [$components, ['meta' => 'applied', 'created_at' => 'applied']];
    }

    /**
     * @param  array<int, mixed>  $components
     * @return array<int, int>|null
     */
    private function headerPath(array $components): ?array
    {
        $title = Tree::findPath($components, [self::class, 'isTitle']);
        if ($title === null) {
            return null;
        }
        for ($k = count($title) - 1; $k >= 1; $k--) {
            $path = array_slice($title, 0, $k);
            if (Tree::contains(Tree::get($components, $path), [self::class, 'isCreatedAt'])) {
                return $path;
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $n */
    public static function isTitle(array $n): bool
    {
        return ($n['name'] ?? null) === 'H1' && Tree::strHas($n['text'] ?? null, 'post?.data?.title');
    }

    /** @param  array<string, mixed>  $n */
    public static function isCreatedAt(array $n): bool
    {
        return ($n['name'] ?? null) === 'Span' && Tree::strHas($n['text'] ?? null, 'post?.data?.created_at_formatted');
    }
}
