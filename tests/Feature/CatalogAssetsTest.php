<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class CatalogAssetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reviewed_import_preview_does_not_change_database_or_public_media(): void
    {
        Storage::fake('public');
        $path = $this->manifest();

        $this->artisan('catalog:import-reviewed', ['manifest' => $path])->assertSuccessful();

        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_images', 0);
        Storage::disk('public')->assertDirectoryEmpty('products');
    }

    public function test_reviewed_import_adds_gallery_specs_and_preserves_manual_edits_on_repeat(): void
    {
        Storage::fake('public');
        $path = $this->manifest();

        $this->artisan('catalog:import-reviewed', ['manifest' => $path, '--apply' => true])->assertSuccessful();

        $product = Product::sole();
        $this->assertSame('WL-IMPORT', $product->code);
        $this->assertSame('400000.00', $product->price);
        $this->assertSame(['Layar' => 'AMOLED'], $product->specifications);
        $this->assertFalse($product->catalog_reviewed);
        $this->assertDatabaseCount('product_images', 1);
        $this->assertDatabaseCount('sales', 0);
        Storage::disk('public')->assertExists($product->photo_path);
        $product->update(['price' => 410000, 'catalog_reviewed' => true]);
        $this->artisan('catalog:import-reviewed', ['manifest' => $path, '--apply' => true, '--update-existing' => true])->assertSuccessful();
        $this->assertSame('410000.00', $product->fresh()->price);
        $this->assertTrue($product->fresh()->catalog_reviewed);
        $this->assertDatabaseCount('product_images', 1);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_existing_product_is_preserved_unless_update_is_explicit(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create(['code' => 'WL-IMPORT', 'price' => 100000]);
        $path = $this->manifest();

        $this->artisan('catalog:import-reviewed', ['manifest' => $path, '--apply' => true])->assertSuccessful();

        $this->assertSame('100000.00', $product->fresh()->price);
        $this->artisan('catalog:import-reviewed', ['manifest' => $path, '--apply' => true, '--update-existing' => true])->assertSuccessful();
        $this->assertSame('400000.00', $product->fresh()->price);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_bad_image_prevents_any_partial_import(): void
    {
        Storage::fake('public');
        $path = $this->manifest(['images' => ['/nonexistent/catalog.webp']]);

        $this->artisan('catalog:import-reviewed', ['manifest' => $path, '--apply' => true])->assertFailed();

        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('categories', 0);
        Storage::disk('public')->assertDirectoryEmpty('products');
    }

    public function test_changed_source_hash_blocks_import(): void
    {
        Storage::fake('public');
        $path = $this->manifest();
        Storage::disk('local')->put('source.docx', 'changed');

        $this->artisan('catalog:import-reviewed', ['manifest' => $path, '--apply' => true])->assertFailed();

        $this->assertDatabaseCount('products', 0);
    }

    public function test_admin_can_upload_gallery_and_edit_structured_specs(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->put(route('products.update', $product), $this->productInput($product, [
            'specifications_text' => "Layar: AMOLED\nKonektivitas: Bluetooth 5.3",
            'gallery' => [UploadedFile::fake()->image('front.png', 800, 800), UploadedFile::fake()->image('back.png', 800, 800)],
            'source_metadata' => ['fake' => true], 'catalog_reviewed' => 1,
        ]))->assertRedirect(route('products.index'));

        $this->assertSame(['Layar' => 'AMOLED', 'Konektivitas' => 'Bluetooth 5.3'], $product->fresh()->specifications);
        $this->assertTrue($product->fresh()->catalog_reviewed);
        $this->assertNull($product->fresh()->source_metadata);
        $this->assertSame(2, $product->images()->count());
        Storage::disk('public')->assertExists($product->images()->pluck('path')->all());
        $this->get(route('catalog.show', $product))->assertOk()->assertSee('AMOLED')->assertSee('Lihat foto 2');
    }

    public function test_gallery_photo_can_be_archived_without_deleting_file(): void
    {
        Storage::fake('public');
        $image = ProductImage::factory()->create();
        Storage::disk('public')->put($image->path, 'fixture');
        $product = $image->product;
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->put(route('products.update', $product), $this->productInput($product, ['remove_gallery' => [$image->id]]))->assertRedirect(route('products.index'));

        $this->assertSoftDeleted($image);
        Storage::disk('public')->assertExists($image->path);
        $this->get(route('catalog.show', $product))->assertOk()->assertDontSee($image->url, false);
    }

    public function test_photo_belonging_to_another_product_cannot_be_removed(): void
    {
        $image = ProductImage::factory()->create();
        $product = Product::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->put(route('products.update', $product), $this->productInput($product, ['remove_gallery' => [$image->id]]))->assertSessionHasErrors('remove_gallery.0');

        $this->assertNotSoftDeleted($image);
    }

    #[TestWith(['Layar tanpa pemisah'])]
    #[TestWith(["Layar: OLED\nLayar: AMOLED"])]
    public function test_invalid_or_duplicate_spec_labels_are_rejected(string $specs): void
    {
        $product = Product::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->put(route('products.update', $product), $this->productInput($product, ['specifications_text' => $specs]))->assertSessionHasErrors('specifications_text');

        $this->assertNull($product->fresh()->specifications);
    }

    public function test_detail_escapes_specs_and_does_not_expose_private_import_metadata(): void
    {
        $product = Product::factory()->create(['specifications' => ['<script>key()</script>' => '<script>value()</script>'], 'source_metadata' => ['notes' => ['PRIVATE_IMPORT_NOTE']], 'price_note' => '<script>price()</script>']);

        $this->get(route('catalog.show', $product))->assertOk()->assertDontSee('<script>key()', false)->assertDontSee('<script>value()', false)->assertDontSee('<script>price()', false)->assertDontSee('PRIVATE_IMPORT_NOTE');
        $this->assertArrayNotHasKey('source_metadata', $product->toArray());
    }

    public function test_price_range_is_displayed_as_starting_price(): void
    {
        $product = Product::factory()->create(['price' => 239999, 'reference_price_max' => 279999]);

        $this->get(route('catalog.show', $product))->assertOk()->assertSee('Mulai dari')->assertSee('239.999')->assertSee('279.999');
    }

    public function test_nested_gallery_payload_is_rejected_without_server_error(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->put(route('products.update', $product), $this->productInput($product, ['gallery' => [['nested' => UploadedFile::fake()->image('photo.jpg', 100, 100)]]]))->assertSessionHasErrors('gallery.0');

        $this->assertDatabaseCount('product_images', 0);
        Storage::disk('public')->assertDirectoryEmpty('products');
    }

    public function test_admin_catalog_review_page_summarizes_import_and_pending_status(): void
    {
        $product = Product::factory()->create(['catalog_source' => 'source.docx', 'catalog_reviewed' => false, 'source_metadata' => ['notes' => ['Periksa panel layar']]]);
        ProductImage::factory()->for($product)->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get(route('catalog-review.index'))->assertOk()->assertViewHas('total', 1)->assertViewHas('pending', 1)->assertSee('Periksa panel layar');
    }

    #[TestWith(['manager'])]
    #[TestWith(['pelanggan'])]
    public function test_catalog_review_is_admin_only(string $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]));

        $this->get(route('catalog-review.index'))->assertForbidden();
    }

    private function productInput(Product $product, array $overrides = []): array
    {
        return array_replace(['category_id' => $product->category_id, 'code' => $product->code, 'name' => $product->name, 'price' => $product->price, 'is_active' => 1], $overrides);
    }

    private function manifest(array $rowOverrides = []): string
    {
        Storage::fake('local');
        Storage::disk('local')->put('source.docx', 'document-fixture');
        $source = Storage::disk('local')->path('source.docx');
        $image = UploadedFile::fake()->image('front.png', 800, 800);
        Storage::disk('local')->put('front.png', file_get_contents($image->getPathname()));
        $payload = ['source_document' => $source, 'source_sha256' => hash_file('sha256', $source), 'products' => [array_replace([
            'code' => 'WL-IMPORT', 'name' => 'Imported watch', 'category' => 'Smartwatch', 'description' => 'Deskripsi dokumen', 'price' => 400000,
            'specifications' => ['Layar' => 'AMOLED'], 'images' => [Storage::disk('local')->path('front.png')], 'source_metadata' => ['notes' => ['Harga referensi']],
        ], $rowOverrides)]];
        Storage::disk('local')->put('manifest.json', json_encode($payload));

        return Storage::disk('local')->path('manifest.json');
    }
}
