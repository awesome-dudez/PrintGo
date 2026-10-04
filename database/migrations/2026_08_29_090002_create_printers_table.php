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
        Schema::create('printers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('model');
            $table->string('connection_type')->default('agent'); // network, usb, agent
            $table->string('ip_address')->nullable();
            $table->string('status')->default('online'); // online, offline, busy, error
            $table->boolean('is_ready')->default(true);
            $table->text('error_message')->nullable();
            $table->string('paper_status')->default('available'); // available, low, empty, unknown
            $table->json('ink_status')->nullable();
            $table->string('current_job_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('printers');
    }
};
