<?php

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
        Schema::create('food_nutrients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('food_id')->constrained('foods')->cascadeOnDelete();
            $table->foreignId('nutrient_id')->constrained('nutrients')->cascadeOnDelete();
            $table->decimal('amount_value', 7, 2)->nullable();
            $table->enum('status', ['EXACT', 'TRACE', 'NA', 'ABSENT']);
            $table->timestamps();

            $table->unique(['food_id', 'nutrient_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('food_nutrients');
    }
};
