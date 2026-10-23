<?php

namespace Tests\Unit;

use App\Services\AnalysisService;
use App\Services\C45Service;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class C45ServiceTest extends TestCase
{
    private function row(float $units, string $label): array
    {
        return ['units_sold' => $units, 'order_frequency' => 0, 'unit_price' => 0, 'total_revenue' => 0, 'reference_label' => $label];
    }

    public function test_entropy_uses_unrounded_information_values(): void
    {
        $service = new C45Service;
        $this->assertSame(0.0, $service->entropy([]));
        $this->assertSame(0.0, $service->entropy(['Laris', 'Laris']));
        $this->assertEqualsWithDelta(1.0, $service->entropy(['Laris', 'Sedang']), 1.0e-12);
        $this->assertEqualsWithDelta(1.584962500721156, $service->entropy(C45Service::LABELS), 1.0e-12);
    }

    public function test_tree_learns_numeric_splits_mdl_gain_and_three_rules_from_labels(): void
    {
        $service = new C45Service;
        $rows = [];
        foreach ([5 => 'Kurang Laris', 50 => 'Sedang', 200 => 'Laris'] as $units => $label) {
            for ($i = 0; $i < 6; $i++) {
                $rows[] = $this->row($units, $label);
            }
        }

        $tree = $service->train($rows);

        $this->assertSame('split', $tree['type']);
        $this->assertSame('units_sold', $tree['feature']);
        $this->assertSame(27.5, $tree['threshold']);
        $this->assertEqualsWithDelta(0.9182958340544896, $tree['raw_gain'], 1.0e-12);
        $this->assertEqualsWithDelta(1 / 18, $tree['mdl_penalty'], 1.0e-12);
        $this->assertCount(3, $service->rules($tree));
        $this->assertSame('Kurang Laris', $service->predict($tree, $this->row(10, 'Laris'))['class']);
        $prediction = $service->predict($tree, $this->row(150, 'Kurang Laris'));
        $this->assertSame('Laris', $prediction['class']);
        $this->assertCount(2, $prediction['path']);
        $this->assertSame(6, $prediction['leaf_support']);
        $this->assertSame(1, $prediction['leaf_purity']);
        $this->assertSame($tree, $service->train(array_reverse($rows)));
        $changed = array_map(fn (array $row): array => [...$row, 'reference_label' => 'Sedang'], $rows);
        $this->assertSame('Sedang', $service->predict($service->train($changed), $this->row(150, 'Laris'))['class']);
    }

    public function test_noisy_low_value_split_is_pessimistically_pruned(): void
    {
        $rows = [];
        foreach ([0 => ['Laris', 'Laris', 'Laris', 'Laris', 'Sedang', 'Sedang'], 1 => ['Laris', 'Laris', 'Laris', 'Sedang', 'Sedang', 'Sedang']] as $units => $labels) {
            foreach ($labels as $label) {
                $rows[] = $this->row($units, $label);
            }
        }

        $tree = (new C45Service)->train($rows);

        $this->assertSame('leaf', $tree['type']);
        $this->assertSame('pessimistic_pruning', $tree['stop_reason']);
        $this->assertSame('Laris', $tree['class']);
        $this->assertGreaterThan(0, $tree['pruned_split']['gain']);
    }

    public function test_constant_features_stop_without_an_invalid_split(): void
    {
        $tree = (new C45Service)->train([$this->row(0, 'Laris'), $this->row(0, 'Laris'), $this->row(0, 'Sedang'), $this->row(0, 'Sedang')]);
        $this->assertSame('leaf', $tree['type']);
        $this->assertSame('no_positive_adjusted_gain', $tree['stop_reason']);
        $this->assertSame('Laris', $tree['class']);
    }

    public function test_missing_numeric_features_are_not_silently_imputed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new C45Service)->train([['reference_label' => 'Laris']]);
    }

    public function test_unlabelled_training_is_not_treated_as_reference_truth(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new C45Service)->train([$this->row(5, '')]);
    }

    public function test_non_finite_features_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new C45Service)->train([$this->row(INF, 'Laris')]);
    }

    public function test_metrics_match_an_independently_specified_confusion_matrix(): void
    {
        $rows = [];
        foreach ([['Laris', 'Laris'], ['Laris', 'Sedang'], ['Sedang', 'Sedang'], ['Kurang Laris', 'Laris'], [null, 'Sedang']] as [$actual, $predicted]) {
            $rows[] = ['reference_label' => $actual, 'prediction' => ['class' => $predicted]];
        }

        $metrics = (new AnalysisService(new C45Service))->evaluate($rows, 'Laris');

        $this->assertSame(4, $metrics['labelled_test_count']);
        $this->assertSame(1, $metrics['unlabelled_test_count']);
        $this->assertSame(0.5, $metrics['accuracy']);
        $this->assertSame(0.5, $metrics['majority_baseline_accuracy']);
        $this->assertSame(['Laris' => 1, 'Sedang' => 1, 'Kurang Laris' => 0], $metrics['confusion_matrix']['Laris']);
        $this->assertSame(0.5, $metrics['per_class']['Laris']['precision']);
        $this->assertSame(1, $metrics['per_class']['Sedang']['recall']);
        $this->assertNull($metrics['per_class']['Kurang Laris']['precision']);
        $this->assertSame(0, $metrics['per_class']['Kurang Laris']['f1']);
        $this->assertEqualsWithDelta(0.38888888888888884, $metrics['macro_f1'], 1.0e-12);
    }

    public function test_no_test_labels_means_no_accuracy_not_zero_or_a_fabricated_score(): void
    {
        $metrics = (new AnalysisService(new C45Service))->evaluate([['reference_label' => null, 'prediction' => ['class' => 'Laris']]], 'Laris');
        $this->assertSame('unavailable', $metrics['status']);
        $this->assertNull($metrics['accuracy']);
        $this->assertNull($metrics['macro_f1']);
    }
}
