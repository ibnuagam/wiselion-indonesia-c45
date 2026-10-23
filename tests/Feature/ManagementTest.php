<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ManagementTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['/admin/categories'])]
    #[TestWith(['/admin/products'])]
    #[TestWith(['/admin/sales'])]
    public function test_guests_are_redirected_to_login(string $path): void
    {
        $this->get($path)->assertRedirect(route('login'));
    }

    #[TestWith(['manager'])]
    #[TestWith(['pelanggan'])]
    public function test_non_admin_cannot_manage_data(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $this->actingAs($user)->post('/admin/categories', ['name' => 'Unauthorized'])->assertForbidden();
        $this->get('/admin/products')->assertForbidden();
        $this->post('/admin/sales', [])->assertForbidden();
        $this->assertDatabaseMissing('categories', ['name' => 'Unauthorized']);
    }

    #[TestWith(['/admin/categories'])]
    #[TestWith(['/admin/products'])]
    #[TestWith(['/admin/sales'])]
    #[TestWith(['/admin/categories/create'])]
    #[TestWith(['/admin/products/create'])]
    #[TestWith(['/admin/sales/create'])]
    public function test_admin_can_open_management_pages(string $path): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get($path)->assertOk();
    }

    public function test_admin_can_create_and_update_a_category(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post('/admin/categories', ['name' => 'Smartwatch', 'description' => 'Perangkat wearable'])
            ->assertRedirect(route('categories.index'));
        $category = Category::where('name', 'Smartwatch')->firstOrFail();
        $this->put(route('categories.update', $category), ['name' => 'Wearable', 'description' => 'Updated'])->assertRedirect(route('categories.index'));
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Wearable']);
    }

    public function test_used_categories_cannot_be_archived(): void
    {
        $product = Product::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->delete(route('categories.destroy', $product->category))->assertSessionHasErrors('category');
        $this->assertNotSoftDeleted($product->category);
    }

    public function test_unused_category_can_be_archived_and_restored(): void
    {
        $category = Category::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->delete(route('categories.destroy', $category))->assertSessionHas('success');
        $this->assertSoftDeleted($category);
        $this->patch(route('categories.restore', $category))->assertRedirect(route('categories.index'));
        $this->assertNotSoftDeleted($category);
    }

    public function test_duplicate_category_name_is_rejected(): void
    {
        $category = Category::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post('/admin/categories', ['name' => $category->name])->assertSessionHasErrors('name');
        $this->assertDatabaseCount('categories', 1);
    }

    public function test_admin_can_upload_product_photo_and_it_is_publicly_visible(): void
    {
        Storage::fake('public');
        $category = Category::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post('/admin/products', [
            'category_id' => $category->id, 'name' => 'Earbuds HD', 'code' => 'EAR-001', 'price' => '349000.50', 'is_active' => 1,
            'photo' => UploadedFile::fake()->image('earbuds.jpg', 400, 400),
        ])->assertRedirect(route('products.index'));
        $product = Product::where('code', 'EAR-001')->firstOrFail();
        Storage::disk('public')->assertExists($product->photo_path);
        $this->get('/katalog')->assertOk()->assertSee('Earbuds HD')->assertSee('/storage/'.$product->photo_path, false);
    }

    public function test_executable_upload_is_rejected(): void
    {
        Storage::fake('public');
        $category = Category::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post('/admin/products', [
            'category_id' => $category->id, 'name' => 'Bad file', 'code' => 'BAD-001', 'price' => 100, 'is_active' => 1,
            'photo' => UploadedFile::fake()->create('shell.php', 10, 'application/x-httpd-php'),
        ])->assertSessionHasErrors('photo');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_product_update_can_deactivate_catalog_visibility(): void
    {
        $product = Product::factory()->create(['name' => 'Hidden model']);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->put(route('products.update', $product), [
            'category_id' => $product->category_id, 'code' => $product->code, 'name' => $product->name, 'price' => 399000, 'is_active' => 0,
        ])->assertRedirect(route('products.index'));
        $this->assertDatabaseHas('products', ['id' => $product->id, 'price' => 399000, 'is_active' => 0]);
        $this->get('/katalog')->assertOk()->assertDontSee('Hidden model');
    }

    public function test_sold_product_cannot_be_archived(): void
    {
        $sale = Sale::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->delete(route('products.destroy', $sale->product))->assertSessionHasErrors('product');
        $this->assertNotSoftDeleted($sale->product);
        $this->assertModelExists($sale);
    }

    public function test_unsold_product_can_be_archived_and_restored(): void
    {
        $product = Product::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->delete(route('products.destroy', $product))->assertSessionHas('success');
        $this->assertSoftDeleted($product);
        $this->patch(route('products.restore', $product))->assertRedirect(route('products.index'));
        $this->assertNotSoftDeleted($product);
    }

    public function test_omset_is_calculated_on_server_ignoring_submitted_total(): void
    {
        $product = Product::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post('/admin/sales', $this->saleInput($product, ['total_revenue' => 1]))->assertRedirect(route('sales.index'));
        $this->assertDatabaseHas('sales', ['product_id' => $product->id, 'total_revenue' => 1050001.50]);
    }

    public function test_duplicate_product_period_is_rejected_even_if_archived(): void
    {
        $sale = Sale::factory()->create();
        $sale->delete();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post('/admin/sales', $this->saleInput($sale->product))->assertSessionHasErrors('product_id');
        $this->assertDatabaseCount('sales', 1);
    }

    public function test_sale_can_be_updated_without_duplicate_error(): void
    {
        $sale = Sale::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->put(route('sales.update', $sale), $this->saleInput($sale->product, ['units_sold' => 4]))->assertRedirect(route('sales.index'));
        $this->assertDatabaseHas('sales', ['id' => $sale->id, 'units_sold' => 4, 'total_revenue' => 1400002.00]);
    }

    public function test_sale_can_be_archived_and_restored(): void
    {
        $sale = Sale::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->delete(route('sales.destroy', $sale))->assertSessionHas('success');
        $this->assertSoftDeleted($sale);
        $this->patch(route('sales.restore', $sale))->assertRedirect(route('sales.index'));
        $this->assertNotSoftDeleted($sale);
    }

    public function test_zero_sales_are_allowed(): void
    {
        $product = Product::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post('/admin/sales', $this->saleInput($product, ['units_sold' => 0, 'order_frequency' => 0]))->assertRedirect(route('sales.index'));
        $this->assertDatabaseHas('sales', ['product_id' => $product->id, 'units_sold' => 0, 'total_revenue' => 0]);
    }

    #[TestWith(['month', 13])]
    #[TestWith(['year', 1999])]
    #[TestWith(['units_sold', -1])]
    #[TestWith(['order_frequency', 4])]
    #[TestWith(['unit_price', -1])]
    #[TestWith(['confirmed', 0])]
    public function test_invalid_sales_fields_are_rejected(string $field, int $value): void
    {
        $product = Product::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post('/admin/sales', $this->saleInput($product, [$field => $value]))->assertSessionHasErrors($field);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_dashboard_aggregates_filtered_sales_and_omits_archives(): void
    {
        $product = Product::factory()->create();
        Sale::factory()->for($product)->create(['month' => 9, 'total_revenue' => 120000, 'units_sold' => 2]);
        Sale::factory()->for($product)->create(['month' => 10, 'total_revenue' => 3500000, 'units_sold' => 10]);
        $archived = Sale::factory()->for($product)->create(['month' => 11, 'total_revenue' => 9000000]);
        $archived->delete();
        $this->actingAs(User::factory()->create(['role' => 'manager']))->get('/dashboard?start=2026-10&end=2026-11')->assertOk()
            ->assertViewHas('totalRevenue', fn ($total) => (float) $total === 3500000.0)
            ->assertViewHas('totalUnits', fn ($total) => (int) $total === 10)
            ->assertViewHas('monthlyCount', 1)->assertDontSee('@else');
    }

    public function test_end_only_period_filter_is_supported(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/dashboard?end=2026-12')->assertOk();
    }

    public function test_invalid_period_order_is_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/dashboard?start=2026-12&end=2026-01')->assertSessionHasErrors('end');
    }

    public function test_product_names_are_escaped_in_public_catalog(): void
    {
        Product::factory()->create(['name' => '<script>alert(1)</script>']);
        $this->get('/katalog')->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    private function saleInput(Product $product, array $overrides = []): array
    {
        return array_replace(['product_id' => $product->id, 'month' => 10, 'year' => 2026, 'units_sold' => 3, 'order_frequency' => 2, 'unit_price' => '350000.50', 'confirmed' => 1], $overrides);
    }
}
