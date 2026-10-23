<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('headlines', function (Blueprint $table): void {
            $table->id();
            $table->string('eyebrow', 80)->nullable();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->string('mobile_image_path')->nullable();
            $table->string('button_text', 40)->default('Jelajahi koleksi');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('theme', 10)->default('dark');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('headlines');
    }
};
