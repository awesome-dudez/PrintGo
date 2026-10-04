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
        Schema::create('uploaded_files', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('session_id');
            $table->string('original_name');
            $table->string('stored_path');
            $table->string('file_type'); // pdf, docx, pptx, jpg, png, heic, webp
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->unsignedInteger('page_count')->default(0);
            $table->string('status')->default('processing'); // processing, ready, failed, expired, deleted
            $table->boolean('preview_available')->default(false);
            $table->json('preview_paths')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('session_id')
                ->references('id')
                ->on('printing_sessions')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('uploaded_files');
    }
};
