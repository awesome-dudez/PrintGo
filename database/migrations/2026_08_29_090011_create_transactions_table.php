<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('print_job_id')->nullable();
            $table->string('session_id')->nullable()->index();
            $table->string('file_type')->index();
            $table->unsignedInteger('page_count')->index();
            $table->unsignedInteger('copies')->default(1);
            $table->string('paper_size');
            $table->string('paper_type');
            $table->string('orientation');
            $table->string('color_mode');
            $table->boolean('duplex')->default(false);
            $table->decimal('amount', 10, 2)->index();
            $table->string('currency')->default('PHP');
            $table->string('payment_status')->default('paid');
            $table->string('print_status')->default('completed');
            $table->timestamps();

            $table->index('created_at');

            $table->foreign('print_job_id')
                ->references('id')
                ->on('print_jobs')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
