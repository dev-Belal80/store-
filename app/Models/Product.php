<?php

namespace App\Models;

use App\Services\CacheService;
use App\Models\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use BelongsToStore, SoftDeletes;

    protected $fillable = [
        'store_id',
        'category_id',
        'name',
        'sku',
        'unit',
        'purchase_price',
        'sale_price',
        'low_stock_threshold',
    ];

    protected $casts = [
        'purchase_price'     => 'float',
        'sale_price'         => 'float',
        'low_stock_threshold' => 'integer',
    ];

    // ── Relationships ────────────────────────────────────────────

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    // ── Stock Helpers ────────────────────────────────────────────

    /**
     * المخزون الحالي = SUM(in) - SUM(out) من stock_movements
     */
    public function getCurrentStockAttribute(): float
    {
        return app(CacheService::class)->getStock((int) $this->store_id, (int) $this->id);
    }

    /**
     * هل يمكن بيع الكمية المطلوبة؟
     */
    public function canSell(float $quantity): bool
    {
        return $this->current_stock >= $quantity;
    }

    /**
     * هل المخزون منخفض (أقل من أو يساوي حد التنبيه)؟
     */
    public function isLowStock(): bool
    {
        if ($this->low_stock_threshold <= 0) {
            return false;
        }

        return $this->current_stock <= $this->low_stock_threshold;
    }
}
