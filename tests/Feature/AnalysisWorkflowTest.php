<?php

namespace Tests\Feature;

use App\Models\Analysis;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\C45Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AnalysisWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function input(array $overrides = []): array
    {
        return array_replace(['name' => 'Uji fungsi C4.5', 'start' => '2025-01', 'end' => '2025-02', 'test_start' => '2025-02', 'data_origin' => 'real',
            'label_policy' => 'Label manual pakar dalam dataset khusus pengujian, bukan data bisnis nyata.', 'confirmed' => 1], $overrides);
    }

    private function dataset(string $origin = 'real', bool $testLabels = true): void
    {
        foreach ([5 => 'Kurang Laris', 50 => 'Sedang', 200 => 'Laris'] as $units => $label) {
            for ($i = 0; $i < 6; $i++) {
                $product = Product::factory()->create();
                $data = ['year' => 2025, 'units_sold' => $units, 'order_frequency' => $units, 'unit_price' => 100000,
                    'total_revenue' => $units * 100000, 'data_origin' => $origin, 'reference_label' => $label, 'label_basis' => 'Penilaian manual pakar pada fixture tes'];
                Sale::factory()->for($product)->create([...$data, 'month' => 1]);
                if ($i === 0) {
                    Sale::factory()->for($product)->create([...$data, 'month' => 2, 'reference_label' => $testLabels ? $label : null]);
                }
            }
        }
    }

    public function test_empty_dataset_cannot_create_a_fake_model_or_accuracy(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get(route('analyses.index'))->assertOk()->assertSee('Belum ada analisis.');
        $this->post(route('analyses.store'), $this->input())->assertSessionHasErrors('dataset');
        $this->assertDatabaseCount('analyses', 0);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_analysis_persists_tree_rules_chronological_snapshot_and_heldout_evaluation(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->dataset();
        $this->post(route('analyses.store'), $this->input())->assertSessionHasNoErrors()->assertRedirect();
        $analysis = Analysis::sole();
        $this->assertSame(C45Service::VERSION, $analysis->algorithm_version);
        $this->assertSame('completed', $analysis->status);
        $this->assertSame(18, $analysis->parameters['training_count']);
        $this->assertSame(3, $analysis->parameters['testing_count']);
        $this->assertCount(21, $analysis->snapshot);
        $this->assertCount(3, $analysis->rules);
        $this->assertSame('split', $analysis->model['type']);
        $this->assertSame(1, $analysis->evaluation['accuracy']);
        $this->assertSame(3, $analysis->evaluation['labelled_test_count']);
        $this->assertSame(1, $analysis->evaluation['confusion_matrix']['Laris']['Laris']);
        $this->assertSame(0, $analysis->evaluation['confusion_matrix']['Laris']['Sedang']);
        $this->assertSame(hash('sha256', json_encode($analysis->snapshot, JSON_THROW_ON_ERROR)), $analysis->dataset_hash);
        $training = array_filter($analysis->snapshot, fn (array $row): bool => $row['partition'] === 'training');
        $this->assertSame(['2025-01'], array_values(array_unique(array_column($training, 'period'))));
        $this->get(route('analyses.show', $analysis))->assertOk()->assertSee('Pohon keputusan C4.5')->assertSee('Confusion matrix')->assertSee('Aturan klasifikasi');
        $download = $this->get(route('analyses.download', $analysis))->assertDownload('wiselion-analysis-'.$analysis->id.'.json');
        $this->assertSame($analysis->snapshot, json_decode($download->streamedContent(), true, 512, JSON_THROW_ON_ERROR)['snapshot']);
    }

    public function test_changing_test_labels_does_not_change_the_learned_tree(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->dataset();
        $this->post(route('analyses.store'), $this->input())->assertSessionHasNoErrors();
        $first = Analysis::sole();
        Sale::where('month', 2)->update(['reference_label' => 'Laris']);
        $this->post(route('analyses.store'), $this->input(['name' => 'Label uji berubah']))->assertSessionHasNoErrors();
        $second = Analysis::latest('id')->first();
        $this->assertSame($first->model, $second->model);
        $this->assertEqualsWithDelta(1 / 3, $second->evaluation['accuracy'], 1.0e-12);
        $this->assertNotSame($first->dataset_hash, $second->dataset_hash);
        $this->assertSame($first->snapshot, $first->fresh()->snapshot);
    }

    public function test_unlabelled_test_rows_can_be_classified_but_have_no_fabricated_accuracy(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->dataset(testLabels: false);
        $this->post(route('analyses.store'), $this->input())->assertSessionHasNoErrors();
        $analysis = Analysis::sole();
        $this->assertNull($analysis->evaluation['accuracy']);
        $this->assertSame('unavailable', $analysis->evaluation['status']);
        $this->assertSame(3, $analysis->evaluation['unlabelled_test_count']);
        $this->get(route('analyses.show', $analysis))->assertOk()->assertSee('Belum dapat dihitung');
    }

    #[TestWith(['simulation'])]
    #[TestWith(['legacy'])]
    public function test_other_data_origins_cannot_train_a_real_model(string $origin): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->dataset($origin);
        $this->post(route('analyses.store'), $this->input())->assertSessionHasErrors('dataset');
        $this->assertDatabaseCount('analyses', 0);
        if ($origin === 'simulation') {
            $this->post(route('analyses.store'), $this->input(['data_origin' => 'simulation']))->assertSessionHasNoErrors();
            $this->get(route('analyses.show', Analysis::sole()))->assertOk()->assertSee('DATA SIMULASI');
        }
    }

    public function test_missing_reference_class_insufficient_samples_and_empty_test_window_are_blocked(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->dataset();
        Sale::where('month', 1)->where('reference_label', 'Laris')->update(['reference_label' => null]);
        $this->post(route('analyses.store'), $this->input())->assertSessionHasErrors('dataset');
        Sale::where('month', 1)->whereNull('reference_label')->update(['reference_label' => 'Laris']);
        Sale::where('month', 2)->delete();
        $this->post(route('analyses.store'), $this->input())->assertSessionHasErrors('dataset');
        $this->assertDatabaseCount('analyses', 0);
    }

    public function test_source_edits_and_archives_do_not_rewrite_saved_results(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->dataset();
        $this->post(route('analyses.store'), $this->input(['name' => '<script>bad()</script>']))->assertSessionHasNoErrors();
        $analysis = Analysis::sole();
        $snapshot = $analysis->snapshot;
        $sale = Sale::where('month', 2)->first();
        $sale->product->update(['name' => 'New catalogue title']);
        $sale->update(['units_sold' => 999]);
        $sale->delete();
        $this->get(route('analyses.show', $analysis))->assertOk()->assertDontSee('<script>bad()</script>', false)->assertSee('&lt;script&gt;bad()&lt;/script&gt;', false)->assertDontSee('New catalogue title');
        $this->assertSame($snapshot, $analysis->fresh()->snapshot);
    }

    #[TestWith(['test_start', '2025-01'])]
    #[TestWith(['test_start', '2025-03'])]
    #[TestWith(['data_origin', 'legacy'])]
    #[TestWith(['label_policy', ''])]
    #[TestWith(['confirmed', 0])]
    public function test_bad_analysis_parameters_fail_before_running(string $field, mixed $value): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->post(route('analyses.store'), $this->input([$field => $value]))->assertSessionHasErrors($field);
        $this->assertDatabaseCount('analyses', 0);
    }

    public function test_manager_can_read_results_but_cannot_execute_or_edit_reference_labels(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->dataset();
        $this->post(route('analyses.store'), $this->input())->assertSessionHasNoErrors();
        $this->actingAs(User::factory()->create(['role' => 'manager']));
        $analysis = Analysis::sole();
        $this->get(route('analyses.index'))->assertOk()->assertSee('Akses Manager');
        $this->get(route('analyses.show', $analysis))->assertOk();
        $this->get(route('analyses.download', $analysis))->assertOk();
        $this->post(route('analyses.store'), $this->input())->assertForbidden();
        $this->assertDatabaseCount('analyses', 1);
    }

    public function test_public_and_customer_cannot_access_model_datasets(): void
    {
        $this->get(route('analyses.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'pelanggan']));
        $analysis = Analysis::factory()->create();
        $this->get(route('analyses.index'))->assertForbidden();
        $this->get(route('analyses.show', $analysis))->assertForbidden();
        $this->get(route('analyses.download', $analysis))->assertForbidden();
        $this->post(route('analyses.store'), $this->input())->assertForbidden();
    }

    public function test_manual_reference_labels_require_a_basis_and_record_the_admin_audit(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        $product = Product::factory()->create();
        $data = ['product_id' => $product->id, 'year' => 2025, 'month' => 1, 'units_sold' => 10, 'order_frequency' => 5, 'unit_price' => 100000, 'confirmed' => 1, 'reference_label' => 'Sedang', 'data_origin' => 'real'];
        $this->post(route('sales.store'), $data)->assertSessionHasErrors('label_basis');
        $this->post(route('sales.store'), [...$data, 'label_basis' => 'Pedoman bisnis disetujui pakar.', 'labelled_by' => 999])->assertSessionHasNoErrors();
        $sale = Sale::sole();
        $this->assertSame($user->id, $sale->labelled_by);
        $this->assertSame('Sedang', $sale->reference_label);
        $this->get(route('sales.edit', $sale))->assertOk()->assertSee('Asal data & kelas acuan', false);
        $this->put(route('sales.update', $sale), [...$data, 'reference_label' => '', 'label_basis' => ''])->assertSessionHasNoErrors();
        $this->assertNull($sale->fresh()->reference_label);
        $this->assertNull($sale->fresh()->labelled_at);
    }

    public function test_archived_rows_are_excluded_but_unlabelled_training_rows_are_audited(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->dataset();
        $unlabelled = Sale::factory()->create(['year' => 2025, 'month' => 1, 'data_origin' => 'real', 'reference_label' => null]);
        $archived = Sale::factory()->create(['year' => 2025, 'month' => 1, 'data_origin' => 'real', 'reference_label' => 'Laris']);
        $archived->delete();
        $this->post(route('analyses.store'), $this->input())->assertSessionHasNoErrors();
        $analysis = Analysis::sole();
        $this->assertSame(1, $analysis->parameters['excluded_count']);
        $this->assertSame(18, $analysis->parameters['training_count']);
        $this->assertCount(22, $analysis->snapshot);
        $ids = array_column($analysis->snapshot, 'sale_id');
        $this->assertContains($unlabelled->id, $ids);
        $this->assertNotContains($archived->id, $ids);
    }
}
