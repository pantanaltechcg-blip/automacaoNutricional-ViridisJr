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
        Schema::create('added_sugars_evaluation_causes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('evaluation_id')->constrained('added_sugars_evaluations')->restrictOnDelete();
            $table->foreignId('evaluation_item_id')->nullable()->constrained('added_sugars_evaluation_items')->restrictOnDelete();
            $table->text('cause_type');
            $table->text('cause_code');
            $table->text('detail')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('added_sugars_evaluation_causes');
    }
};
