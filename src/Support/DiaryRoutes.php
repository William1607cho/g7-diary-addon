<?php

namespace Plugins\G7\Diary\Addon\Support;

/**
 * Visitor routes of the diary screens (pure, for unit tests). D12.
 *
 * Plugin routes are merged into admin templates only, so the calendar and photo screens
 * reach visitors through `core.routes.filter_merged` (user templates). The layouts are the
 * plugin's own `resources/layouts/user/*.json`, registered by the core as
 * `g7-diary-addon.<layout_name>`. A path that is already present is left as it is.
 */
final class DiaryRoutes
{
    public const IDENTIFIER = 'g7-diary-addon';

    /** @var array<int, array{path: string, layout: string, title: string}> */
    public const SCREENS = [
        ['path' => '/diary/:slug/calendar', 'layout' => self::IDENTIFIER.'.calendar', 'title' => '$t:g7-diary-addon.calendar_title'],
        ['path' => '/diary/:slug/photos', 'layout' => self::IDENTIFIER.'.photos', 'title' => '$t:g7-diary-addon.photos_title'],
    ];

    /**
     * @param  array<int, mixed>  $routes  Merged routes of a template.
     * @return array<int, mixed>
     */
    public static function merge(array $routes, string $templateType): array
    {
        if ($templateType !== 'user') {
            return $routes;
        }

        $taken = [];
        foreach ($routes as $route) {
            if (is_array($route) && is_string($route['path'] ?? null)) {
                $taken[$route['path']] = true;
            }
        }

        foreach (self::SCREENS as $screen) {
            if (isset($taken[$screen['path']])) {
                continue;
            }
            $routes[] = [
                'path' => $screen['path'],
                'layout' => $screen['layout'],
                'auth_required' => false,
                'meta' => ['title' => $screen['title']],
                'source' => ['kind' => 'plugin', 'identifier' => self::IDENTIFIER],
            ];
        }

        return $routes;
    }
}
