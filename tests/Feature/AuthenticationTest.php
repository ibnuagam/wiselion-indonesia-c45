<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_does_not_expose_demo_credentials(): void
    {
        $this->get('/login')->assertOk()->assertSee('Masuk ke akun Anda')->assertDontSee('admin@wiselion.test')->assertDontSee('password: password');
    }

    #[TestWith(['admin'])]
    #[TestWith(['manager'])]
    public function test_internal_accounts_can_log_in(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_customer_login_ignores_an_internal_intended_destination(): void
    {
        $user = User::factory()->create(['role' => 'pelanggan']);

        $this->withSession(['url.intended' => route('dashboard')])->post('/login', [
            'email' => $user->email, 'password' => 'password', 'role' => 'admin',
        ])->assertRedirect(route('catalog'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'pelanggan']);
    }

    public function test_signed_in_customer_returns_to_catalog_from_login_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'pelanggan']))->get('/login')->assertRedirect(route('catalog'));
    }

    public function test_invalid_password_does_not_authenticate(): void
    {
        $user = User::factory()->create();

        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertRedirect('/login')->assertSessionHasErrors(['email' => 'Email atau password tidak sesuai.']);

        $this->assertGuest();
    }

    public function test_missing_credentials_receive_clear_errors(): void
    {
        $this->from('/login')->post('/login', [])->assertSessionHasErrors([
            'email' => 'Alamat email wajib diisi.', 'password' => 'Password wajib diisi.',
        ]);

        $this->assertGuest();
    }

    public function test_repeated_failed_attempts_are_limited(): void
    {
        $user = User::factory()->create();
        $key = 'login:'.mb_strtolower($user->email).'|127.0.0.1';
        RateLimiter::hit($key, 60);
        RateLimiter::hit($key, 60);
        RateLimiter::hit($key, 60);
        RateLimiter::hit($key, 60);
        RateLimiter::hit($key, 60);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');

        $this->assertGuest();
        RateLimiter::clear($key);
    }

    #[TestWith(['/dashboard'])]
    #[TestWith(['/admin/dashboard'])]
    #[TestWith(['/manager/dashboard'])]
    public function test_customer_cannot_access_internal_dashboards(string $path): void
    {
        $this->actingAs(User::factory()->create(['role' => 'pelanggan']))->get($path)->assertForbidden();
    }

    #[TestWith(['admin', '/admin/dashboard'])]
    #[TestWith(['manager', '/manager/dashboard'])]
    public function test_internal_roles_can_access_their_dashboard(string $role, string $path): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]))->get($path)->assertOk()->assertSee('Ringkasan penjualan');
    }

    #[TestWith(['manager', '/admin/dashboard'])]
    #[TestWith(['admin', '/manager/dashboard'])]
    public function test_internal_roles_cannot_access_another_role_dashboard(string $role, string $path): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]))->get($path)->assertForbidden();
    }

    public function test_guest_cannot_access_company_sales(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_public_catalog_does_not_contain_internal_sales_information(): void
    {
        $this->seed();

        $this->get('/katalog')->assertOk()->assertSee('Laptop Pro 14')
            ->assertDontSee('Total omset')->assertDontSee('1.223.750.000')->assertDontSee('Entropy')->assertDontSee('Gain ratio');
    }

    public function test_catalog_filters_products_by_name(): void
    {
        $this->seed();

        $this->get('/katalog?q=Laptop')->assertOk()->assertSee('Laptop Pro 14')->assertDontSee('Mouse Wireless');
    }

    public function test_logout_ends_the_authenticated_session(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
