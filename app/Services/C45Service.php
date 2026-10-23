<?php

namespace App\Services;

use InvalidArgumentException;

/** Numeric C4.5 adaptation: MDL-adjusted gain ratio and pessimistic subtree replacement; no subtree raising or missing-value weighting. */
class C45Service
{
    public const VERSION = 'wiselion-c45-numeric-1.0';
    public const LABELS = ['Laris', 'Sedang', 'Kurang Laris'];
    public const FEATURES = ['units_sold', 'order_frequency', 'unit_price', 'total_revenue'];
    public const FEATURE_NAMES = ['units_sold' => 'Unit terjual', 'order_frequency' => 'Frekuensi order', 'unit_price' => 'Harga satuan', 'total_revenue' => 'Total omset'];

    public function train(array $rows): array
    {
        if (count($rows) < 1 || count($rows) > 1000) {
            throw new InvalidArgumentException('Data latih harus berisi 1–1000 observasi lengkap.');
        }
        foreach ($rows as $row) {
            $this->validateFeatures($row);
            if (! in_array($row['reference_label'] ?? null, self::LABELS, true)) {
                throw new InvalidArgumentException('Setiap baris latih harus memiliki kelas acuan yang valid.');
            }
        }

        return $this->build(array_values($rows), 0);
    }

    public function predict(array $tree, array $row): array
    {
        $this->validateFeatures($row);
        $path = [];
        while ($tree['type'] === 'split') {
            $left = (float) $row[$tree['feature']] <= $tree['threshold'];
            $path[] = ['feature' => $tree['feature'], 'operator' => $left ? '<=' : '>', 'threshold' => $tree['threshold']];
            $tree = $tree[$left ? 'left' : 'right'];
        }

        return ['class' => $tree['class'], 'path' => $path, 'leaf_support' => $tree['count'],
            'leaf_purity' => $tree['distribution'][$tree['class']] / $tree['count']];
    }

    public function rules(array $tree, array $path = []): array
    {
        if ($tree['type'] === 'leaf') {
            return [['conditions' => $path, 'class' => $tree['class'], 'support' => $tree['count'], 'distribution' => $tree['distribution']]];
        }
        $condition = ['feature' => $tree['feature'], 'threshold' => $tree['threshold']];

        return [...$this->rules($tree['left'], [...$path, [...$condition, 'operator' => '<=']]),
            ...$this->rules($tree['right'], [...$path, [...$condition, 'operator' => '>']])];
    }

    public function entropy(array $labels): float
    {
        return $this->entropyCounts(array_count_values($labels));
    }

    private function entropyCounts(array $counts): float
    {
        $total = array_sum($counts);
        $entropy = 0.0;
        foreach ($counts as $count) {
            if ($count > 0) {
                $probability = $count / $total;
                $entropy -= $probability * log($probability, 2);
            }
        }

        return $entropy;
    }

    private function validateFeatures(array $row): void
    {
        foreach (self::FEATURES as $feature) {
            if (! isset($row[$feature]) || ! is_numeric($row[$feature]) || ! is_finite((float) $row[$feature]) || (float) $row[$feature] < 0) {
                throw new InvalidArgumentException('Fitur numerik lengkap dan nonnegatif wajib tersedia: '.$feature);
            }
        }
    }

