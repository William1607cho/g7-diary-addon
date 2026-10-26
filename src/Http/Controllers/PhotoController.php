<?php

namespace Plugins\G7\Diary\Addon\Http\Controllers;

use App\Http\Controllers\Api\Base\PublicBaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Plugins\G7\Diary\Addon\Services\DiaryPhotos;
use Plugins\G7\Diary\Addon\Support\DiaryBoardResolver;
use Plugins\G7\Diary\Addon\Support\DiaryCatalog;
use Plugins\G7\Diary\Addon\Support\DiaryCategoryFilter;
use Plugins\G7\Diary\Addon\Support\SecretVisibility;

/**
 * `GET boards/{slug}/photos?cursor=YYYY-MM[.N]&diary_category=<id>` — one page of the photo
 * gallery (bundle 3). The page size and display mode come from the settings. The category
 * filter is the list's own (D44 default-category rule, S1 secret step — D56).
 */
class PhotoController extends PublicBaseController
{
    public function __construct(
        private readonly DiaryBoardResolver $boards,
        private readonly DiaryPhotos $photos,
        private readonly DiaryCatalog $catalog,
        private readonly SecretVisibility $visibility,
    ) {}

    public function index(Request $request, string $slug): JsonResponse
    {
        $board = $this->boards->resolve($request, $slug);
        $cursor = $request->query('cursor');

        return $this->success('common.success', $this->photos->page($board, is_string($cursor) ? $cursor : null, $this->plan($request, (string) $board->slug), $request));
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function plan(Request $request, string $slug): array
    {
        $value = $request->query('diary_category');
        $byId = $this->catalog->categoriesById();
        if (! is_string($value) || preg_match('/^[1-9][0-9]{0,9}$/D', $value) !== 1 || ! isset($byId[(int) $value])) {
            return [];
        }
        $category = (int) $value;
        $viewer = Auth::id();

        return DiaryCategoryFilter::plan(
            $category,
            DiaryCategoryFilter::othersFor($category, $this->catalog->defaultCategoryId(), array_keys($byId)),
            $this->visibility->isBoardWide($slug),
            $viewer === null ? null : (int) $viewer
        );
    }
}
