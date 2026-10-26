<?php

namespace Plugins\G7\Diary\Addon\Layout;

/**
 * `board/form`: diary fields in place of the template's option cards, and the title
 * field made optional-looking on diary boards (pure). Found by shape (D17):
 * - option cards: the Div whose `if` checks `user_abilities?.can_manage` and `secret_mode`
 *   — hidden on diary boards, the diary fields go right before it;
 * - title: the Input named `title` (placeholder) and the `*` Span of its label.
 * Without the option cards the diary fields are not added (the template's checkboxes would
 * otherwise appear twice); the form stays as the template draws it.
 */
final class FormInjector
{
    public const FIELDS_ID = 'g7da_form_fields';

    public const NOT_DIARY = "form_meta?.data?.board?.type !== 'diary'";

    public const IS_DIARY = "form_meta?.data?.board?.type === 'diary'";

    public function __construct(private readonly Fragments $fragments) {}

    /**
     * @param  array<int, mixed>  $components
     * @return array{0: array<int, mixed>, 1: array<string, string>}
     */
    public function apply(array $components): array
    {
        if (Tree::hasId($components, self::FIELDS_ID)) {
            return [$components, ['fields' => 'already', 'title' => 'already']];
        }

        $report = [];
        $options = Tree::findPath($components, [self::class, 'isOptionCards']);
        if ($options === null) {
            $report['fields'] = 'no_anchor';
        } else {
            $components = Tree::update($components, $options, static function (array $n): array {
                $n['if'] = Tree::andCondition($n['if'] ?? null, self::NOT_DIARY);

                return $n;
            });
            $components = Tree::insertSibling($components, $options, $this->fragments->get('form/fields'), false);
            $report['fields'] = 'applied';
        }

        [$components, $report['title']] = $this->title($components);

        return [$components, $report];
    }

    /**
     * @param  array<int, mixed>  $components
     * @return array{0: array<int, mixed>, 1: string}
     */
    private function title(array $components): array
    {
        $input = Tree::findPath($components, [self::class, 'isTitleInput']);
        if ($input === null) {
            return [$components, 'no_anchor'];
        }

        $components = Tree::update($components, $input, static function (array $n): array {
            $n['props']['placeholder'] = self::placeholder($n['props']['placeholder'] ?? '');

            return $n;
        });

        $block = array_slice($input, 0, -1);
        $star = $block === [] ? null : Tree::findPath(Tree::get($components, $block)['children'], [self::class, 'isStar']);
        if ($star === null) {
            return [$components, 'placeholder_only'];
        }
        $components = Tree::update($components, array_merge($block, $star), static function (array $n): array {
            $n['if'] = Tree::andCondition($n['if'] ?? null, self::NOT_DIARY);

            return $n;
        });

        return [$components, 'applied'];
    }

    /**
     * Placeholder expression: the diary hint on diary boards, the original otherwise.
     */
    public static function placeholder(mixed $original): string
    {
        $original = is_string($original) ? $original : '';
        $otherwise = str_starts_with($original, '$t:')
            ? '$t('.json_encode(substr($original, 3)).')'
            : json_encode($original, JSON_UNESCAPED_UNICODE);

        return '{{'.self::IS_DIARY." ? \$t('g7-diary-addon.title_placeholder') : ".$otherwise.'}}';
    }

    /** @param  array<string, mixed>  $n */
    public static function isOptionCards(array $n): bool
    {
        return ($n['name'] ?? null) === 'Div'
            && Tree::strHas($n['if'] ?? null, 'user_abilities?.can_manage')
            && Tree::strHas($n['if'] ?? null, 'secret_mode');
    }

    /** @param  array<string, mixed>  $n */
    public static function isTitleInput(array $n): bool
    {
        return ($n['name'] ?? null) === 'Input' && ($n['props']['name'] ?? null) === 'title';
    }

    /** @param  array<string, mixed>  $n */
    public static function isStar(array $n): bool
    {
        return ($n['name'] ?? null) === 'Span' && trim((string) ($n['text'] ?? '')) === '*';
    }
}
