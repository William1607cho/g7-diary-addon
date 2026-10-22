<?php

namespace Plugins\G7\Diary\Addon\Providers;

use App\Extension\BasePluginServiceProvider;
use Plugins\G7\Diary\Addon\Support\DiaryBoards;
use Plugins\G7\Diary\Addon\Support\PendingDiaryMeta;

/**
 * Diary add-on service provider.
 *
 * The core PluginServiceProvider discovers `src/Providers/*ServiceProvider.php`; routes,
 * migrations and hook listeners are picked up by convention.
 *
 * Two request-scoped services: the board-type memo and the store that carries the diary
 * fields from the data filters to the after-save hooks (listeners are resolved fresh for
 * every hook call, so they cannot keep state themselves). No repository is rebound.
 */
class DiaryAddonServiceProvider extends BasePluginServiceProvider
{
    protected string $pluginIdentifier = 'g7-diary-addon';

    public function register(): void
    {
        parent::register();

        $this->app->scoped(DiaryBoards::class);
        $this->app->scoped(PendingDiaryMeta::class);
    }
}
