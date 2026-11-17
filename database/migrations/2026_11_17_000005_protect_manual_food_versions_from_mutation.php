<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<string> */
    private const IMMUTABLE_TABLES = [
        'manual_food_versions',
        'manual_food_version_nutrients',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_manual_food_version_mutation()
            RETURNS TRIGGER AS $$
            BEGIN
                RAISE EXCEPTION 'Manual food version history is immutable.';
            END;
            $$ LANGUAGE plpgsql
            SQL);

        foreach (self::IMMUTABLE_TABLES as $table) {
            DB::statement(<<<SQL
                CREATE TRIGGER {$table}_prevent_mutation
                BEFORE UPDATE OR DELETE ON {$table}
                FOR EACH ROW
                EXECUTE FUNCTION prevent_manual_food_version_mutation()
                SQL);
        }

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_taco_food_manual_registration()
            RETURNS TRIGGER AS $$
            BEGIN
                IF EXISTS (
                    SELECT 1
                    FROM foods
                    WHERE id = NEW.food_id
                      AND UPPER(BTRIM(source)) = 'TACO'
                ) THEN
                    RAISE EXCEPTION 'TACO foods cannot be linked to manual_food_registrations.';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
            SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER manual_food_registrations_prevent_taco
            BEFORE INSERT OR UPDATE ON manual_food_registrations
            FOR EACH ROW
            EXECUTE FUNCTION prevent_taco_food_manual_registration()
            SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_registered_manual_food_from_becoming_taco()
            RETURNS TRIGGER AS $$
            BEGIN
                IF UPPER(BTRIM(NEW.source)) = 'TACO'
                   AND EXISTS (
                       SELECT 1
                       FROM manual_food_registrations
                       WHERE food_id = NEW.id
                   ) THEN
                    RAISE EXCEPTION 'Registered manual foods cannot use source TACO.';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
            SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER foods_prevent_manual_food_taco_source
            BEFORE UPDATE ON foods
            FOR EACH ROW
            EXECUTE FUNCTION prevent_registered_manual_food_from_becoming_taco()
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS foods_prevent_manual_food_taco_source ON foods');
        DB::statement('DROP FUNCTION IF EXISTS prevent_registered_manual_food_from_becoming_taco()');
        DB::statement('DROP TRIGGER IF EXISTS manual_food_registrations_prevent_taco ON manual_food_registrations');
        DB::statement('DROP FUNCTION IF EXISTS prevent_taco_food_manual_registration()');

        foreach (array_reverse(self::IMMUTABLE_TABLES) as $table) {
            DB::statement("DROP TRIGGER IF EXISTS {$table}_prevent_mutation ON {$table}");
        }

        DB::statement('DROP FUNCTION IF EXISTS prevent_manual_food_version_mutation()');
    }
};
