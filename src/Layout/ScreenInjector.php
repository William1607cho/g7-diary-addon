<?php

namespace Plugins\G7\Diary\Addon\Layout;

/**
 * Calendar / photo screens (bundle 3) and the admin settings screen (bundle 4): the plugin's
 * own layouts hold one root node in the content slot; this fills it with the screen fragment (pure). Keeping the content in
 * `resources/layout/` lets the screens share fragments with the list (head row, icons,
 * 지난 오늘 popup) and keeps every file small (D49).
 */
final class ScreenInjector
{
    /** @var array<string, string> screen => root node id */
    public const ROOTS = ['calendar' => 'g7da_calendar_root', 'photos' => 'g7da_photos_root', 'plugin_settings' => 'g7da_admin_root'];

    /**
     * Bundle 4: the admin settings screen (`resources/layouts/admin/plugin_settings.json`, D98).
     * Every plugin's settings layout has that name, so the root id decides — another plugin's
     * screen has no such node and is left as it is ("absent").
     *
     * @var array<string, string> screen => fragment
     */
    private const FRAGMENTS = ['plugin_settings' => 'admin/screen'];

    public function __construct(private readonly Fragments $fragments, private readonly string $screen) {}

    /**
     * Screen of a served layout name (the core serves plugin layouts under their file
     * `layout_name`; the registered name carries the plugin prefix), or null.
     */
    public static function screenOf(string $layoutName): ?string
    {
        $name = str_starts_with($layoutName, 'g7-diary-addon.') ? substr($layoutName, strlen('g7-diary-addon.')) : $layoutName;

        return isset(self::ROOTS[$name]) ? $name : null;
    }

    /**
     * @param  array<int, mixed>  $components
     * @return array{0: array<int, mixed>, 1: array<string, string>} New components and a report (screen => applied|already|absent).
     */
    public function apply(array $components): array
    {
        $root = self::ROOTS[$this->screen];
        $path = Tree::findPath($components, static fn (array $n): bool => ($n['id'] ?? null) === $root);
        if ($path === null) {
            // A layout of the same name from somewhere else — not ours, left as it is.
            return [$components, ['screen' => 'absent']];
        }
        if (! empty(Tree::get($components, $path)['children'])) {
            return [$components, ['screen' => 'already']];
        }

        $body = $this->fragments->get(self::FRAGMENTS[$this->screen] ?? $this->screen.'/screen');
        $components = Tree::update($components, $path, static function (array $n) use ($body): array {
            $n['children'] = [$body];

            return $n;
        });

        return [$components, ['screen' => 'applied']];
    }
}
