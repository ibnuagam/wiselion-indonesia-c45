<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->json('specifications')->nullable();
            $table->decimal('reference_price_max', 15, 2)->nullable();
            $table->string('price_note', 255)->nullable();
            $table->string('catalog_source', 255)->nullable();
            $table->json('source_metadata')->nullable();
            $table->timestamp('catalog_imported_at')->nullable();
            $table->boolean('catalog_reviewed')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['specifications', 'reference_price_max', 'price_note', 'catalog_source', 'source_metadata', 'catalog_imported_at', 'catalog_reviewed']);
        });
    }
};
