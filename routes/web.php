<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\HeadlineController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\CatalogReviewController;
use App\Http\Controllers\ProductColorController;
use App\Http\Controllers\SalesImportController;
use App\Http\Controllers\AnalysisController;
use Illuminate\Support\Facades\Route;

Route::get('/', CatalogController::class)->name('catalog');
Route::get('/katalog', CatalogController::class)->name('catalog.legacy');
Route::get('/produk/{product}', [CatalogController::class, 'show'])->name('catalog.show');
Route::get('/login', [AuthController::class, 'showLogin'])->middleware('guest')->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('guest')->name('login.store');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'role:admin,manager'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/analisis', [AnalysisController::class, 'index'])->name('analyses.index');
    Route::get('/analisis/{analysis}', [AnalysisController::class, 'show'])->name('analyses.show');
    Route::get('/analisis/{analysis}/snapshot', [AnalysisController::class, 'download'])->name('analyses.download');
    Route::get('/admin/dashboard', DashboardController::class)->middleware('role:admin')->name('admin.dashboard');
    Route::get('/manager/dashboard', DashboardController::class)->middleware('role:manager')->name('manager.dashboard');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function (): void {
    Route::post('analyses', [AnalysisController::class, 'store'])->middleware('throttle:10,1')->name('analyses.store');
    Route::get('sales-imports', [SalesImportController::class, 'index'])->name('sales-imports.index');
    Route::get('sales-imports/template', [SalesImportController::class, 'template'])->name('sales-imports.template');
    Route::post('sales-imports', [SalesImportController::class, 'store'])->middleware('throttle:10,1')->name('sales-imports.store');
    Route::get('sales-imports/{salesImport}', [SalesImportController::class, 'show'])->name('sales-imports.show');
    Route::post('sales-imports/{salesImport}/commit', [SalesImportController::class, 'commit'])->name('sales-imports.commit');
    Route::get('catalog-review', CatalogReviewController::class)->name('catalog-review.index');
    Route::resource('headlines', HeadlineController::class)->except('show');
    Route::patch('headlines/{headline}/restore', [HeadlineController::class, 'restore'])->withTrashed()->name('headlines.restore');
    Route::get('promotions', [PromotionController::class, 'index'])->name('promotions.index');
    Route::get('promotions/{product}/edit', [PromotionController::class, 'edit'])->name('promotions.edit');
    Route::put('promotions/{product}', [PromotionController::class, 'update'])->name('promotions.update');
    Route::resource('categories', CategoryController::class)->except('show');
    Route::resource('products', ProductController::class)->except('show');
    Route::resource('products.colors', ProductColorController::class)->only(['index', 'store', 'edit', 'update', 'destroy'])->scoped();
    Route::patch('products/{product}/colors/{color}/restore', [ProductColorController::class, 'restore'])->scopeBindings()->withTrashed()->name('products.colors.restore');
    Route::resource('sales', SaleController::class)->except('show');
    Route::patch('categories/{category}/restore', [CategoryController::class, 'restore'])->withTrashed()->name('categories.restore');
    Route::patch('products/{product}/restore', [ProductController::class, 'restore'])->withTrashed()->name('products.restore');
    Route::patch('sales/{sale}/restore', [SaleController::class, 'restore'])->withTrashed()->name('sales.restore');
});
