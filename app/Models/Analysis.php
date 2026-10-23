<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Analysis extends Model
{
    /** @use HasFactory<\Database\Factories\AnalysisFactory> */
    use HasFactory;

    protected $fillable = ['name', 'period_start', 'period_end', 'data_count', 'model', 'status', 'created_by', 'data_origin', 'algorithm_version', 'dataset_hash', 'label_policy', 'parameters', 'snapshot', 'rules', 'evaluation'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'model' => 'array', 'parameters' => 'array', 'snapshot' => 'array', 'rules' => 'array', 'evaluation' => 'array'];
    }
}
