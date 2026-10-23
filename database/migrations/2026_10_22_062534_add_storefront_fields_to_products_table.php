<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('is_new')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->boolean('sale_enabled')->default(false);
            $table->decimal('sale_price', 15, 2)->nullable();
            $table->string('sale_title', 100)->nullable();
            $table->date('sale_starts_on')->nullable();
            $table->date('sale_ends_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['is_new', 'is_featured', 'sale_enabled', 'sale_price', 'sale_title', 'sale_starts_on', 'sale_ends_on']);
        });
    }
};
