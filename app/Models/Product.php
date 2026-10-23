<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory;
    use \Illuminate\Database\Eloquent\SoftDeletes;
    protected $fillable = ['category_id', 'code', 'name', 'price', 'description', 'is_active', 'photo_path', 'is_new', 'is_featured', 'sale_enabled', 'sale_price', 'sale_title', 'sale_starts_on', 'sale_ends_on', 'specifications', 'reference_price_max', 'price_note', 'catalog_source', 'source_metadata', 'catalog_imported_at', 'catalog_reviewed'];

    protected $hidden = ['source_metadata'];

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path ? asset('storage/'.$this->photo_path) : null;
    }
    protected function casts(): array { return ['price' => 'decimal:2', 'is_active' => 'boolean', 'is_new' => 'boolean', 'is_featured' => 'boolean', 'sale_enabled' => 'boolean', 'sale_price' => 'decimal:2', 'sale_starts_on' => 'date', 'sale_ends_on' => 'date', 'specifications' => 'array', 'reference_price_max' => 'decimal:2', 'source_metadata' => 'array', 'catalog_imported_at' => 'datetime', 'catalog_reviewed' => 'boolean']; }

    public function colors(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductColor::class)->orderBy('sort_order')->orderBy('id');
    }

    public function images(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function scopePublicCatalog(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true)->whereHas('category');
    }

    public function getIsOnSaleAttribute(): bool
    {
        $today = now('Asia/Jakarta')->toDateString();

        return $this->sale_enabled && $this->sale_price !== null && (float) $this->sale_price >= 0
            && (float) $this->sale_price < (float) $this->price
            && (! $this->sale_starts_on || $this->sale_starts_on->toDateString() <= $today)
            && (! $this->sale_ends_on || $this->sale_ends_on->toDateString() >= $today);
    }

    public function getDisplayPriceAttribute(): string
    {
        return $this->is_on_sale ? $this->sale_price : $this->price;
    }

    public function getDiscountPercentAttribute(): int
    {
        return $this->is_on_sale ? (int) floor((1 - (float) $this->sale_price / (float) $this->price) * 100) : 0;
    }

    public function getPriceGroupAttribute(): string
    {
        return match (true) {
            (float) $this->display_price < 250000 => 'Di bawah Rp250 ribu',
            (float) $this->display_price < 500000 => 'Rp250–500 ribu',
            default => 'Rp500 ribu ke atas',
        };
    }

    public static function catalogPriceSql(): string
    {
        return 'CASE WHEN sale_enabled = 1 AND sale_price IS NOT NULL AND sale_price >= 0 AND sale_price < price AND (sale_starts_on IS NULL OR DATE(sale_starts_on) <= ?) AND (sale_ends_on IS NULL OR DATE(sale_ends_on) >= ?) THEN sale_price ELSE price END';
    }
    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Category::class); }
    public function sales(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(Sale::class); }
}
