<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nutrition_calculation_nutrient_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nutrition_calculation_nutrient_id')
                ->constrained('nutrition_calculation_nutrients')
                ->cascadeOnDelete();
            $table->text('state');

            $table->unique(
                ['nutrition_calculation_nutrient_id', 'state'],
                'nutrition_calc_nutrient_states_state_unique',
            );
        });

        DB::statement(<<<'SQL'
            ALTER TABLE nutrition_calculation_nutrient_states
            ADD CONSTRAINT nutrition_calculation_nutrient_states_state_check
            CHECK (state IN ('TRACE', 'NA', 'ABSENT', 'REEVALUATION'))
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('nutrition_calculation_nutrient_states');
    }
};
