<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serving_daily_value_nutrient_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('serving_daily_value_nutrient_id')->constrained('serving_daily_value_nutrients')->restrictOnDelete();
            $table->text('state');
            $table->unique(['serving_daily_value_nutrient_id', 'state'], 'serving_nutrient_state_unique');
        });
        DB::statement("ALTER TABLE serving_daily_value_nutrient_states ADD CONSTRAINT serving_nutrient_states_check CHECK (state IN ('TRACE','NA','ABSENT','REEVALUATION'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('serving_daily_value_nutrient_states');
    }
};
