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
        Schema::create('print_jobs', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('session_id');
            $table->string('file_id');
            $table->foreignId('printer_id')->nullable()->constrained('printers')->nullOnDelete();
            $table->unsignedInteger('copies')->default(1);
            $table->string('paper_size')->default('A4'); // A4, Letter, Legal
            $table->string('paper_type')->default('bond'); // bond, glossy, matte, photo
            $table->string('orientation')->default('portrait'); // portrait, landscape
            $table->string('color_mode')->default('bw'); // color, bw
            $table->boolean('duplex')->default(false);
            $table->json('adjustments')->nullable();
            $table->unsignedInteger('page_count')->default(1);
            $table->decimal('total_price', 10, 2)->default(0.00);
            $table->string('payment_status')->default('pending'); // pending, paid, failed, etc.
            $table->string('status')->default('pending'); // pending, paid, queued, printing, completed, failed, cancelled
            $table->unsignedInteger('pages_completed')->default(0);
            $table->unsignedInteger('progress')->default(0); // 0 to 100 percentage
            $table->string('idempotency_key')->nullable()->index();
            $table->text('error_message')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('session_id')
                ->references('id')
                ->on('printing_sessions')
                ->cascadeOnDelete();

            $table->foreign('file_id')
                ->references('id')
                ->on('uploaded_files')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
    }
};
