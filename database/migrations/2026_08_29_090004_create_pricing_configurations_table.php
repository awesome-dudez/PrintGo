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
        Schema::create('pricing_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('item_key')->unique();
            $table->integer('price_in_cents')->default(0);
            $table->decimal('price', 10, 2)->default(0.00);
            $table->string('currency')->default('PHP');
            $table->boolean('is_active')->default(true);
            $table->string('description')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pricing_configurations');
    }
};
