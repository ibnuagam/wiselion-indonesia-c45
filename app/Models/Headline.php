<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Headline extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['eyebrow', 'title', 'description', 'image_path', 'mobile_image_path', 'button_text', 'product_id', 'theme', 'sort_order', 'is_published', 'starts_on', 'ends_on'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeVisible(Builder $query): Builder
    {
        $today = now('Asia/Jakarta')->toDateString();

        return $query->where('is_published', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_on')->orWhereDate('starts_on', '<=', $today))
            ->where(fn (Builder $q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today));
    }

    public function getPublicationStatusAttribute(): string
    {
        $today = now('Asia/Jakarta')->toDateString();
        if (! $this->is_published) {
            return 'Draf';
        }
        if ($this->starts_on?->toDateString() > $today) {
            return 'Terjadwal';
        }
        if ($this->ends_on && $this->ends_on->toDateString() < $today) {
            return 'Selesai';
        }

        return 'Tayang';
    }
}