    private function build(array $rows, int $depth): array
    {
        $distribution = array_replace(array_fill_keys(self::LABELS, 0), array_count_values(array_column($rows, 'reference_label')));
        $majority = self::LABELS[0];
        foreach (self::LABELS as $label) {
            if ($distribution[$label] > $distribution[$majority]) {
                $majority = $label;
            }
        }
        $count = count($rows);
        $errors = $count - $distribution[$majority];
        $node = ['type' => 'leaf', 'class' => $majority, 'count' => $count, 'distribution' => $distribution,
            'entropy' => $this->entropyCounts($distribution), 'estimated_errors' => $this->upperErrors($count, $errors), 'depth' => $depth];
        if ($errors === 0 || $count < 4 || $depth >= 12) {
            return [...$node, 'stop_reason' => $errors === 0 ? 'pure' : 'minimum_leaf_or_depth'];
        }
        $candidates = [];
        foreach (self::FEATURES as $feature) {
            $candidate = $this->bestThreshold($rows, $feature, $distribution);
            if ($candidate !== null) {
                $candidates[] = $candidate;
            }
        }
        $node['candidates'] = $candidates;
        $positive = array_values(array_filter($candidates, fn (array $candidate): bool => $candidate['gain'] > 1.0e-10));
        if ($positive === []) {
            return [...$node, 'stop_reason' => 'no_positive_adjusted_gain'];
        }
        $averageGain = array_sum(array_column($positive, 'gain')) / count($positive);
        $eligible = array_values(array_filter($positive, fn (array $candidate): bool => $candidate['gain'] + 1.0e-10 >= $averageGain));
        usort($eligible, fn (array $a, array $b): int => ($b['gain_ratio'] <=> $a['gain_ratio']) ?: (array_search($a['feature'], self::FEATURES, true) <=> array_search($b['feature'], self::FEATURES, true)));
        $best = $eligible[0];
        $leftRows = $rightRows = [];
        foreach ($rows as $row) {
            if ((float) $row[$best['feature']] <= $best['threshold']) {
                $leftRows[] = $row;
            } else {
                $rightRows[] = $row;
            }
        }
        $left = $this->build($leftRows, $depth + 1);
        $right = $this->build($rightRows, $depth + 1);
        $subtreeErrors = $left['estimated_errors'] + $right['estimated_errors'];
        if ($node['estimated_errors'] <= $subtreeErrors + 1.0e-10) {
            return [...$node, 'stop_reason' => 'pessimistic_pruning', 'pruned_split' => $best];
        }

        return [...$node, ...$best, 'type' => 'split', 'average_gain' => $averageGain, 'estimated_errors' => $subtreeErrors, 'left' => $left, 'right' => $right];
    }

    private function bestThreshold(array $rows, string $feature, array $distribution): ?array
    {
        usort($rows, fn (array $a, array $b): int => (float) $a[$feature] <=> (float) $b[$feature]);
        $count = count($rows);
        $left = array_fill_keys(self::LABELS, 0);
        $right = $distribution;
        $best = null;
        $trials = 0;
        $entropy = $this->entropyCounts($distribution);
        for ($i = 0; $i < $count - 1; $i++) {
            $label = $rows[$i]['reference_label'];
            $left[$label]++;
            $right[$label]--;
            $size = $i + 1;
            if ($size < 2 || $count - $size < 2 || (float) $rows[$i][$feature] === (float) $rows[$i + 1][$feature]) {
                continue;
            }
            $trials++;
            $gain = $entropy - $size / $count * $this->entropyCounts($left) - ($count - $size) / $count * $this->entropyCounts($right);
            if ($best === null || $gain > $best['raw_gain'] + 1.0e-10) {
                $best = ['feature' => $feature, 'threshold' => ((float) $rows[$i][$feature] + (float) $rows[$i + 1][$feature]) / 2,
                    'raw_gain' => $gain, 'split_info' => $this->entropyCounts([$size, $count - $size])];
            }
        }
        if ($best === null) {
            return null;
        }
        $penalty = log(max(1, $trials), 2) / $count;
        $gain = $best['raw_gain'] - $penalty;

        return [...$best, 'threshold_trials' => $trials, 'mdl_penalty' => $penalty, 'gain' => $gain, 'gain_ratio' => $gain / $best['split_info']];
    }

    /** One-sided 75% upper error estimate with continuity correction (CF 0.25); subtree replacement only. */
    private function upperErrors(int $count, int $errors): float
    {
        if ($errors === 0) {
            return $count * (1 - pow(0.25, 1 / $count));
        }
        $z = 0.6744897501960817;
        $p = min(1, ($errors + 0.5) / $count);
        $upper = ($p + $z * $z / (2 * $count) + $z * sqrt($p * (1 - $p) / $count + $z * $z / (4 * $count * $count))) / (1 + $z * $z / $count);

        return $count * min(1, $upper);
    }
}
