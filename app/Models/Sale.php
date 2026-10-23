<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    /** @use HasFactory<\Database\Factories\SaleFactory> */
    use HasFactory;
    use \Illuminate\Database\Eloquent\SoftDeletes;
    protected $fillable = ['product_id', 'month', 'year', 'units_sold', 'order_frequency', 'unit_price', 'total_revenue', 'data_origin', 'reference_label', 'label_basis', 'labelled_by', 'labelled_at', 'sales_import_id'];
    protected function casts(): array { return ['unit_price' => 'decimal:2', 'total_revenue' => 'decimal:2', 'labelled_at' => 'datetime']; }
    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Product::class); }

    public function scopeFiltered(\Illuminate\Database\Eloquent\Builder $query, array $filters): \Illuminate\Database\Eloquent\Builder
    {
        return $query->when($filters['product_id'] ?? null, fn ($q, $value) => $q->where('product_id', $value))
            ->when($filters['category_id'] ?? null, fn ($q, $value) => $q->whereHas('product', fn ($p) => $p->where('category_id', $value)))
            ->when($filters['start'] ?? null, function ($q, string $value): void {
                [$year, $month] = array_map('intval', explode('-', $value));
                $q->whereRaw('(year * 12 + month) >= ?', [$year * 12 + $month]);
            })
            ->when($filters['end'] ?? null, function ($q, string $value): void {
                [$year, $month] = array_map('intval', explode('-', $value));
                $q->whereRaw('(year * 12 + month) <= ?', [$year * 12 + $month]);
            });
    }
}
