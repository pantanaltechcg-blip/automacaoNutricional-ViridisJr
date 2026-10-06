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
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE food_nutrients DROP CONSTRAINT IF EXISTS food_nutrients_status_check');
            DB::statement("ALTER TABLE food_nutrients ADD CONSTRAINT food_nutrients_status_check CHECK (status IN ('EXACT', 'TRACE', 'NA', 'ABSENT', 'REEVALUATION'))");

            return;
        }

        Schema::table('food_nutrients', function (Blueprint $table) {
            $table->enum('status', ['EXACT', 'TRACE', 'NA', 'ABSENT', 'REEVALUATION'])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE food_nutrients DROP CONSTRAINT IF EXISTS food_nutrients_status_check');
            DB::statement("ALTER TABLE food_nutrients ADD CONSTRAINT food_nutrients_status_check CHECK (status IN ('EXACT', 'TRACE', 'NA', 'ABSENT'))");

            return;
        }

        Schema::table('food_nutrients', function (Blueprint $table) {
            $table->enum('status', ['EXACT', 'TRACE', 'NA', 'ABSENT'])->change();
        });
    }
};
