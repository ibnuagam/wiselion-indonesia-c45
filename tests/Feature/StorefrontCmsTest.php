<?php

namespace Tests\Feature;

use App\Models\Headline;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class StorefrontCmsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_home_and_product_but_cannot_manage_content(): void
    {
        $product = Product::factory()->create();

        $this->get('/')->assertOk()->assertSee($product->name);
        $this->get(route('catalog.show', $product))->assertOk()->assertSee($product->name);
        $this->get('/admin/headlines')->assertRedirect(route('login'));
        $this->get('/admin/promotions')->assertRedirect(route('login'));
    }

    #[TestWith(['manager'])]
    #[TestWith(['pelanggan'])]
    public function test_non_admin_roles_cannot_manage_headlines_or_promotions(string $role): void
    {
        $product = Product::factory()->create();
        $headline = Headline::factory()->create();
        $this->actingAs(User::factory()->create(['role' => $role]));

        $this->get('/admin/headlines')->assertForbidden();
        $this->post('/admin/headlines', $this->headlineInput())->assertForbidden();
        $this->put(route('headlines.update', $headline), $this->headlineInput())->assertForbidden();
        $this->delete(route('headlines.destroy', $headline))->assertForbidden();
        $this->get('/admin/promotions')->assertForbidden();
        $this->put(route('promotions.update', $product), ['sale_enabled' => 1, 'sale_price' => 1000])->assertForbidden();
        $this->assertSame(1, Headline::count());
        $this->assertFalse($product->fresh()->sale_enabled);
    }

    public function test_admin_can_create_headline_with_desktop_and_mobile_uploads(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->post('/admin/headlines', $this->headlineInput([
            'image' => UploadedFile::fake()->image('banner.jpg', 1920, 800),
            'mobile_image' => UploadedFile::fake()->image('mobile.png', 800, 1000),
            'image_path' => 'untrusted.php',
        ]))->assertRedirect(route('headlines.index'));

        $headline = Headline::sole();
        $this->assertSame('Headline pengujian', $headline->title);
        Storage::disk('public')->assertExists([$headline->image_path, $headline->mobile_image_path]);
        $this->assertNotSame('untrusted.php', $headline->image_path);
    }

    public function test_admin_can_update_and_remove_banner_without_losing_recoverable_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('headlines/existing.jpg', 'fixture');
        $headline = Headline::factory()->create(['image_path' => 'headlines/existing.jpg']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->put(route('headlines.update', $headline), $this->headlineInput(['title' => 'Judul terbaru', 'remove_image' => 1]))->assertRedirect(route('headlines.index'));

        $this->assertSame('Judul terbaru', $headline->fresh()->title);
        $this->assertNull($headline->fresh()->image_path);
        Storage::disk('public')->assertExists('headlines/existing.jpg');
    }

    public function test_admin_can_archive_and_restore_headline(): void
    {
        $headline = Headline::factory()->create(['is_published' => true, 'title' => 'Tayang sementara']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->delete(route('headlines.destroy', $headline))->assertRedirect();

        $this->assertSoftDeleted($headline);
        $this->get('/')->assertDontSee('Tayang sementara');
        $this->patch(route('headlines.restore', $headline))->assertRedirect(route('headlines.index'));
        $this->assertNotSoftDeleted($headline);
        $this->get('/')->assertSee('Tayang sementara');
    }

    public function test_home_displays_only_published_in_period_headlines_in_admin_order(): void
    {
        $this->travelTo(now()->setDateTime(2026, 10, 22, 12, 0));
        Headline::factory()->create(['title' => 'Tayang kedua', 'is_published' => true, 'sort_order' => 2]);
        Headline::factory()->create(['title' => 'Tayang pertama', 'is_published' => true, 'sort_order' => 1, 'starts_on' => '2026-10-22', 'ends_on' => '2026-10-22']);
        Headline::factory()->create(['title' => 'Draf rahasia']);
        Headline::factory()->create(['title' => 'Belum waktunya', 'is_published' => true, 'starts_on' => '2026-10-23']);
        Headline::factory()->create(['title' => 'Sudah berakhir', 'is_published' => true, 'ends_on' => '2026-10-21']);

        $this->get('/')->assertOk()->assertSeeInOrder(['Tayang pertama', 'Tayang kedua'])->assertDontSee('Draf rahasia')->assertDontSee('Belum waktunya')->assertDontSee('Sudah berakhir');
    }

    public function test_headline_title_description_and_button_are_escaped(): void
    {
        Headline::factory()->create(['title' => '<script>title()</script>', 'description' => '<img src=x onerror=run()>', 'button_text' => '<script>button()</script>', 'is_published' => true]);

        $this->get('/')->assertOk()->assertSee('&lt;script&gt;title()', false)->assertDontSee('<script>title()', false)->assertDontSee('<img src=x', false)->assertDontSee('<script>button()', false);
    }

    public function test_multiple_headlines_have_manual_navigation_without_pause_control(): void
    {
        Headline::factory()->count(2)->create(['is_published' => true]);

        $this->get('/')->assertOk()
            ->assertSee('data-carousel-prev', false)
            ->assertSee('data-carousel-next', false)
            ->assertDontSee('data-carousel-toggle', false)
            ->assertDontSee('Jeda pergantian headline');
    }

    public function test_single_headline_and_empty_fallback_have_no_carousel_controls(): void
    {
        $this->get('/')->assertOk()->assertSee('data-slide', false)->assertDontSee('data-carousel-toggle', false);

        Headline::factory()->create(['is_published' => true]);

        $this->get('/')->assertOk()->assertSee('data-slide', false)
            ->assertDontSee('data-carousel-prev', false)
            ->assertDontSee('data-carousel-toggle', false);
    }

    #[TestWith(['theme', 'unknown'])]
    #[TestWith(['sort_order', -1])]
    #[TestWith(['title', ''])]
    #[TestWith(['ends_on', '2026-10-20'])]
    public function test_invalid_headline_input_is_rejected(string $field, mixed $value): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->post('/admin/headlines', $this->headlineInput([$field => $value, 'starts_on' => '2026-10-22']))->assertSessionHasErrors($field);

        $this->assertDatabaseCount('headlines', 0);
    }

    public function test_executable_upload_is_rejected(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->post('/admin/headlines', $this->headlineInput(['image' => UploadedFile::fake()->createWithContent('payload.php', '<?php echo 1;')]))->assertSessionHasErrors('image');

        $this->assertDatabaseCount('headlines', 0);
        Storage::disk('public')->assertDirectoryEmpty('headlines');
    }

    public function test_banner_dimensions_are_validated(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->post('/admin/headlines', $this->headlineInput(['image' => UploadedFile::fake()->image('small.jpg', 100, 100)]))->assertSessionHasErrors(['image' => 'Banner desktop minimal 800 × 300 piksel.']);

        $this->assertDatabaseCount('headlines', 0);
    }

    public function test_admin_can_activate_sale_and_customers_see_discount_without_changing_history(): void
    {
        $this->freezeTime();
        $product = Product::factory()->create(['price' => 400000]);
        $sale = Sale::factory()->for($product)->create(['unit_price' => 400000, 'total_revenue' => 800000]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->put(route('promotions.update', $product), ['sale_enabled' => 1, 'sale_title' => 'Weekend Wiselion', 'sale_price' => 300000, 'sale_starts_on' => now('Asia/Jakarta')->toDateString(), 'sale_ends_on' => now('Asia/Jakarta')->toDateString(), 'price' => 1])->assertRedirect(route('promotions.index'));

        $this->assertSame('400000.00', $product->fresh()->price);
        $this->assertSame('300000.00', $product->fresh()->sale_price);
        $this->assertSame('800000.00', $sale->fresh()->total_revenue);
        $this->get('/?collection=sale')->assertOk()->assertSee('25% OFF')->assertSee('Rp 300.000')->assertSee('Weekend Wiselion');
    }

    public function test_discount_label_is_next_to_prices_and_never_overlays_product_photo(): void
    {
        $this->freezeTime();
        $product = Product::factory()->create(['name' => 'Wiselion promo test', 'price' => 400000, 'sale_price' => 300000, 'sale_enabled' => true, 'is_new' => true]);

        $response = $this->get('/?collection=sale')->assertOk()->assertSee('25% OFF')->assertSee('Rp 400.000')->assertSee('Rp 300.000');

        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $this->assertSame(0, $xpath->query('//a[contains(concat(" ", normalize-space(@class), " "), " watch-image ")]//*[contains(text(), "% OFF")]')->length);
        $this->assertSame(1, $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " watch-price ")]/span[contains(concat(" ", normalize-space(@class), " "), " watch-sale-badge ") and contains(text(), "25% OFF")]')->length);
        $this->assertSame('400000.00', $product->fresh()->price);
        $this->assertSame('300000.00', $product->fresh()->sale_price);
    }

    public function test_non_discounted_product_keeps_new_label_without_a_discount_badge(): void
    {
        $this->freezeTime();
        Product::factory()->create(['is_new' => true, 'sale_enabled' => false, 'sale_price' => 300000, 'price' => 400000]);

        $this->get('/')->assertOk()->assertSee('BARU')->assertDontSee('class="watch-sale-badge"', false)->assertDontSee('25% OFF');
    }

    #[TestWith(['sale_price', 400000])]
    #[TestWith(['sale_price', -1])]
    #[TestWith(['sale_price', null])]
    #[TestWith(['sale_ends_on', '2026-10-20'])]
    public function test_invalid_promo_prices_and_periods_are_rejected(string $field, mixed $value): void
    {
        $product = Product::factory()->create(['price' => 400000]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->put(route('promotions.update', $product), array_replace(['sale_enabled' => 1, 'sale_price' => 300000, 'sale_starts_on' => '2026-10-22'], [$field => $value]))->assertSessionHasErrors($field);

        $this->assertFalse($product->fresh()->sale_enabled);
    }

    public function test_expired_future_and_disabled_sales_use_regular_price(): void
    {
        $this->travelTo(now()->setDateTime(2026, 10, 22, 12, 0));
        Product::factory()->create(['name' => 'Expired watch', 'price' => 400000, 'sale_price' => 200000, 'sale_enabled' => true, 'sale_ends_on' => '2026-10-21']);
        Product::factory()->create(['name' => 'Future watch', 'price' => 400000, 'sale_price' => 200000, 'sale_enabled' => true, 'sale_starts_on' => '2026-10-23']);
        Product::factory()->create(['name' => 'Disabled sale watch', 'price' => 400000, 'sale_price' => 200000, 'sale_enabled' => false]);

        $this->get('/?collection=sale')->assertOk()->assertViewHas('products', fn ($rows) => $rows->total() === 0);
        $this->get('/?budget=under250')->assertViewHas('products', fn ($rows) => $rows->total() === 0);
    }

    public function test_price_filters_and_sort_use_current_discounted_price(): void
    {
        $this->freezeTime();
        Product::factory()->create(['name' => 'Normal watch', 'price' => 240000]);
        Product::factory()->create(['name' => 'Discount watch', 'price' => 400000, 'sale_price' => 200000, 'sale_enabled' => true]);
        Product::factory()->create(['name' => 'Too expensive', 'price' => 600000]);

        $this->get('/?min_price=200000&max_price=250000&sort=price_asc')->assertOk()
            ->assertViewHas('products', fn ($rows) => $rows->pluck('name')->all() === ['Discount watch', 'Normal watch']);
    }

    #[TestWith(['under250', ['Low']])]
    #[TestWith(['250to500', ['Middle']])]
    #[TestWith(['over500', ['High']])]
    public function test_budget_groups_have_unambiguous_boundaries(string $budget, array $names): void
    {
        Product::factory()->create(['name' => 'Low', 'price' => 249999]);
        Product::factory()->create(['name' => 'Middle', 'price' => 250000]);
        Product::factory()->create(['name' => 'High', 'price' => 500000]);

        $this->get('/?budget='.$budget)->assertOk()->assertViewHas('products', fn ($rows) => $rows->pluck('name')->all() === $names);
    }

    #[TestWith(['sort', 'price desc; drop table products'])]
    #[TestWith(['max_price', -1])]
    #[TestWith(['max_price', 50])]
    public function test_invalid_customer_filters_are_rejected(string $field, mixed $value): void
    {
        $query = http_build_query(array_replace(['min_price' => 100], [$field => $value]));

        $this->from('/')->get('/?'.$query)->assertSessionHasErrors($field);
    }

    public function test_admin_curated_new_and_featured_collections_are_customer_visible(): void
    {
        Product::factory()->create(['name' => 'New watch', 'is_new' => true]);
        Product::factory()->create(['name' => 'Featured watch', 'is_featured' => true]);
        Product::factory()->create(['name' => 'Regular watch']);

        $this->get('/?collection=new')->assertOk()->assertSee('New watch')->assertDontSee('Featured watch')->assertDontSee('Regular watch');
        $this->get('/?collection=featured')->assertOk()->assertSee('Featured watch')->assertDontSee('New watch');
    }

    public function test_inactive_and_archived_products_are_not_public(): void
    {
        $inactive = Product::factory()->create(['name' => 'Hidden watch', 'is_active' => false]);
        $archived = Product::factory()->create(['name' => 'Archived watch']);
        $archived->delete();

        $this->get('/')->assertOk()->assertDontSee('Hidden watch')->assertDontSee('Archived watch');
        $this->get(route('catalog.show', $inactive))->assertNotFound();
        $this->get(route('catalog.show', $archived))->assertNotFound();
    }

    public function test_archived_categories_do_not_break_public_or_admin_product_lists(): void
    {
        $product = Product::factory()->create(['name' => 'Archived category watch']);
        $product->category->delete();

        $this->get('/')->assertOk()->assertDontSee('Archived category watch');
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin/products')->assertOk();
    }

    private function headlineInput(array $overrides = []): array
    {
        return array_replace(['title' => 'Headline pengujian', 'eyebrow' => 'WISELION', 'button_text' => 'Jelajahi koleksi', 'theme' => 'dark', 'sort_order' => 1, 'is_published' => 0], $overrides);
    }

    public function test_historical_sales_keep_archived_product_information_readable_for_admin(): void
    {
        $product = Product::factory()->create(['name' => 'Historical watch']);
        Sale::factory()->for($product)->create(['total_revenue' => 750000]);
        $product->category->delete();
        $product->delete();
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get('/dashboard')->assertOk()->assertViewHas('totalRevenue', fn ($total) => (float) $total === 750000.0);
        $this->get('/admin/sales')->assertOk()->assertSee('Historical watch');
    }
}
