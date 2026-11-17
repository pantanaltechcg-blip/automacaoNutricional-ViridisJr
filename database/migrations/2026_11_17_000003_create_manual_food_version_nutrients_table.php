<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('manual_food_version_nutrients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('manual_food_version_id')
                ->constrained('manual_food_versions')
                ->restrictOnDelete();
            $table->foreignId('nutrient_id')
                ->constrained('nutrients')
                ->restrictOnDelete();
            $table->decimal('amount_value', 7, 2)->nullable();
            $table->text('status');

            $table->unique(
                ['manual_food_version_id', 'nutrient_id'],
                'manual_food_version_nutrients_version_nutrient_unique',
            );
        });

        DB::statement(<<<'SQL'
            ALTER TABLE manual_food_version_nutrients
            ADD CONSTRAINT manual_food_version_nutrients_status_check
            CHECK (status IN ('EXACT', 'TRACE', 'NA', 'ABSENT', 'REEVALUATION'))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE manual_food_version_nutrients
            ADD CONSTRAINT manual_food_version_nutrients_amount_check
            CHECK (
                (
                    status = 'EXACT'
                    AND amount_value IS NOT NULL
                    AND amount_value >= 0
                    AND amount_value <> 'Infinity'::numeric
                    AND amount_value <> 'NaN'::numeric
                )
                OR (
                    status <> 'EXACT'
                    AND amount_value IS NULL
                )
            )
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manual_food_version_nutrients');
    }
};
