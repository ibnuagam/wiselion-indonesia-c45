<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->string('data_origin', 20)->default('legacy');
            $table->string('reference_label', 20)->nullable();
            $table->string('label_basis', 500)->nullable();
            $table->foreignId('labelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('labelled_at')->nullable();
            $table->foreignId('sales_import_id')->nullable()->constrained('sales_imports')->restrictOnDelete();
            $table->index(['data_origin', 'year', 'month']);
        });
        Schema::table('analyses', function (Blueprint $table): void {
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('data_origin', 20)->nullable();
            $table->string('algorithm_version', 80)->nullable();
            $table->string('dataset_hash', 64)->nullable();
            $table->text('label_policy')->nullable();
            $table->json('parameters')->nullable();
            $table->json('snapshot')->nullable();
            $table->json('rules')->nullable();
            $table->json('evaluation')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('analyses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['data_origin', 'algorithm_version', 'dataset_hash', 'label_policy', 'parameters', 'snapshot', 'rules', 'evaluation']);
        });
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropIndex(['data_origin', 'year', 'month']);
            $table->dropConstrainedForeignId('labelled_by');
            $table->dropConstrainedForeignId('sales_import_id');
            $table->dropColumn(['data_origin', 'reference_label', 'label_basis', 'labelled_at']);
        });
    }
};
