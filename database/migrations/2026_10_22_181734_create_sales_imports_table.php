<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('source_name', 180);
            $table->string('checksum', 64);
            $table->string('data_origin', 20);
            $table->string('status', 20)->default('preview');
            $table->json('rows');
            $table->json('errors');
            $table->unsignedInteger('row_count');
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_imports');
    }
};
