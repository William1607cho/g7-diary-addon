<?php

namespace Plugins\G7\Diary\Addon\Http\Controllers;

use App\Http\Controllers\Api\Base\PublicBaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Plugins\G7\Diary\Addon\Services\DiaryMemories;
use Plugins\G7\Diary\Addon\Support\DiaryBoardResolver;
use Plugins\G7\Diary\Addon\Support\MemoryPicker;

/**
 * `GET boards/{slug}/memories?count=3&exclude=1,2` — "지난 오늘" picks (bundle 3).
 * count 1–3 (default 3; the narrow screen asks for 1); exclude = ids already shown.
 */
class MemoryController extends PublicBaseController
{
    public function __construct(
        private readonly DiaryBoardResolver $boards,
        private readonly DiaryMemories $memories,
    ) {}

    public function index(Request $request, string $slug): JsonResponse
    {
        $board = $this->boards->resolve($request, $slug);
        $count = $request->query('count');
        $count = is_string($count) && preg_match('/^[1-3]$/D', $count) === 1 ? (int) $count : 3;

        return $this->success('common.success', $this->memories->pick($board, $count, MemoryPicker::parseExclude($request->query('exclude')), $request));
    }
}
