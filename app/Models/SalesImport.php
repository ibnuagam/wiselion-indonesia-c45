<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesImport extends Model
{
    /** @use HasFactory<\Database\Factories\SalesImportFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'source_name', 'checksum', 'data_origin', 'status', 'rows', 'errors', 'row_count', 'committed_at'];

    protected function casts(): array
    {
        return ['rows' => 'array', 'errors' => 'array', 'committed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
