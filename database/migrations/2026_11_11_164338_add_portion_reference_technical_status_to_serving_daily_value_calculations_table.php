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
        DB::statement(<<<'SQL'
            ALTER TABLE serving_daily_value_calculations
            ADD COLUMN portion_reference_technical_status TEXT NOT NULL DEFAULT 'AUTHORIZED'
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE serving_daily_value_calculations
            ADD CONSTRAINT serving_daily_value_portion_reference_status_check
            CHECK (portion_reference_technical_status IN ('AUTHORIZED', 'BLOCKED'))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE serving_daily_value_calculations
            ALTER COLUMN portion_reference_technical_status DROP DEFAULT
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE serving_daily_value_calculations
            DROP CONSTRAINT IF EXISTS serving_daily_value_portion_reference_status_check
            SQL);
        Schema::table('serving_daily_value_calculations', function (Blueprint $table): void {
            $table->dropColumn('portion_reference_technical_status');
        });
    }
};
