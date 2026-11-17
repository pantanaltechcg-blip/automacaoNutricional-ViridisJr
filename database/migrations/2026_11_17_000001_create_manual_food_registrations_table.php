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
        Schema::create('manual_food_registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('food_id')->unique()->constrained('foods')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->integer('current_version_number');
            $table->string('reference_unit', 2)->default('g');
            $table->timestamps();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE manual_food_registrations
            ADD CONSTRAINT manual_food_registrations_current_version_number_check
            CHECK (current_version_number >= 1)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE manual_food_registrations
            ADD CONSTRAINT manual_food_registrations_reference_unit_check
            CHECK (reference_unit = 'g')
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manual_food_registrations');
    }
};
