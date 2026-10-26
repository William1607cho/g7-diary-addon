<?php

namespace Plugins\G7\Diary\Addon\Support;

use App\Helpers\ResponseHelper;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Plugins\G7\Diary\Addon\Services\DiaryPostReader;

/**
 * Board of an add-on endpoint: an active diary board the viewer may read. Unknown,
 * inactive and non-diary boards are 404; read permission is checked again because these
 * routes do not pass through sirsoft-board ({@see BoardReadGate}).
 */
class DiaryBoardResolver
{
    public function __construct(
        private readonly DiaryPostReader $reader,
        private readonly BoardReadGate $gate,
    ) {}

    public function resolve(Request $request, string $slug): object
    {
        $board = preg_match('/^[A-Za-z0-9_-]{1,100}$/', $slug) === 1 ? $this->reader->diaryBoard($slug) : null;
        if ($board === null) {
            throw new HttpResponseException(ResponseHelper::notFound());
        }
        $this->gate->assertReadable($request, $board);

        return $board;
    }
}
