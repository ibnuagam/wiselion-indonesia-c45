<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SalesImport;
use App\Models\User;
use App\Services\SalesImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class SalesImportTest extends TestCase
{
    use RefreshDatabase;

    private function upload(string $content, string $origin = 'real'): void
    {
        $this->post(route('sales-imports.store'), ['file' => UploadedFile::fake()->createWithContent('sales.csv', $content), 'data_origin' => $origin, 'confirmed' => 1])->assertRedirect();
    }

    private function csv(string $rows): string
    {
        return implode(',', SalesImportService::HEADERS)."\n".$rows;
    }

    public function test_admin_template_contains_headers_only_and_empty_upload_is_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get(route('sales-imports.index'))->assertOk()->assertSee('Tidak ada data contoh');
        $response = $this->get(route('sales-imports.template'))->assertDownload('template-penjualan-wiselion.csv');
        $this->assertSame(implode(',', SalesImportService::HEADERS)."\n", $response->streamedContent());
        $this->post(route('sales-imports.store'), ['file' => UploadedFile::fake()->createWithContent('empty.csv', $this->csv('')), 'data_origin' => 'real', 'confirmed' => 1])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('sales_imports', 0);
    }

    public function test_preview_writes_no_sales_commit_is_atomic_and_idempotent(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        Product::factory()->create(['code' => 'WL-TEST']);
        $this->upload($this->csv("WL-TEST,2025-01,3,2,123456.78,Laris,Penilaian manual pakar\nWL-TEST,2025-02,0,0,123456.78,,\n"));
        $batch = SalesImport::sole();
        $this->assertSame([], $batch->errors);
        $this->assertDatabaseCount('sales', 0);
        $this->get(route('sales-imports.show', $batch))->assertOk()->assertSee('Pratinjau valid');
        $this->post(route('sales-imports.commit', $batch), ['confirmed' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('sales', 2);
        $sale = Sale::where('month', 1)->sole();
        $this->assertSame('370370.34', $sale->total_revenue);
        $this->assertSame('real', $sale->data_origin);
        $this->assertSame('Laris', $sale->reference_label);
        $this->assertSame($user->id, $sale->labelled_by);
        $this->assertNotNull($sale->labelled_at);
        $this->post(route('sales-imports.commit', $batch), ['confirmed' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('sales', 2);
        $this->assertSame('committed', $batch->fresh()->status);
    }

    #[TestWith(['MISSING,2025-01,2,1,100000,Laris,Pakar'])]
    #[TestWith(['WL-TEST,2025-13,2,1,100000,Laris,Pakar'])]
    #[TestWith(['WL-TEST,2025-01,-2,1,100000,Laris,Pakar'])]
    #[TestWith(['WL-TEST,2025-01,0,1,100000,Laris,Pakar'])]
    #[TestWith(['WL-TEST,2025-01,2,3,100000,Laris,Pakar'])]
    #[TestWith(['WL-TEST,2025-01,2,1,1e5,Laris,Pakar'])]
    #[TestWith(['WL-TEST,2025-01,2,1,100000.123,Laris,Pakar'])]
    #[TestWith(['WL-TEST,2025-01,2,1,100000,Invalid,Pakar'])]
    #[TestWith(['WL-TEST,2025-01,2,1,100000,Laris,'])]
    #[TestWith(['WL-TEST,2025-01,2,1,100000'])]
    public function test_invalid_csv_rows_block_the_entire_batch(string $invalidRow): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Product::factory()->create(['code' => 'WL-TEST']);
        $this->upload($this->csv("WL-TEST,2025-03,2,1,100000,,\n".$invalidRow."\n"));
        $batch = SalesImport::sole();
        $this->assertNotEmpty($batch->errors);
        $this->post(route('sales-imports.commit', $batch), ['confirmed' => 1])->assertSessionHasErrors('import');
        $this->assertDatabaseCount('sales', 0);
        $this->assertSame('preview', $batch->fresh()->status);
    }

    public function test_duplicate_file_rows_existing_archives_and_intervening_writes_are_not_overwritten(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $product = Product::factory()->create(['code' => 'WL-TEST']);
        $this->upload($this->csv("WL-TEST,2025-01,2,1,100000,,\nWL-TEST,2025-01,2,1,100000,,\n"));
        $this->assertNotEmpty(SalesImport::latest('id')->first()->errors);
        $this->upload($this->csv("WL-TEST,2025-01,2,1,100000,,\nWL-TEST,2025-02,2,1,100000,,\n"));
        $batch = SalesImport::latest('id')->first();
        $this->assertSame([], $batch->errors);
        $existing = Sale::factory()->for($product)->create(['year' => 2025, 'month' => 2]);
        $existing->delete();
        $this->post(route('sales-imports.commit', $batch), ['confirmed' => 1])->assertSessionHasErrors('import');
        $this->assertDatabaseCount('sales', 1);
        $this->assertSoftDeleted($existing);
        $this->assertDatabaseMissing('sales', ['product_id' => $product->id, 'year' => 2025, 'month' => 1]);
    }

    public function test_bom_semicolon_and_utf8_are_supported_without_inventing_labels(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Product::factory()->create(['code' => 'WL-TEST']);
        $this->upload("\xEF\xBB\xBF".implode(';', SalesImportService::HEADERS)."\nWL-TEST;2025-01;2;1;100000;;\n", 'simulation');
        $batch = SalesImport::sole();
        $this->post(route('sales-imports.commit', $batch), ['confirmed' => 1])->assertSessionHasNoErrors();
        $this->assertNull(Sale::sole()->reference_label);
        $this->assertSame('simulation', Sale::sole()->data_origin);
    }

    #[TestWith(['manager'])]
    #[TestWith(['pelanggan'])]
    public function test_non_admin_cannot_access_imports_or_commit(string $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]));
        $batch = SalesImport::factory()->create();
        $this->get(route('sales-imports.index'))->assertForbidden();
        $this->get(route('sales-imports.template'))->assertForbidden();
        $this->post(route('sales-imports.store'), [])->assertForbidden();
        $this->get(route('sales-imports.show', $batch))->assertForbidden();
        $this->post(route('sales-imports.commit', $batch), ['confirmed' => 1])->assertForbidden();
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_guest_is_redirected_and_missing_confirmation_does_not_write_sales(): void
    {
        $this->get(route('sales-imports.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $batch = SalesImport::factory()->create();
        $this->post(route('sales-imports.commit', $batch), [])->assertSessionHasErrors('confirmed');
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_wrong_headers_invalid_encoding_and_oversized_row_count_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->post(route('sales-imports.store'), ['file' => UploadedFile::fake()->createWithContent('wrong.csv', "product,month\nWL-TEST,2025-01\n"), 'data_origin' => 'real', 'confirmed' => 1])->assertSessionHasErrors('file');
        $this->post(route('sales-imports.store'), ['file' => UploadedFile::fake()->createWithContent('many.csv', $this->csv(str_repeat("WL-TEST,2025-01,1,1,100000,,\n", 1001))), 'data_origin' => 'real', 'confirmed' => 1])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('sales_imports', 0);
        Product::factory()->create(['code' => 'WL-TEST']);
        $this->upload($this->csv("WL-TEST,2025-01,1,1,100000,Laris,Invalid \xFF UTF8\n"));
        $batch = SalesImport::sole();
        $this->assertNotEmpty($batch->errors);
        $this->post(route('sales-imports.commit', $batch), ['confirmed' => 1])->assertSessionHasErrors('import');
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_executable_files_and_missing_provenance_are_not_accepted(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->post(route('sales-imports.store'), ['file' => UploadedFile::fake()->createWithContent('upload.php', '<?php echo 1;'), 'data_origin' => 'real', 'confirmed' => 1])->assertSessionHasErrors('file');
        $this->post(route('sales-imports.store'), ['file' => UploadedFile::fake()->createWithContent('sales.csv', $this->csv("WL-TEST,2025-01,1,1,100000,,\n")), 'confirmed' => 1])->assertSessionHasErrors('data_origin');
        $this->assertDatabaseCount('sales_imports', 0);
        $this->assertDatabaseCount('sales', 0);
    }
}
