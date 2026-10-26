<?php

namespace Plugins\G7\Diary\Addon\Listeners;

use App\Contracts\Extension\HookListenerInterface;
use Plugins\G7\Diary\Addon\Support\DiaryRoutes;

/**
 * Calendar and photo screens on the visitor template (D12).
 *
 * `core.routes.filter_merged` receives the merged routes, the template type and its
 * identifier; on user templates the diary screen routes are appended
 * ({@see DiaryRoutes::merge()}). Admin templates and existing paths are not touched.
 */
class DiaryRouteListener implements HookListenerInterface
{
    public static function getSubscribedHooks(): array
    {
        return [
            'core.routes.filter_merged' => ['method' => 'addRoutes', 'type' => 'filter', 'priority' => 20],
        ];
    }

    public function handle(...$args): void {}

    /**
     * @param  array<int, mixed>  $routes
     * @return array<int, mixed>
     */
    public function addRoutes(mixed $routes, string $templateType = '', string $identifier = ''): mixed
    {
        return is_array($routes) ? DiaryRoutes::merge($routes, $templateType) : $routes;
    }
}
