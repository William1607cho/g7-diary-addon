<?php

namespace Plugins\G7\Diary\Addon\Http\Controllers;

use App\Http\Controllers\Api\Base\PublicBaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Plugins\G7\Diary\Addon\Services\DiaryCalendar;
use Plugins\G7\Diary\Addon\Support\CalendarGrid;
use Plugins\G7\Diary\Addon\Support\DiaryBoardResolver;
use Plugins\G7\Diary\Addon\Support\DiaryDateFilter;

/**
 * Calendar screen data of a diary board (bundle 3):
 * - `GET boards/{slug}/heatmap?year=YYYY` — year heatmap (default: this year);
 * - `GET boards/{slug}/calendar?month=YYYY-MM` — month calendar (default: this month);
 * - `GET boards/{slug}/day?date=YYYY-MM-DD` — posts of one diary date (date required).
 * Invalid values fall back to the default (year / month) or 404 (day).
 */
class CalendarController extends PublicBaseController
{
    public function __construct(
        private readonly DiaryBoardResolver $boards,
        private readonly DiaryCalendar $calendar,
    ) {}

    public function heatmap(Request $request, string $slug): JsonResponse
    {
        $board = $this->boards->resolve($request, $slug);
        $year = $request->query('year');
        $year = is_string($year) && preg_match('/^(19|2[0-9])[0-9]{2}$/D', $year) === 1 ? (int) $year : null;

        return $this->success('common.success', $this->calendar->heatmap($board, $year, $request));
    }

    public function month(Request $request, string $slug): JsonResponse
    {
        $board = $this->boards->resolve($request, $slug);

        return $this->success('common.success', $this->calendar->month($board, CalendarGrid::month($request->query('month')), $request));
    }

    public function day(Request $request, string $slug): JsonResponse
    {
        $board = $this->boards->resolve($request, $slug);
        $date = DiaryDateFilter::parse($request->query('date'));
        if ($date === null) {
            return $this->error('common.not_found', 404);
        }

        return $this->success('common.success', $this->calendar->day($board, $date, $request));
    }
}
