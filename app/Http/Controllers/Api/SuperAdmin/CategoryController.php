<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Category\StoreCategoryRequest;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CategoryController extends Controller
{
    public function __construct(private readonly CategoryService $categoryService) {}

    public function index(): JsonResponse
    {
        $storeId = Auth::user()->getStoreId();
        $categories = $this->categoryService->list($storeId);

        return response()->json(['data' => $categories]);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $storeId = Auth::user()->getStoreId();
        $category = $this->categoryService->create($request->validated(), $storeId);

        return response()->json([
            'message' => 'تم إنشاء التصنيف بنجاح.',
            'data' => [
                'id' => $category->id,
                'name' => $category->name,
            ],
        ], 201);
    }

    public function update(StoreCategoryRequest $request, Category $category): JsonResponse
    {
        $storeId = Auth::user()->getStoreId();
        $category = $this->categoryService->update($category, $request->validated(), $storeId);

        return response()->json([
            'message' => 'تم تحديث التصنيف بنجاح.',
            'data' => [
                'id' => $category->id,
                'name' => $category->name,
            ],
        ]);
    }

    public function destroy(Category $category): JsonResponse
    {
        $storeId = Auth::user()->getStoreId();
        $this->categoryService->delete($category, $storeId);

        return response()->json(['message' => 'تم حذف التصنيف بنجاح.']);
    }
}
