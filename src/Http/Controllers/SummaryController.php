<?php

namespace Plugins\G7\Diary\Addon\Http\Controllers;

use App\Http\Controllers\Api\Base\PublicBaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Plugins\G7\Diary\Addon\Services\DiaryPostReader;
use Plugins\G7\Diary\Addon\Services\DiarySummary;
use Plugins\G7\Diary\Addon\Support\BoardReadGate;

/**
 * `GET /api/plugins/g7-diary-addon/boards/{slug}/summary`
 *
 * Side panel data: writing streak (current / longest), total posts and posts per
 * category (secret posts the viewer cannot open are not counted per category — D6).
 * Unknown, inactive or non-diary boards are 404; read permission is checked again here
 * because this route does not pass through sirsoft-board.
 */
class SummaryController extends PublicBaseController
{
    public function __construct(
        private readonly DiaryPostReader $reader,
        private readonly DiarySummary $summary,
        private readonly BoardReadGate $gate,
    ) {}

    public function show(Request $request, string $slug): JsonResponse
    {
        $board = preg_match('/^[A-Za-z0-9_-]{1,100}$/', $slug) === 1 ? $this->reader->diaryBoard($slug) : null;
        if ($board === null) {
            return $this->error('common.not_found', 404);
        }

        $this->gate->assertReadable($request, $board);

        return $this->success('common.success', $this->summary->forBoard($board, $request));
    }
}
