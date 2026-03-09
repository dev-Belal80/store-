<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Validation\ValidationException;

class CategoryService
{
    public function __construct(private CacheService $cacheService) {}

    // CREATE
    public function create(array $data, int $storeId): Category
    {
        $exists = Category::withoutGlobalScopes()
            ->where('store_id', $storeId)
            ->where('name', $data['name'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'هذا التصنيف موجود بالفعل في متجرك.',
            ]);
        }

        $category = Category::create([
            'store_id' => $storeId,
            'name' => $data['name'],
        ]);

        $this->cacheService->invalidateCategories($storeId);

        return $category;
    }

    // UPDATE
    public function update(Category $category, array $data, int $storeId): Category
    {
        if ((int) $category->store_id !== $storeId) {
            abort(403);
        }

        $exists = Category::withoutGlobalScopes()
            ->where('store_id', $storeId)
            ->where('name', $data['name'])
            ->where('id', '!=', $category->id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'هذا التصنيف موجود بالفعل في متجرك.',
            ]);
        }

        $category->update(['name' => $data['name']]);
        $this->cacheService->invalidateCategories($storeId);

        return $category->fresh();
    }

    // LIST
    public function list(int $storeId): array
    {
        return Category::withoutGlobalScopes()
            ->where('store_id', $storeId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
            ])
            ->toArray();
    }

    // DELETE
    public function delete(Category $category, int $storeId): void
    {
        if ((int) $category->store_id !== $storeId) {
            abort(403);
        }

        if ($category->products()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'لا يمكن حذف تصنيف مرتبط بمنتجات.',
            ]);
        }

        $category->delete();
        $this->cacheService->invalidateCategories($storeId);
    }
}
