<?php

namespace Plugins\G7\Diary\Addon\Listeners;

use App\Contracts\Extension\HookListenerInterface;
use Illuminate\Support\Facades\Log;
use Plugins\G7\Diary\Addon\Layout\Fragments;
use Plugins\G7\Diary\Addon\Layout\FormInjector;
use Plugins\G7\Diary\Addon\Layout\IndexInjector;
use Plugins\G7\Diary\Addon\Layout\ScreenInjector;
use Plugins\G7\Diary\Addon\Layout\ShowInjector;

/**
 * Diary screens on the visitor template, without modifying it (D17).
 *
 * `core.layout_extension.after_apply` filter on the final tree of `board/index`,
 * `board/show` and `board/form`, and of the plugin's own calendar / photo screens (their
 * root node is filled from the fragments). The added nodes render only on diary boards (`if` on
 * the board type), nodes added by other add-ons are left in place, and an anchor that
 * cannot be found is logged as a warning while the layout is returned unchanged for that
 * part. Priority 30 runs after the forum / webzine listeners (20): the webzine listener
 * looks for the untouched basic fallback condition, so it must see it first.
 */
class DiaryLayoutListener implements HookListenerInterface
{
    public const PRIORITY = 30;

    public static function getSubscribedHooks(): array
    {
        return [
            'core.layout_extension.after_apply' => [
                'method' => 'inject',
                'type' => 'filter',
                'priority' => self::PRIORITY,
            ],
        ];
    }

    public function handle(...$args): void {}

    /**
     * @param  array<string, mixed>  $layout
     * @return array<string, mixed>
     */
    public function inject(mixed $layout, int $templateId = 0): mixed
    {
        if (! is_array($layout) || ! is_array($layout['components'] ?? null)) {
            return $layout;
        }

        $injector = $this->injectorFor((string) ($layout['layout_name'] ?? ''));
        if ($injector === null) {
            return $layout;
        }

        try {
            [$components, $report] = $injector->apply($layout['components']);
            if ($injector instanceof IndexInjector) {
                [$layout['data_sources'], $report['filter_param']] = IndexInjector::withFilterParam(
                    is_array($layout['data_sources'] ?? null) ? $layout['data_sources'] : []
                );
            }
        } catch (\Throwable $e) {
            Log::warning('[g7-diary-addon] layout injection failed, layout left unchanged', [
                'layout' => $layout['layout_name'], 'template_id' => $templateId, 'error' => $e->getMessage(),
            ]);

            return $layout;
        }

        if (in_array('no_anchor', $report, true)) {
            Log::warning('[g7-diary-addon] layout anchor not found; that part is not shown', [
                'layout' => $layout['layout_name'], 'template_id' => $templateId, 'report' => $report,
            ]);
        }
        $layout['components'] = $components;

        return $layout;
    }

    private function injectorFor(string $name): IndexInjector|ShowInjector|FormInjector|ScreenInjector|null
    {
        $screen = ScreenInjector::screenOf($name);
        if ($screen !== null) {
            return new ScreenInjector(Fragments::plugin(), $screen);
        }

        return match ($name) {
            'board/index' => new IndexInjector(Fragments::plugin()),
            'board/show' => new ShowInjector(Fragments::plugin()),
            'board/form' => new FormInjector(Fragments::plugin()),
            default => null,
        };
    }
}
