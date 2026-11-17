<?php

namespace Tests\Feature;

use App\Models\Food;
use App\Models\FoodNutrient;
use App\Models\ManualFoodRegistration;
use App\Models\ManualFoodVersion;
use App\Models\ManualFoodVersionNutrient;
use App\Models\Nutrient;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ManualFoodPersistenceTest extends TestCase
{
    use DatabaseTransactions;

    /** @var list<string> */
    private const TABLES = [
        'manual_food_registrations',
        'manual_food_versions',
        'manual_food_version_nutrients',
    ];

    public function test_all_planned_postgresql_tables_and_columns_exist_with_approved_definitions(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());

        foreach (self::TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }

        $expectedColumns = [
            'manual_food_registrations' => [
                'id' => ['bigint', 64, 0, null, 'NO'],
                'food_id' => ['bigint', 64, 0, null, 'NO'],
                'is_active' => ['boolean', null, null, null, 'NO'],
                'current_version_number' => ['integer', 32, 0, null, 'NO'],
                'reference_unit' => ['character varying', null, null, 2, 'NO'],
                'created_at' => ['timestamp without time zone', null, null, null, 'YES'],
                'updated_at' => ['timestamp without time zone', null, null, null, 'YES'],
            ],
            'manual_food_versions' => [
                'id' => ['bigint', 64, 0, null, 'NO'],
                'manual_food_registration_id' => ['bigint', 64, 0, null, 'NO'],
                'version_number' => ['integer', 32, 0, null, 'NO'],
                'name' => ['text', null, null, null, 'NO'],
                'category' => ['text', null, null, null, 'YES'],
                'source' => ['text', null, null, null, 'NO'],
                'source_code' => ['text', null, null, null, 'NO'],
                'reference_unit' => ['character varying', null, null, 2, 'NO'],
                'evidence_reference' => ['text', null, null, null, 'NO'],
                'evidence_source' => ['text', null, null, null, 'NO'],
                'registered_by' => ['text', null, null, null, 'NO'],
                'registered_on' => ['date', null, null, null, 'NO'],
                'change_reason' => ['text', null, null, null, 'YES'],
                'created_at' => ['timestamp without time zone', null, null, null, 'NO'],
            ],
            'manual_food_version_nutrients' => [
                'id' => ['bigint', 64, 0, null, 'NO'],
                'manual_food_version_id' => ['bigint', 64, 0, null, 'NO'],
                'nutrient_id' => ['bigint', 64, 0, null, 'NO'],
                'amount_value' => ['numeric', 7, 2, null, 'YES'],
                'status' => ['text', null, null, null, 'NO'],
            ],
        ];

        foreach ($expectedColumns as $table => $columns) {
            $actualColumns = $this->columnsFor($table);
            $this->assertSame(array_keys($columns), array_keys($actualColumns), $table);

            foreach ($columns as $column => $definition) {
                $this->assertSame($definition, $actualColumns[$column], "{$table}.{$column}");
            }
        }
    }

    public function test_foreign_keys_use_on_delete_restrict_and_block_parent_deletion(): void
    {
        $this->assertSame([
            'manual_food_registrations.food_id -> foods.id ON DELETE RESTRICT',
            'manual_food_version_nutrients.manual_food_version_id -> manual_food_versions.id ON DELETE RESTRICT',
            'manual_food_version_nutrients.nutrient_id -> nutrients.id ON DELETE RESTRICT',
            'manual_food_versions.manual_food_registration_id -> manual_food_registrations.id ON DELETE RESTRICT',
        ], $this->foreignKeys());

        $aggregate = $this->seedCompleteManualFoodAggregate();

        $this->assertQueryException(function () use ($aggregate): void {
            DB::table('foods')->where('id', $aggregate['food']->id)->delete();
        }, '23503');
        $this->assertQueryException(function () use ($aggregate): void {
            DB::table('manual_food_registrations')->where('id', $aggregate['registration']->id)->delete();
        }, '23503');
        $this->assertQueryException(function () use ($aggregate): void {
            DB::table('nutrients')->where('id', $aggregate['nutrient']->id)->delete();
        }, '23503');
    }

    public function test_unique_constraints_reject_duplicate_registration_version_and_nutrient_rows(): void
    {
        $aggregate = $this->seedCompleteManualFoodAggregate();

        $this->assertQueryException(function () use ($aggregate): void {
            DB::table('manual_food_registrations')->insert([
                'food_id' => $aggregate['food']->id,
                'is_active' => true,
                'current_version_number' => 1,
                'reference_unit' => 'g',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 'manual_food_registrations_food_id_unique');

        $this->assertQueryException(function () use ($aggregate): void {
            DB::table('manual_food_versions')->insert([
                'manual_food_registration_id' => $aggregate['registration']->id,
                'version_number' => 1,
                'name' => 'Duplicada',
                'category' => null,
                'source' => 'MANUAL_TEST',
                'source_code' => 'DUP-V1',
                'reference_unit' => 'g',
                'evidence_reference' => 'EV-1',
                'evidence_source' => 'Lab',
                'registered_by' => 'Responsavel',
                'registered_on' => '2026-11-17',
                'change_reason' => null,
                'created_at' => now(),
            ]);
        }, 'manual_food_versions_registration_version_unique');

        $this->assertQueryException(function () use ($aggregate): void {
            DB::table('manual_food_version_nutrients')->insert([
                'manual_food_version_id' => $aggregate['version']->id,
                'nutrient_id' => $aggregate['nutrient']->id,
                'amount_value' => '1.00',
                'status' => 'EXACT',
            ]);
        }, 'manual_food_version_nutrients_version_nutrient_unique');
    }

    #[DataProvider('invalidRegistrationRowProvider')]
    public function test_manual_food_registrations_check_constraints_reject_invalid_rows(
        int $currentVersionNumber,
        string $referenceUnit,
        string $expectedConstraint,
    ): void {
        $food = $this->createManualFood('REG-CHECK');

        $this->assertQueryException(function () use ($food, $currentVersionNumber, $referenceUnit): void {
            DB::table('manual_food_registrations')->insert([
                'food_id' => $food->id,
                'is_active' => true,
                'current_version_number' => $currentVersionNumber,
                'reference_unit' => $referenceUnit,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, $expectedConstraint);
    }

    /**
     * @return array<string, array{int, string, string}>
     */
    public static function invalidRegistrationRowProvider(): array
    {
        return [
            'version zero' => [0, 'g', 'manual_food_registrations_current_version_number_check'],
            'negative version' => [-1, 'g', 'manual_food_registrations_current_version_number_check'],
            'reference unit ml' => [1, 'ml', 'manual_food_registrations_reference_unit_check'],
            'reference unit uppercase G' => [1, 'G', 'manual_food_registrations_reference_unit_check'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidVersionRowProvider')]
    public function test_manual_food_versions_check_constraints_reject_invalid_rows(
        array $overrides,
        string $expectedConstraint,
    ): void {
        $food = $this->createManualFood('VER-CHECK-'.str()->uuid()->toString());
        $registration = ManualFoodRegistration::query()->create([
            'food_id' => $food->id,
            'is_active' => true,
            'current_version_number' => 1,
            'reference_unit' => 'g',
        ]);

        $row = array_merge([
            'manual_food_registration_id' => $registration->id,
            'version_number' => 1,
            'name' => 'Alimento válido',
            'category' => 'Categoria válida',
            'source' => 'FABRICANTE_TESTE',
            'source_code' => 'CODE-1',
            'reference_unit' => 'g',
            'evidence_reference' => 'LAUDO-1',
            'evidence_source' => 'Lab Teste',
            'registered_by' => 'Nutricionista',
            'registered_on' => '2026-11-17',
            'change_reason' => null,
            'created_at' => now(),
        ], $overrides);

        $this->assertQueryException(function () use ($row): void {
            DB::table('manual_food_versions')->insert($row);
        }, $expectedConstraint);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidVersionRowProvider(): array
    {
        return [
            'version_number zero' => [['version_number' => 0, 'change_reason' => 'Justificativa'], 'manual_food_versions_version_number_check'],
            'blank name' => [['name' => '   '], 'manual_food_versions_name_check'],
            'blank category' => [['category' => '   '], 'manual_food_versions_category_check'],
            'blank source' => [['source' => '   '], 'manual_food_versions_source_check'],
            'source TACO exact' => [['source' => 'TACO'], 'manual_food_versions_source_check'],
            'source taco padded' => [['source' => ' taco '], 'manual_food_versions_source_check'],
            'blank source_code' => [['source_code' => '   '], 'manual_food_versions_source_code_check'],
            'reference_unit ml' => [['reference_unit' => 'ml'], 'manual_food_versions_reference_unit_check'],
            'blank evidence_reference' => [['evidence_reference' => '   '], 'manual_food_versions_evidence_reference_check'],
            'blank evidence_source' => [['evidence_source' => '   '], 'manual_food_versions_evidence_source_check'],
            'blank registered_by' => [['registered_by' => '   '], 'manual_food_versions_registered_by_check'],
            'version 2 with null change_reason' => [['version_number' => 2, 'change_reason' => null], 'manual_food_versions_change_reason_check'],
            'version 2 with blank change_reason' => [['version_number' => 2, 'change_reason' => '   '], 'manual_food_versions_change_reason_check'],
        ];
    }

    #[DataProvider('invalidVersionNutrientRowProvider')]
    public function test_manual_food_version_nutrients_check_constraints_reject_invalid_rows(
        string $status,
        ?string $amountValue,
        string $expectedConstraint,
    ): void {
        $aggregate = $this->seedCompleteManualFoodAggregate();
        $extraNutrient = Nutrient::query()->create([
            'name' => 'Nutriente check '.str()->uuid()->toString(),
            'unit' => 'g',
        ]);

        $this->assertQueryException(function () use ($aggregate, $extraNutrient, $status, $amountValue): void {
            DB::table('manual_food_version_nutrients')->insert([
                'manual_food_version_id' => $aggregate['version']->id,
                'nutrient_id' => $extraNutrient->id,
                'amount_value' => $amountValue,
                'status' => $status,
            ]);
        }, $expectedConstraint);
    }

    /**
     * @return array<string, array{string, ?string, string}>
     */
    public static function invalidVersionNutrientRowProvider(): array
    {
        return [
            'invalid status' => ['UNKNOWN', null, 'manual_food_version_nutrients_status_check'],
            'EXACT with null amount' => ['EXACT', null, 'manual_food_version_nutrients_amount_check'],
            'EXACT with negative amount' => ['EXACT', '-0.01', 'manual_food_version_nutrients_amount_check'],
            'TRACE with numeric amount' => ['TRACE', '0.00', 'manual_food_version_nutrients_amount_check'],
            'NA with numeric amount' => ['NA', '1.00', 'manual_food_version_nutrients_amount_check'],
            'ABSENT with numeric amount' => ['ABSENT', '0.00', 'manual_food_version_nutrients_amount_check'],
            'REEVALUATION with numeric amount' => ['REEVALUATION', '2.50', 'manual_food_version_nutrients_amount_check'],
        ];
    }

    public function test_manual_food_version_nutrients_accepts_zero_exact_and_all_qualitative_statuses(): void
    {
        $aggregate = $this->seedCompleteManualFoodAggregate();
        $statuses = [
            [FoodNutrient::STATUS_EXACT, '0.00'],
            [FoodNutrient::STATUS_TRACE, null],
            [FoodNutrient::STATUS_NA, null],
            [FoodNutrient::STATUS_ABSENT, null],
            [FoodNutrient::STATUS_REEVALUATION, null],
        ];

        foreach ($statuses as [$status, $amount]) {
            $nutrient = Nutrient::query()->create([
                'name' => "Nutriente {$status} ".str()->uuid()->toString(),
                'unit' => 'g',
            ]);

            $row = ManualFoodVersionNutrient::query()->create([
                'manual_food_version_id' => $aggregate['version']->id,
                'nutrient_id' => $nutrient->id,
                'amount_value' => $amount,
                'status' => $status,
            ]);

            $this->assertSame($status, $row->status);
            $this->assertSame($amount, $row->amount_value);
        }
    }

    public function test_foods_normalized_unique_indexes_block_case_and_whitespace_duplicates_without_blocking_null_source_code(): void
    {
        $indexes = collect(DB::select(<<<'SQL'
            SELECT indexname, indexdef
            FROM pg_indexes
            WHERE schemaname = current_schema()
              AND tablename = 'foods'
              AND indexname IN ('foods_normalized_source_code_unique', 'foods_normalized_source_name_unique')
            ORDER BY indexname
            SQL))->pluck('indexdef', 'indexname')->all();

        $this->assertArrayHasKey('foods_normalized_source_code_unique', $indexes);
        $this->assertArrayHasKey('foods_normalized_source_name_unique', $indexes);

        $uuid = str()->uuid()->toString();
        Food::query()->create([
            'name' => "Polpa Base {$uuid}",
            'category' => 'Frutas',
            'source' => "FABRICANTE_{$uuid}",
            'source_code' => "COD-{$uuid}",
        ]);

        $this->assertQueryException(function () use ($uuid): void {
            Food::query()->create([
                'name' => "Outro Nome {$uuid}",
                'category' => 'Frutas',
                'source' => " fabricante_{$uuid} ",
                'source_code' => " cod-{$uuid} ",
            ]);
        }, 'foods_normalized_source_code_unique');

        $this->assertQueryException(function () use ($uuid): void {
            Food::query()->create([
                'name' => "  polpa base {$uuid} ",
                'category' => 'Frutas',
                'source' => " fabricante_{$uuid} ",
                'source_code' => "OUTRO-COD-{$uuid}",
            ]);
        }, 'foods_normalized_source_name_unique');

        Food::query()->create([
            'name' => "Sem codigo A {$uuid}",
            'category' => null,
            'source' => "FABRICANTE_{$uuid}",
            'source_code' => null,
        ]);
        Food::query()->create([
            'name' => "Sem codigo B {$uuid}",
            'category' => null,
            'source' => "FABRICANTE_{$uuid}",
            'source_code' => null,
        ]);

        $this->assertSame(
            3,
            Food::query()->where('source', "FABRICANTE_{$uuid}")->count(),
        );
    }

    #[DataProvider('tacoSourceVariantProvider')]
    public function test_trigger_blocks_manual_food_registration_for_taco_foods_and_prevents_registered_food_from_becoming_taco(
        string $tacoSource,
    ): void {
        $tacoFood = Food::query()->create([
            'name' => 'TACO protegido '.str()->uuid()->toString(),
            'category' => 'Cereais',
            'source' => $tacoSource,
            'source_code' => 'TACO-PROT-'.str()->uuid()->toString(),
        ]);

        $this->assertQueryException(function () use ($tacoFood): void {
            ManualFoodRegistration::query()->create([
                'food_id' => $tacoFood->id,
                'is_active' => true,
                'current_version_number' => 1,
                'reference_unit' => 'g',
            ]);
        }, 'TACO');

        $manualFood = $this->createManualFood('LINK-UPDATE-TEST');
        $registration = ManualFoodRegistration::query()->create([
            'food_id' => $manualFood->id,
            'is_active' => true,
            'current_version_number' => 1,
            'reference_unit' => 'g',
        ]);

        $this->assertQueryException(function () use ($registration, $tacoFood): void {
            DB::table('manual_food_registrations')
                ->where('id', $registration->id)
                ->update(['food_id' => $tacoFood->id]);
        }, 'TACO');

        $this->assertQueryException(function () use ($manualFood, $tacoSource): void {
            DB::table('foods')
                ->where('id', $manualFood->id)
                ->update(['source' => $tacoSource]);
        }, 'TACO');

        $unlinkedFood = Food::query()->create([
            'name' => 'TACO sem vínculo '.str()->uuid()->toString(),
            'category' => 'Cereais',
            'source' => $tacoSource,
            'source_code' => 'TACO-UNLINKED-'.str()->uuid()->toString(),
        ]);
        $unlinkedFood->update(['source' => 'FONTE-ALTERADA']);

        $this->assertSame('FONTE-ALTERADA', $unlinkedFood->fresh()->source);
        $this->assertDatabaseHas('foods', ['id' => $tacoFood->id]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function tacoSourceVariantProvider(): array
    {
        return [
            'exact uppercase' => ['TACO'],
            'lowercase padded' => [' taco '],
            'mixed case' => ['TaCo'],
        ];
    }

    public function test_immutability_triggers_are_installed_and_block_update_and_delete_on_versions_and_nutrients(): void
    {
        $protectedTables = collect(DB::select(<<<'SQL'
            SELECT c.relname AS table_name
            FROM pg_trigger t
            JOIN pg_class c ON c.oid = t.tgrelid
            JOIN pg_proc p ON p.oid = t.tgfoid
            WHERE NOT t.tgisinternal
              AND p.proname = 'prevent_manual_food_version_mutation'
            ORDER BY c.relname
            SQL))->pluck('table_name')->all();

        $this->assertSame([
            'manual_food_version_nutrients',
            'manual_food_versions',
        ], $protectedTables);

        $aggregate = $this->seedCompleteManualFoodAggregate();

        $targets = [
            'manual_food_versions' => $aggregate['version']->id,
            'manual_food_version_nutrients' => $aggregate['version_nutrient']->id,
        ];

        foreach ($targets as $table => $id) {
            foreach (['UPDATE', 'DELETE'] as $operation) {
                $this->assertQueryException(function () use ($table, $operation, $id): void {
                    $sql = $operation === 'UPDATE'
                        ? "UPDATE {$table} SET id = id WHERE id = ?"
                        : "DELETE FROM {$table} WHERE id = ?";
                    DB::statement($sql, [$id]);
                }, 'immutable');

                $this->assertDatabaseHas($table, ['id' => $id]);
            }
        }
    }

    public function test_eloquent_models_timestamps_constants_casts_and_relationships_follow_contract(): void
    {
        $registrationModel = new ManualFoodRegistration;
        $versionModel = new ManualFoodVersion;
        $versionNutrientModel = new ManualFoodVersionNutrient;

        $this->assertTrue($registrationModel->usesTimestamps());
        $this->assertTrue($versionModel->usesTimestamps());
        $this->assertNull(ManualFoodVersion::UPDATED_AT);
        $this->assertFalse($versionNutrientModel->usesTimestamps());

        $this->assertSame(FoodNutrient::STATUS_EXACT, ManualFoodVersionNutrient::STATUS_EXACT);
        $this->assertSame(FoodNutrient::STATUS_TRACE, ManualFoodVersionNutrient::STATUS_TRACE);
        $this->assertSame(FoodNutrient::STATUS_NA, ManualFoodVersionNutrient::STATUS_NA);
        $this->assertSame(FoodNutrient::STATUS_ABSENT, ManualFoodVersionNutrient::STATUS_ABSENT);
        $this->assertSame(FoodNutrient::STATUS_REEVALUATION, ManualFoodVersionNutrient::STATUS_REEVALUATION);

        $aggregate = $this->seedCompleteManualFoodAggregate();
        $registration = $aggregate['registration'];
        $food = $aggregate['food'];

        $secondNutrient = Nutrient::query()->create([
            'name' => 'Segundo nutriente '.str()->uuid()->toString(),
            'unit' => 'mg',
        ]);
        $versionTwo = ManualFoodVersion::query()->create([
            'manual_food_registration_id' => $registration->id,
            'version_number' => 2,
            'name' => $food->name,
            'category' => $food->category,
            'source' => $food->source,
            'source_code' => $food->source_code,
            'reference_unit' => 'g',
            'evidence_reference' => 'EV-2',
            'evidence_source' => 'Lab 2',
            'registered_by' => 'Auditor',
            'registered_on' => '2026-11-18',
            'change_reason' => 'Correção laboratorial',
        ]);
        ManualFoodVersionNutrient::query()->create([
            'manual_food_version_id' => $versionTwo->id,
            'nutrient_id' => $secondNutrient->id,
            'amount_value' => null,
            'status' => ManualFoodVersionNutrient::STATUS_TRACE,
        ]);
        ManualFoodVersionNutrient::query()->create([
            'manual_food_version_id' => $versionTwo->id,
            'nutrient_id' => $aggregate['nutrient']->id,
            'amount_value' => '15.25',
            'status' => ManualFoodVersionNutrient::STATUS_EXACT,
        ]);
        $registration->update(['current_version_number' => 2]);

        $loadedFood = Food::query()->with('manualRegistration')->findOrFail($food->id);
        $this->assertNotNull($loadedFood->manualRegistration);
        $this->assertTrue($loadedFood->manualRegistration->is_active);
        $this->assertSame(2, $loadedFood->manualRegistration->current_version_number);

        $loadedRegistration = ManualFoodRegistration::query()
            ->with(['food', 'versions.nutrients.nutrient', 'currentVersion.nutrients.nutrient'])
            ->findOrFail($registration->id);

        $this->assertSame($food->id, $loadedRegistration->food->id);
        $this->assertSame([1, 2], $loadedRegistration->versions->pluck('version_number')->all());
        $this->assertSame(2, $loadedRegistration->currentVersion->version_number);
        $this->assertSame('2026-11-18', $loadedRegistration->currentVersion->registered_on);
        $this->assertSame($registration->id, $loadedRegistration->currentVersion->registration->id);

        $expectedNutrientIds = [
            min($aggregate['nutrient']->id, $secondNutrient->id),
            max($aggregate['nutrient']->id, $secondNutrient->id),
        ];
        $this->assertSame(
            $expectedNutrientIds,
            $loadedRegistration->currentVersion->nutrients->pluck('nutrient_id')->all(),
        );
        $this->assertSame(
            $versionTwo->id,
            $loadedRegistration->currentVersion->nutrients->first()->version->id,
        );
        $this->assertInstanceOf(
            Nutrient::class,
            $loadedRegistration->currentVersion->nutrients->first()->nutrient,
        );
    }

    /**
     * @return array<string, array{string, ?int, ?int, ?int, string}>
     */
    private function columnsFor(string $table): array
    {
        return collect(DB::select(<<<'SQL'
            SELECT column_name, data_type, numeric_precision, numeric_scale, character_maximum_length, is_nullable
            FROM information_schema.columns
            WHERE table_schema = current_schema()
              AND table_name = ?
            ORDER BY ordinal_position
            SQL, [$table]))
            ->mapWithKeys(fn (object $column): array => [
                $column->column_name => [
                    $column->data_type,
                    $column->numeric_precision === null ? null : (int) $column->numeric_precision,
                    $column->numeric_scale === null ? null : (int) $column->numeric_scale,
                    $column->character_maximum_length === null ? null : (int) $column->character_maximum_length,
                    $column->is_nullable,
                ],
            ])
            ->all();
    }

    /**
     * @return list<string>
     */
    private function foreignKeys(): array
    {
        return collect(DB::select(<<<'SQL'
            SELECT
                source_table.relname || '.' || source_column.attname || ' -> ' ||
                target_table.relname || '.' || target_column.attname || ' ON DELETE ' ||
                CASE constraint_data.confdeltype
                    WHEN 'a' THEN 'NO ACTION'
                    WHEN 'c' THEN 'CASCADE'
                    WHEN 'n' THEN 'SET NULL'
                    WHEN 'r' THEN 'RESTRICT'
                    WHEN 'd' THEN 'SET DEFAULT'
                END AS relationship
            FROM pg_constraint AS constraint_data
            JOIN pg_class AS source_table ON source_table.oid = constraint_data.conrelid
            JOIN pg_namespace AS source_namespace ON source_namespace.oid = source_table.relnamespace
            JOIN pg_class AS target_table ON target_table.oid = constraint_data.confrelid
            JOIN unnest(constraint_data.conkey) WITH ORDINALITY AS source_key(attnum, position) ON true
            JOIN unnest(constraint_data.confkey) WITH ORDINALITY AS target_key(attnum, position)
                ON target_key.position = source_key.position
            JOIN pg_attribute AS source_column
                ON source_column.attrelid = source_table.oid AND source_column.attnum = source_key.attnum
            JOIN pg_attribute AS target_column
                ON target_column.attrelid = target_table.oid AND target_column.attnum = target_key.attnum
            WHERE constraint_data.contype = 'f'
              AND source_namespace.nspname = current_schema()
              AND source_table.relname LIKE 'manual_food%'
            ORDER BY relationship
            SQL))
            ->pluck('relationship')
            ->all();
    }

    private function createManualFood(string $label): Food
    {
        $uuid = str()->uuid()->toString();

        return Food::query()->create([
            'name' => "Alimento manual {$label} {$uuid}",
            'category' => 'Categoria manual',
            'source' => 'FABRICANTE_TESTE',
            'source_code' => "{$label}-{$uuid}",
        ]);
    }

    /**
     * @return array{
     *     food: Food,
     *     registration: ManualFoodRegistration,
     *     version: ManualFoodVersion,
     *     nutrient: Nutrient,
     *     version_nutrient: ManualFoodVersionNutrient
     * }
     */
    private function seedCompleteManualFoodAggregate(): array
    {
        $food = $this->createManualFood('AGGREGATE');
        $registration = ManualFoodRegistration::query()->create([
            'food_id' => $food->id,
            'is_active' => true,
            'current_version_number' => 1,
            'reference_unit' => 'g',
        ]);
        $version = ManualFoodVersion::query()->create([
            'manual_food_registration_id' => $registration->id,
            'version_number' => 1,
            'name' => $food->name,
            'category' => $food->category,
            'source' => $food->source,
            'source_code' => $food->source_code,
            'reference_unit' => 'g',
            'evidence_reference' => 'EV-1',
            'evidence_source' => 'Lab 1',
            'registered_by' => 'Responsável',
            'registered_on' => '2026-11-17',
            'change_reason' => null,
        ]);
        $nutrient = Nutrient::query()->create([
            'name' => 'Nutriente manual '.str()->uuid()->toString(),
            'unit' => 'g',
        ]);
        $versionNutrient = ManualFoodVersionNutrient::query()->create([
            'manual_food_version_id' => $version->id,
            'nutrient_id' => $nutrient->id,
            'amount_value' => '12.34',
            'status' => ManualFoodVersionNutrient::STATUS_EXACT,
        ]);

        return [
            'food' => $food,
            'registration' => $registration,
            'version' => $version,
            'nutrient' => $nutrient,
            'version_nutrient' => $versionNutrient,
        ];
    }

    private function assertQueryException(callable $callback, ?string $expectedMessageFragment = null): void
    {
        try {
            DB::transaction(function () use ($callback): void {
                $callback();
            });
            $this->fail('PostgreSQL constraint or trigger exception was expected.');
        } catch (QueryException $exception) {
            $this->assertNotSame('', $exception->getMessage());

            if ($expectedMessageFragment !== null) {
                $this->assertStringContainsStringIgnoringCase($expectedMessageFragment, $exception->getMessage());
            }
        }
    }
}
