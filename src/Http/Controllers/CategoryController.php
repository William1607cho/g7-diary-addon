<?php

namespace Plugins\G7\Diary\Addon\Http\Controllers;

use App\Http\Controllers\Api\Base\PublicBaseController;
use Illuminate\Http\JsonResponse;
use Plugins\G7\Diary\Addon\Support\DiaryCategories;

/**
 * `GET /api/plugins/g7-diary-addon/categories`
 *
 * Categories that can be chosen, in display order. `is_default` marks the category used
 * for posts without one (and for posts whose category was deleted).
 */
class CategoryController extends PublicBaseController
{
    public function index(): JsonResponse
    {
        return $this->success('common.success', DiaryCategories::listActive());
    }
}
