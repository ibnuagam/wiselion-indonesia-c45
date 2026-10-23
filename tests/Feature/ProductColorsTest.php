<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductColor;
use App\Models\User;
use Database\Seeders\ProductColorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ProductColorsTest extends TestCase
{
    use RefreshDatabase;

    private function input(array $overrides = []): array
    {
        return array_replace(['name' => 'Midnight Blue', 'color_hex' => '#233c69', 'sort_order' => 1, 'is_active' => 1], $overrides);
    }

    private function admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_admin_can_create_empty_slot_then_upload_replace_and_remove_color_photo(): void
    {
        Storage::fake('public');
        $this->admin();
        $product = Product::factory()->create();
        $this->post(route('products.colors.store', $product), $this->input(['name' => '  Midnight   Blue  ', 'product_id' => 999, 'photo_path' => 'evil.png']))->assertRedirect(route('products.colors.index', $product));
        $color = $product->colors()->sole();
        $this->assertSame('Midnight Blue', $color->name);
        $this->assertSame('midnight blue', $color->name_key);
        $this->assertNull($color->photo_path);
        $this->put(route('products.colors.update', [$product, $color]), $this->input(['photo' => UploadedFile::fake()->image('blue.png', 800, 800)]))->assertSessionHasNoErrors();
        $old = $color->fresh()->photo_path;
        Storage::disk('public')->assertExists($old);
        $this->put(route('products.colors.update', [$product, $color]), $this->input(['photo' => UploadedFile::fake()->image('new.png', 800, 800)]))->assertSessionHasNoErrors();
        $new = $color->fresh()->photo_path;
        $this->assertNotSame($old, $new);
        Storage::disk('public')->assertExists([$old, $new]);
        $this->put(route('products.colors.update', [$product, $color]), $this->input(['remove_photo' => 1]))->assertSessionHasNoErrors();
        $this->assertNull($color->fresh()->photo_path);
        Storage::disk('public')->assertExists([$old, $new]);
    }

    public function test_admin_pages_and_product_shortcuts_render(): void
    {
        $this->admin();
        $color = ProductColor::factory()->create();
        $this->get(route('products.colors.index', $color->product))->assertOk()->assertSee('Tambahkan warna');
        $this->get(route('products.colors.edit', [$color->product, $color]))->assertOk()->assertSee('Simpan perubahan warna');
        $this->get(route('products.index'))->assertOk()->assertSee('Warna (1)');
        $this->get(route('products.edit', $color->product))->assertOk()->assertSee('Kelola pilihan warna');
    }

    public function test_duplicate_names_are_normalized_and_scoped_to_product_including_archives(): void
    {
        $this->admin();
        $color = ProductColor::factory()->create(['name' => 'Midnight Blue']);
        $this->post(route('products.colors.store', $color->product), $this->input(['name' => '  MIDNIGHT   blue ']))->assertSessionHasErrors('name_key');
        $this->put(route('products.colors.update', [$color->product, $color]), $this->input())->assertSessionHasNoErrors();
        $color->delete();
        $this->post(route('products.colors.store', $color->product), $this->input())->assertSessionHasErrors('name_key');
        $this->post(route('products.colors.store', Product::factory()->create()), $this->input())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('product_colors', 2);
    }

    #[TestWith(['color_hex', 'red'])]
    #[TestWith(['color_hex', '#123456;display:none'])]
    #[TestWith(['sort_order', -1])]
    #[TestWith(['sort_order', 10000])]
    #[TestWith(['is_active', 'yes'])]
    #[TestWith(['name', ['invalid']])]
    #[TestWith(['name', '  '])]
    public function test_invalid_fields_cannot_create_colors(string $field, mixed $value): void
    {
        $this->admin();
        $this->post(route('products.colors.store', Product::factory()->create()), $this->input([$field => $value]))->assertSessionHasErrors($field);
        $this->assertDatabaseCount('product_colors', 0);
    }

    public function test_invalid_photos_are_rejected_before_storage(): void
    {
        Storage::fake('public');
        $this->admin();
        $product = Product::factory()->create();
        foreach ([UploadedFile::fake()->create('fake.svg', 10, 'image/svg+xml'), UploadedFile::fake()->image('small.png', 20, 20), UploadedFile::fake()->image('large.jpg', 800, 800)->size(2049)] as $file) {
            $this->post(route('products.colors.store', $product), $this->input(['photo' => $file]))->assertSessionHasErrors('photo');
        }
        $this->assertDatabaseCount('product_colors', 0);
        Storage::disk('public')->assertDirectoryEmpty('products/colors');
    }

    public function test_colors_can_be_archived_and_restored_without_losing_photo(): void
    {
        $this->admin();
        $color = ProductColor::factory()->create(['photo_path' => 'products/colors/blue.png']);
        $url = route('products.colors.restore', [$color->product, $color]);
        $this->delete(route('products.colors.destroy', [$color->product, $color]))->assertRedirect();
        $this->assertSoftDeleted($color);
        $this->get(route('catalog.show', $color->product))->assertOk()->assertDontSee('Pilih warna '.$color->name);
        $this->patch($url)->assertRedirect();
        $this->assertNotSoftDeleted($color);
        $this->assertSame('products/colors/blue.png', $color->fresh()->photo_path);
        $this->patch($url)->assertNotFound();
    }

    public function test_color_routes_enforce_product_ownership_even_on_restore(): void
    {
        $this->admin();
        $color = ProductColor::factory()->create();
        $other = Product::factory()->create();
        $params = [$other, $color];
        $this->get(route('products.colors.edit', $params))->assertNotFound();
        $this->put(route('products.colors.update', $params), $this->input())->assertNotFound();
        $this->delete(route('products.colors.destroy', $params))->assertNotFound();
        $color->delete();
        $this->patch(route('products.colors.restore', $params))->assertNotFound();
        $this->assertSoftDeleted($color);
        $color->product->delete();
        $this->patch(route('products.colors.restore', [$color->product, $color]))->assertNotFound();
    }

    #[TestWith(['manager'])]
    #[TestWith(['pelanggan'])]
    public function test_non_admin_roles_cannot_manage_color_options(string $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]));
        $color = ProductColor::factory()->create();
        $this->get(route('products.colors.index', $color->product))->assertForbidden();
        $this->post(route('products.colors.store', $color->product), $this->input())->assertForbidden();
        $this->get(route('products.colors.edit', [$color->product, $color]))->assertForbidden();
        $this->put(route('products.colors.update', [$color->product, $color]), $this->input())->assertForbidden();
        $this->delete(route('products.colors.destroy', [$color->product, $color]))->assertForbidden();
        $color->delete();
        $this->patch(route('products.colors.restore', [$color->product, $color]))->assertForbidden();
    }

    public function test_guest_is_redirected_from_admin_but_can_see_active_colors(): void
    {
        $color = ProductColor::factory()->create(['name' => 'Navy Blue', 'photo_path' => 'products/colors/navy.png']);
        $this->get(route('products.colors.index', $color->product))->assertRedirect(route('login'));
        $this->post(route('products.colors.store', $color->product), $this->input())->assertRedirect(route('login'));
        $this->get(route('catalog.show', $color->product))->assertOk()->assertSee('Pilih warna Navy Blue')->assertSee('products/colors/navy.png')->assertSee('data-color-option', false);
    }

    public function test_public_colors_are_ordered_filtered_and_escaped_even_without_gallery(): void
    {
        $product = Product::factory()->create(['photo_path' => null]);
        ProductColor::factory()->for($product)->create(['name' => 'Second', 'sort_order' => 2]);
        ProductColor::factory()->for($product)->create(['name' => '<script>bad</script>', 'sort_order' => 1]);
        ProductColor::factory()->for($product)->create(['name' => 'PrivateDraft', 'is_active' => false]);
        ProductColor::factory()->for($product)->create(['name' => 'ArchivedOption'])->delete();
        $this->get(route('catalog.show', $product))->assertOk()
            ->assertSeeInOrder(['&lt;script&gt;bad&lt;/script&gt;', 'Second'], false)
            ->assertDontSee('<script>bad</script>', false)->assertDontSee('PrivateDraft')->assertDontSee('ArchivedOption')
            ->assertSee('data-gallery-placeholder', false)->assertSee('data-gallery-main', false)->assertSee('data-gallery-zoom', false);
        $product->update(['is_active' => false]);
        $this->get(route('catalog.show', $product))->assertNotFound();
    }

    public function test_color_slot_seeder_is_idempotent_and_preserves_manual_changes(): void
    {
        $product = Product::factory()->create(['code' => 'WL-W69-PRO']);
        $this->seed(ProductColorSeeder::class);
        $this->assertSame(4, $product->colors()->count());
        $this->assertSame(0, $product->colors()->whereNotNull('photo_path')->count());
        $color = $product->colors()->first();
        $color->update(['color_hex' => '#abcdef']);
        $color->delete();
        $this->seed(ProductColorSeeder::class);
        $this->assertDatabaseCount('product_colors', 4);
        $this->assertSame('#abcdef', $color->fresh()->color_hex);
        $this->assertSoftDeleted($color);
        $this->assertDatabaseCount('sales', 0);
    }
}
