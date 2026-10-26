<?php

namespace Plugins\G7\Diary\Addon\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Plugins\G7\Diary\Addon\Support\DiaryBoards;
use Plugins\G7\Diary\Addon\Support\DiarySettings;

/**
 * Posts per load for diary board lists (before core, D40).
 *
 * When the setting "posts per load" is above 0 and the request names no `per_page`, the
 * value is put into the query before sirsoft-board reads it — every page (the first one
 * from the template and the next ones from infinite scroll) then has the same size, so
 * page numbers line up. 0 (default) leaves the board's own list size. Other boards are
 * never touched.
 */
class DiaryListPageSize
{
    public function __construct(private readonly DiaryBoards $boards) {}

    public function handle(Request $request, Closure $next): mixed
    {
        if (! $request->query->has('per_page') && $this->boards->isDiarySlug($request->route('slug'))) {
            $size = (int) DiarySettings::all()['list_page_size'];
            if ($size > 0) {
                $request->query->set('per_page', (string) $size);
            }
        }

        return $next($request);
    }
}
