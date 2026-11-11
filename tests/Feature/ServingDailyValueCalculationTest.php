<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\NutritionCalculation;
use App\Models\PortionAuthorization;
use App\Models\Recipe;
use App\Models\RegulatoryProfile;
use App\Models\ServingDailyValueCalculation;
use App\Models\ServingDailyValueNutrient;
use App\Services\ServingDailyValueCalculationService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ServingDailyValueCalculationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_post_calculates_every_nutrient_and_preserves_zero_null_states_and_missing_relation(): void
    {
        $source = $this->sourceCalculation();
        $source->nutrients()->create([
            'nutrient_id' => 101,
            'nutrient_name' => 'Proteína',
            'nutrient_unit' => 'g',
            'total_numerator' => '50',
            'total_denominator' => '1',
            'is_complete' => true,
            'has_missing_relation' => false,
            'reference_basis' => '100',
            'reference_unit' => 'g',
            'reference_numerator' => '50',
            'reference_denominator' => '1',
        ]);
        $zero = $source->nutrients()->create([
            'nutrient_id' => 102,
            'nutrient_name' => 'Zero',
            'nutrient_unit' => 'g',
            'total_numerator' => '0',
            'total_denominator' => '1',
            'is_complete' => true,
            'has_missing_relation' => false,
            'reference_basis' => '100',
            'reference_unit' => 'g',
            'reference_numerator' => '0',
            'reference_denominator' => '1',
        ]);
        $unknown = $source->nutrients()->create([
            'nutrient_id' => 103,
            'nutrient_name' => 'Qualitativo',
            'nutrient_unit' => 'mg',
            'total_numerator' => null,
            'total_denominator' => null,
            'is_complete' => false,
            'has_missing_relation' => true,
            'reference_basis' => '100',
            'reference_unit' => 'g',
            'reference_numerator' => null,
            'reference_denominator' => null,
        ]);
        $unknown->states()->create(['state' => 'TRACE']);
        $profile = $this->blockedProfile();
        $authorization = $this->portionAuthorization($source, '20');

        $response = $this->postJson("/api/nutrition-calculations/{$source->id}/serving-daily-value-calculations", [
            'portion' => ['value' => ' 20 ', 'unit' => ' g ', 'household_measure' => ' 1 colher '],
            'portion_authorization_id' => $authorization->id,
            'regulatory_profile_id' => $profile->id,
        ])->assertSuccessful();

        $data = $response->json('data');
        $this->assertCount(3, $data['nutrients']);
        $this->assertSame(['numerator' => '10', 'denominator' => '1'], $data['nutrients'][0]['portion']['value']);
        $this->assertSame(['numerator' => '0', 'denominator' => '1'], $data['nutrients'][1]['portion']['value']);
        $this->assertNull($data['nutrients'][2]['portion']['value']);
        $this->assertSame(['TRACE'], $data['nutrients'][2]['portion']['qualitative_states']);
        $this->assertTrue($data['nutrients'][2]['portion']['has_missing_relation']);
        $this->assertSame('UNAVAILABLE', $data['energy']['availability']);
        $this->assertSame('20', $data['portion']['value']);
        $this->assertSame('1 colher', $data['portion']['household_measure']);
        $this->assertSame('AUTHORIZED', $data['portion']['reference']['technical_status']);

        $secondId = $this->postJson("/api/nutrition-calculations/{$source->id}/serving-daily-value-calculations", [
            'portion' => ['value' => '20', 'unit' => 'g', 'household_measure' => '1 colher'],
            'portion_authorization_id' => $authorization->id,
            'regulatory_profile_id' => $profile->id,
        ])->assertSuccessful()->json('data.id');

        $this->assertNotSame($data['id'], $secondId);
        $this->assertSame('0', $zero->total_numerator);
    }

    public function test_invalid_or_divergent_portion_is_rejected_without_persistence(): void
    {
        $source = $this->sourceCalculation();
        $profile = $this->blockedProfile();
        $authorization = $this->portionAuthorization($source, '20');

        $this->postJson("/api/nutrition-calculations/{$source->id}/serving-daily-value-calculations", [
            'portion' => ['value' => '21', 'unit' => 'g', 'household_measure' => '1 colher'],
            'portion_authorization_id' => $authorization->id,
            'regulatory_profile_id' => $profile->id,
        ])->assertUnprocessable();

        $this->assertDatabaseCount('serving_daily_value_calculations', 0);
    }

    #[DataProvider('qualitativeStateProvider')]
    public function test_each_qualitative_state_is_preserved_without_becoming_zero(string $state): void
    {
        $source = $this->sourceCalculation();
        $nutrient = $source->nutrients()->create([
            'nutrient_id' => fake()->unique()->numberBetween(200000, 299999),
            'nutrient_name' => "Estado {$state}",
            'nutrient_unit' => 'mg',
            'total_numerator' => null,
            'total_denominator' => null,
            'is_complete' => false,
            'has_missing_relation' => false,
            'reference_basis' => '100',
            'reference_unit' => 'g',
            'reference_numerator' => null,
            'reference_denominator' => null,
        ]);
        $nutrient->states()->create(['state' => $state]);
        $profile = $this->blockedProfile();
        $authorization = $this->portionAuthorization($source, '20');

        $data = $this->postJson("/api/nutrition-calculations/{$source->id}/serving-daily-value-calculations", [
            'portion' => ['value' => '20', 'unit' => 'g', 'household_measure' => '1 colher'],
            'portion_authorization_id' => $authorization->id,
            'regulatory_profile_id' => $profile->id,
        ])->assertSuccessful()->json('data');

        $this->assertNull($data['nutrients'][0]['portion']['value']);
        $this->assertSame([$state], $data['nutrients'][0]['portion']['qualitative_states']);
        $this->assertFalse($data['nutrients'][0]['portion']['complete']);
    }

    /** @return array<string, array{string}> */
    public static function qualitativeStateProvider(): array
    {
        return [
            'TRACE' => ['TRACE'],
            'NA' => ['NA'],
            'ABSENT' => ['ABSENT'],
            'REEVALUATION' => ['REEVALUATION'],
        ];
    }

    public function test_exception_after_root_creation_rolls_back_the_entire_aggregate(): void
    {
        $source = $this->sourceCalculation();
        $source->nutrients()->create([
            'nutrient_id' => 300001,
            'nutrient_name' => 'Rollback',
            'nutrient_unit' => 'g',
            'total_numerator' => '10',
            'total_denominator' => '1',
            'is_complete' => true,
            'has_missing_relation' => false,
            'reference_basis' => '100',
            'reference_unit' => 'g',
            'reference_numerator' => '10',
            'reference_denominator' => '1',
        ]);
        $profile = $this->blockedProfile();
        $authorization = $this->portionAuthorization($source, '20');
        ServingDailyValueNutrient::created(static function (): never {
            throw new RuntimeException('forced-mid-persistence');
        });

        try {
            app(ServingDailyValueCalculationService::class)->create($source, [
                'portion' => ['value' => '20', 'unit' => 'g', 'household_measure' => '1 colher'],
                'portion_authorization_id' => $authorization->id,
                'regulatory_profile_id' => $profile->id,
            ]);
            $this->fail('A falha intermediária deveria interromper a persistência.');
        } catch (RuntimeException $exception) {
            $this->assertSame('forced-mid-persistence', $exception->getMessage());
        }

        $this->assertSame(0, ServingDailyValueCalculation::query()->count());
        $this->assertSame(0, ServingDailyValueNutrient::query()->count());
    }

    public function test_post_loads_origin_and_references_without_select_n_plus_one(): void
    {
        $queries = [];
        DB::listen(static function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $profile = $this->blockedProfile();
        $singleSelects = $this->countPostSelects($profile, 1, $queries);
        $manySelects = $this->countPostSelects($profile, 20, $queries);

        $this->assertSame($singleSelects, $manySelects);
    }

    /** @param list<string> $queries */
    private function countPostSelects(RegulatoryProfile $profile, int $nutrientCount, array &$queries): int
    {
        $source = $this->sourceCalculation();

        foreach (range(1, $nutrientCount) as $position) {
            $source->nutrients()->create([
                'nutrient_id' => 400000 + ($nutrientCount * 100) + $position,
                'nutrient_name' => "Nutriente {$position}",
                'nutrient_unit' => 'g',
                'total_numerator' => (string) $position,
                'total_denominator' => '1',
                'is_complete' => true,
                'has_missing_relation' => false,
                'reference_basis' => '100',
                'reference_unit' => 'g',
                'reference_numerator' => (string) $position,
                'reference_denominator' => '1',
            ]);
        }

        $authorization = $this->portionAuthorization($source, '20');
        $before = count($queries);
        app(ServingDailyValueCalculationService::class)->create($source, [
            'portion' => ['value' => '20', 'unit' => 'g', 'household_measure' => '1 colher'],
            'portion_authorization_id' => $authorization->id,
            'regulatory_profile_id' => $profile->id,
        ]);

        return collect(array_slice($queries, $before))
            ->filter(static fn (string $sql): bool => str_starts_with(strtolower(ltrim($sql)), 'select'))
            ->count();
    }

    private function sourceCalculation(): NutritionCalculation
    {
        $recipe = Recipe::factory()->create(['yield_value' => '100', 'yield_unit' => 'g']);

        return NutritionCalculation::query()->create([
            'recipe_id' => $recipe->id,
            'yield_value' => '100',
            'yield_unit' => 'g',
            'rules_version' => NutritionCalculation::RULES_VERSION,
        ]);
    }

    private function blockedProfile(): RegulatoryProfile
    {
        return RegulatoryProfile::query()->create([
            'code' => '004-TEST',
            'version' => str()->uuid()->toString(),
            'title' => 'Perfil bloqueado controlado',
            'normative_source' => 'Fonte identificada',
            'normative_version' => 'v1',
            'consulted_on' => '2026-11-11',
            'technical_status' => 'BLOCKED',
        ]);
    }

    private function portionAuthorization(NutritionCalculation $source, string $value): PortionAuthorization
    {
        return PortionAuthorization::query()->create([
            'nutrition_calculation_id' => $source->id,
            'reference_code' => 'PORTION-TEST',
            'reference_version' => 'v1',
            'reference_source' => 'Fixture controlada',
            'technical_status' => 'AUTHORIZED',
            'portion_value' => $value,
            'portion_unit' => 'g',
            'household_measure_mode' => 'PROVIDED',
            'household_measure' => '1 colher',
            'justification' => null,
            'evidence_reference' => 'EVIDENCE-PORTION',
            'evidence_source' => 'Teste automatizado',
            'evidence_version' => 'v1',
            'evidence_scope' => "nutrition-calculation:{$source->id}/portion:PORTION-TEST@v1",
            'evidence_kind' => 'TEST_FIXTURE',
            'validated_by' => 'Automated test',
            'validated_on' => '2026-11-11',
        ]);
    }
}
