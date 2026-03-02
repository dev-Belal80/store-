<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Category\StoreCategoryRequest;
use App\Http\Requests\Api\V1\Product\StoreProductRequest;
use App\Http\Requests\Api\V1\Product\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Services\CacheService;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function __construct(
        private ProductService $productService,
        private CacheService $cacheService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = $this->resolvePerPage($request, 10, 10);
        $withTotal = $request->boolean('with_total', false);
        $storeId = Auth::user()->getStoreId();

        $productsQuery = Product::query()
            ->with('category:id,name')
            ->when(
                $request->filled('search'),
                fn($q) => $q->where(function ($query) use ($request) {
                    $query->where('name', 'like', '%' . $request->search . '%')
                          ->orWhere('sku', 'like', '%' . $request->search . '%');
                })
            )
            ->when($request->filled('category_id'), fn($q) => $q->where('category_id', $request->category_id))
            ->orderBy('name');

        $products = ($withTotal
            ? $productsQuery->paginate($perPage)
            : $productsQuery->simplePaginate($perPage))
            ->withQueryString();

        $productIds = $products->getCollection()->pluck('id')->all();

        $stockTotals = empty($productIds)
            ? collect()
            : DB::table('stock_movements')
                ->selectRaw('product_id')
                ->selectRaw("COALESCE(SUM(CASE WHEN type = 'in' THEN quantity ELSE 0 END), 0) AS stock_in")
                ->selectRaw("COALESCE(SUM(CASE WHEN type = 'out' THEN quantity ELSE 0 END), 0) AS stock_out")
                ->where('store_id', $storeId)
                ->whereIntegerInRaw('product_id', $productIds)
                ->groupBy('product_id')
                ->get()
                ->keyBy('product_id');

        $products->setCollection(
            $products->getCollection()->map(function (Product $product) use ($stockTotals) {
                $stock = $stockTotals->get($product->id);
                $stockIn = (float) ($stock->stock_in ?? 0);
                $stockOut = (float) ($stock->stock_out ?? 0);
                $currentStock = round($stockIn - $stockOut, 3);

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'category' => $product->category?->name,
                    'unit' => $product->unit,
                    'sale_price' => $product->sale_price,
                    'current_stock' => $currentStock,
                    'low_stock_threshold' => $product->low_stock_threshold,
                    'is_low_stock' => $product->low_stock_threshold > 0 && $currentStock <= $product->low_stock_threshold,
                ];
            })
        );

        $payload = $products->toArray();
        $payload['products_count'] = $this->cacheService->getProductsCount($storeId);

        return response()->json($payload);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->productService->create(
            $request->validated(),
            Auth::user()->getStoreId()
        );

        return response()->json([
            'message' => 'تم إضافة المنتج بنجاح.',
            'product' => $product,
        ], 201);
    }

    public function update(UpdateProductRequest $request, int $id): JsonResponse
    {
        $product = $this->productService->update($id, $request->validated());

        return response()->json([
            'message' => 'تم تعديل المنتج.',
            'product' => $product,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->productService->delete($id);

        return response()->json(['message' => 'تم حذف المنتج.']);
    }

    // ── Categories ───────────────────────────────────────────────

    public function categories(Request $request): JsonResponse
    {
        $perPage = $this->resolvePerPage($request, 25);
        $storeId = Auth::user()->getStoreId();

        if (! $request->filled('search')) {
            $allCategories = collect($this->cacheService->getCategories($storeId));
            $page = max((int) $request->query('page', 1), 1);
            $total = $allCategories->count();
            $items = $allCategories->forPage($page, $perPage)->values();

            $paginator = new LengthAwarePaginator(
                items: $items,
                total: $total,
                perPage: $perPage,
                currentPage: $page,
                options: [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ],
            );

            return response()->json($paginator);
        }

        $categories = Category::query()
            ->select(['id', 'store_id', 'name', 'products_count', 'created_at', 'updated_at'])
            ->withCount(['products as products_count' => fn($q) => $q->whereNull('products.deleted_at')])
            ->when($request->filled('search'), fn($q) => $q->where('name', 'like', '%' . $request->search . '%'))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json($categories);
    }

    public function categoriesSummary(): JsonResponse
    {
        $storeId = Auth::user()->getStoreId();

        return response()->json([
            'categories' => $this->cacheService->getCategories($storeId),
        ]);
    }

    public function storeCategory(StoreCategoryRequest $request): JsonResponse
    {
        $storeId = Auth::user()->getStoreId();

        $category = Category::create([
            'store_id' => $storeId,
            'name'     => $request->name,
        ]);

        $this->cacheService->invalidateCategories($storeId);

        return response()->json([
            'message'  => 'تم إضافة التصنيف.',
            'category' => $category,
        ], 201);
    }

    public function destroyCategory(int $id): JsonResponse
    {
        $storeId = Auth::user()->getStoreId();

        $this->productService->deleteCategory($id, $storeId);
        $this->cacheService->invalidateCategories($storeId);

        return response()->json(['message' => 'تم حذف التصنيف.']);
    }
}
