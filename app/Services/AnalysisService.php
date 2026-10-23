<?php

namespace App\Services;

use App\Models\Analysis;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AnalysisService
{
    public function __construct(private C45Service $c45) {}

    public function run(array $input, User $user): Analysis
    {
        $sales = Sale::query()->where('data_origin', $input['data_origin'])->filtered(['start' => $input['start'], 'end' => $input['end']])
            ->with(['product' => fn ($query) => $query->withTrashed()])->orderBy('year')->orderBy('month')->orderBy('product_id')->limit(1001)->get();
        if ($sales->count() > 1000) {
            throw ValidationException::withMessages(['dataset' => 'Maksimal 1000 observasi per analisis. Persempit rentang bulan.']);
        }
        $snapshot = $sales->map(function (Sale $sale) use ($input): array {
            $period = sprintf('%04d-%02d', $sale->year, $sale->month);
            return ['sale_id' => $sale->id, 'product_id' => $sale->product_id, 'product_code' => $sale->product->code, 'product_name' => $sale->product->name,
                'period' => $period, 'units_sold' => (int) $sale->units_sold, 'order_frequency' => (int) $sale->order_frequency,
                'unit_price' => $sale->unit_price, 'total_revenue' => $sale->total_revenue, 'data_origin' => $sale->data_origin,
                'reference_label' => $sale->reference_label, 'label_basis' => $sale->label_basis, 'labelled_by' => $sale->labelled_by, 'labelled_at' => $sale->labelled_at?->toIso8601String(),
                'partition' => $period >= $input['test_start'] ? 'testing' : ($sale->reference_label ? 'training' : 'excluded_unlabelled_training')];
        })->all();
        $training = array_values(array_filter($snapshot, fn (array $row): bool => $row['partition'] === 'training'));
        $testing = array_values(array_filter($snapshot, fn (array $row): bool => $row['partition'] === 'testing'));
        $counts = array_count_values(array_column($training, 'reference_label'));
        foreach (C45Service::LABELS as $label) {
            if (($counts[$label] ?? 0) < 2) {
                throw ValidationException::withMessages(['dataset' => 'Data latih belum cukup: perlu minimal 2 observasi berlabel per kelas (Laris, Sedang, Kurang Laris), sebelum bulan uji. Data legacy dan asal data lain tidak dicampur.']);
            }
        }
        if ($testing === []) {
            throw ValidationException::withMessages(['dataset' => 'Belum ada observasi pada rentang bulan uji. Tidak ada hasil atau akurasi yang dibuat.']);
        }
        $tree = $this->c45->train($training);
        foreach ($snapshot as &$row) {
            if ($row['partition'] === 'testing') {
                $row['prediction'] = $this->c45->predict($tree, $row);
            }
        }
        unset($row);
        $evaluation = $this->evaluate(array_values(array_filter($snapshot, fn (array $row): bool => $row['partition'] === 'testing')), $tree['class']);

        return Analysis::create(['name' => $input['name'], 'period_start' => $input['start'].'-01', 'period_end' => $input['end'].'-01',
            'data_count' => count($snapshot), 'status' => 'completed', 'created_by' => $user->id, 'data_origin' => $input['data_origin'],
            'algorithm_version' => C45Service::VERSION, 'model' => $tree, 'snapshot' => $snapshot,
            'dataset_hash' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)), 'label_policy' => $input['label_policy'],
            'rules' => $this->c45->rules($tree), 'evaluation' => $evaluation,
            'parameters' => ['unit' => 'product_month', 'features' => C45Service::FEATURES, 'test_start' => $input['test_start'],
                'training_count' => count($training), 'testing_count' => count($testing), 'excluded_count' => count($snapshot) - count($training) - count($testing),
                'max_depth' => 12, 'min_leaf' => 2, 'confidence_factor' => 0.25, 'numeric_mdl' => true, 'pruning' => 'pessimistic_subtree_replacement',
                'subtree_raising' => false, 'missing_values' => 'reject', 'split' => 'chronological_month_holdout']]);
    }

    public function evaluate(array $rows, string $baseline): array
    {
        $matrix = array_fill_keys(C45Service::LABELS, array_fill_keys(C45Service::LABELS, 0));
        $count = $correct = $baselineCorrect = 0;
        foreach ($rows as $row) {
            if (! in_array($row['reference_label'], C45Service::LABELS, true)) {
                continue;
            }
            $actual = $row['reference_label'];
            $predicted = $row['prediction']['class'];
            $matrix[$actual][$predicted]++;
            $count++;
            $correct += (int) ($actual === $predicted);
            $baselineCorrect += (int) ($actual === $baseline);
        }
        $perClass = [];
        foreach (C45Service::LABELS as $label) {
            $tp = $matrix[$label][$label];
            $support = array_sum($matrix[$label]);
            $predictedCount = array_sum(array_column($matrix, $label));
            $perClass[$label] = ['support' => $support, 'precision' => $predictedCount ? $tp / $predictedCount : null,
                'recall' => $support ? $tp / $support : null, 'f1' => $support ? 2 * $tp / ($support + $predictedCount) : null];
        }
        $supported = array_filter($perClass, fn (array $row): bool => $row['support'] > 0);

        return ['status' => $count ? 'evaluated' : 'unavailable', 'reason' => $count ? null : 'Belum ada kelas acuan pada observasi uji; akurasi tidak dapat dihitung.',
            'labelled_test_count' => $count, 'unlabelled_test_count' => count($rows) - $count,
            'accuracy' => $count ? $correct / $count : null, 'majority_baseline_accuracy' => $count ? $baselineCorrect / $count : null,
            'baseline_class' => $baseline, 'macro_f1' => $supported ? array_sum(array_column($supported, 'f1')) / count($supported) : null,
            'macro_supported_classes' => count($supported), 'confusion_matrix' => $matrix, 'per_class' => $perClass];
    }
}
