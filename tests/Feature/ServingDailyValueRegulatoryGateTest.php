<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Nutrient;
use App\Models\NutritionCalculation;
use App\Models\PortionAuthorization;
use App\Models\Recipe;
use App\Models\RegulatoryDailyValue;
use App\Models\RegulatoryEnergyApplicability;
use App\Models\RegulatoryEnergyComponent;
use App\Models\RegulatoryNutrientMapping;
use App\Models\RegulatoryProfile;
use App\Services\ServingRegulatoryGateService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ServingDailyValueRegulatoryGateTest extends TestCase
{
    use DatabaseTransactions;

    public function test_test_fixture_evidence_never_authorizes_outside_testing(): void
    {
        $profile = $this->authorizedProfile();
        $gate = app(ServingRegulatoryGateService::class);

        $this->assertTrue($gate->profileAuthorized($profile));
        app()->detectEnvironment(fn (): string => 'production');

        try {
            $this->assertFalse($gate->profileAuthorized($profile));
        } finally {
            app()->detectEnvironment(fn (): string => 'testing');
        }
    }

    public function test_evidence_scope_must_match_the_expected_structure_instead_of_only_containing_it(): void
    {
        $profile = $this->authorizedProfile();
        $profile->evidence_scope = "unrelated/{$this->profileScope($profile)}/suffix";

        $this->assertFalse(app(ServingRegulatoryGateService::class)->profileAuthorized($profile));
    }

    public function test_authorized_mapping_without_quantitative_source_is_blocked(): void
    {
        [$source, $nutrientId] = $this->source();
        $profile = $this->authorizedProfile();
        $mapping = $this->mapping($profile, $nutrientId, 'AUTHORIZED');
        $mapping->source_reference = null;

        $this->assertFalse(app(ServingRegulatoryGateService::class)->mappingAuthorized($mapping));
        $this->assertNotNull($source->getKey());
    }

    public function test_mapping_rejects_incompatible_source_unit_and_evidence_from_other_version_or_formulation(): void
    {
        [, $nutrientId] = $this->source();
        $profile = $this->authorizedProfile();
        $mapping = $this->mapping($profile, $nutrientId, 'AUTHORIZED');
        $gate = app(ServingRegulatoryGateService::class);

        $mapping->source_unit = 'mg';
        $this->assertFalse($gate->mappingAuthorized($mapping));

        $mapping->source_unit = 'g';
        $mapping->evidence_version = 'other-version';
        $this->assertFalse($gate->mappingAuthorized($mapping));

        $mapping->evidence_version = $profile->version;
        $mapping->evidence_scope = "product:other/formulation:other/{$this->mappingScope($profile, $mapping->map_code, $nutrientId)}";
        $this->assertFalse($gate->mappingAuthorized($mapping));
    }

    public function test_authorized_conversion_and_vdr_require_exact_compatible_dependencies(): void
    {
        [, $nutrientId] = $this->source();
        $profile = $this->authorizedProfile();
        $mapCode = 'MAP-CONVERTED';
        $mapping = RegulatoryNutrientMapping::query()->create([
            'profile_id' => $profile->id,
            'map_code' => $mapCode,
            'component_name' => 'Componente convertido',
            'nutrient_id' => $nutrientId,
            'source_unit' => 'g',
            'regulatory_unit' => 'mg',
            'technical_status' => 'AUTHORIZED',
            'source_reference' => 'MAP fixture',
            'conversion_status' => 'AUTHORIZED',
            'conversion_numerator' => '1000',
            'conversion_denominator' => '1',
            'conversion_source_unit' => 'g',
            'conversion_target_unit' => 'mg',
            ...$this->evidence(
                $this->mappingScope($profile, $mapCode, $nutrientId),
                version: $profile->version,
            ),
            ...$this->evidence(
                $this->conversionScope($profile, $mapCode, $nutrientId, 'g', 'mg'),
                'conversion_',
                $profile->version,
            ),
        ]);
        $dailyValue = RegulatoryDailyValue::query()->create([
            'profile_id' => $profile->id,
            'vdr_code' => 'VDR-CONVERTED',
            'component_name' => 'Componente convertido',
            'mapping_id' => $mapping->id,
            'vdr_numerator' => '50000',
            'vdr_denominator' => '1',
            'unit' => 'mg',
            'technical_status' => 'AUTHORIZED',
            'source_reference' => 'VDR fixture',
            ...$this->evidence(
                "{$this->profileScope($profile)}/vdr:VDR-CONVERTED/map:{$mapCode}",
                version: $profile->version,
            ),
        ]);
        $gate = app(ServingRegulatoryGateService::class);

        $this->assertTrue($gate->mappingAuthorized($mapping));
        $this->assertTrue($gate->dailyValueAuthorized($dailyValue));

        $mapping->conversion_evidence_scope = "{$this->conversionScope($profile, $mapCode, $nutrientId, 'g', 'mg')}/other";
        $this->assertFalse($gate->mappingAuthorized($mapping));

        $mapping->conversion_evidence_scope = $this->conversionScope($profile, $mapCode, $nutrientId, 'g', 'mg');
        $dailyValue->unit = 'g';
        $this->assertFalse($gate->dailyValueAuthorized($dailyValue));
    }

    public function test_vdr_rejects_incompatible_identity_and_evidence_scope(): void
    {
        [, $nutrientId] = $this->source();
        $profile = $this->authorizedProfile();
        $mapping = $this->mapping($profile, $nutrientId, 'AUTHORIZED');
        $dailyValue = $this->dailyValue($profile, $mapping);
        $gate = app(ServingRegulatoryGateService::class);

        $this->assertTrue($gate->dailyValueAuthorized($dailyValue));

        $dailyValue->evidence_scope .= '/similar-but-wrong';
        $this->assertFalse($gate->dailyValueAuthorized($dailyValue));

        $dailyValue->evidence_scope = "{$this->profileScope($profile)}/vdr:VDR-001/map:{$mapping->map_code}";
        $dailyValue->evidence_version = 'other-version';
        $this->assertFalse($gate->dailyValueAuthorized($dailyValue));

        $dailyValue->evidence_version = $profile->version;
        $dailyValue->profile_id = $this->authorizedProfile()->id;
        $dailyValue->unsetRelation('profile');
        $this->assertFalse($gate->dailyValueAuthorized($dailyValue));
    }

    public function test_fully_authorized_fixture_calculates_map_energy_and_daily_value_exactly(): void
    {
        [$source, $nutrientId] = $this->source();
        $profile = $this->authorizedProfile();
        $mapping = $this->mapping($profile, $nutrientId, 'AUTHORIZED');
        $component = $this->energyComponent($profile, $mapping);
        $this->applicability($source, $profile, $component, 'APPLICABLE');
        $this->dailyValue($profile, $mapping);
        $authorization = $this->portion($source);

        $data = $this->postJson("/api/nutrition-calculations/{$source->id}/serving-daily-value-calculations", $this->payload($authorization, $profile))
            ->assertSuccessful()->json('data');

        $this->assertSame(['numerator' => '10', 'denominator' => '1'], $data['regulatory_results'][0]['value']);
        $this->assertSame('AVAILABLE', $data['regulatory_results'][0]['availability']);
        $this->assertSame(['numerator' => '40', 'denominator' => '1'], $data['energy']['value']);
        $this->assertTrue($data['energy']['complete']);
        $this->assertSame(['numerator' => '20', 'denominator' => '1'], $data['daily_values'][0]['value']);
        $this->assertTrue($data['daily_values'][0]['complete']);
    }

    public function test_blocked_mapping_keeps_independent_portion_value_and_blocks_energy_and_percentage(): void
    {
        [$source, $nutrientId] = $this->source();
        $profile = $this->authorizedProfile();
        $mapping = $this->mapping($profile, $nutrientId, 'BLOCKED');
        $component = RegulatoryEnergyComponent::query()->create([
            'profile_id' => $profile->id,
            'ene_code' => 'ENE-001',
            'component_name' => 'Componente bloqueado',
            'mapping_id' => $mapping->id,
            'technical_status' => 'BLOCKED',
            'overlap_rule_status' => 'NOT_REQUIRED',
        ]);
        $this->applicability($source, $profile, $component, 'APPLICABLE');
        RegulatoryDailyValue::query()->create([
            'profile_id' => $profile->id,
            'vdr_code' => 'VDR-001',
            'component_name' => 'Componente bloqueado',
            'mapping_id' => $mapping->id,
            'technical_status' => 'BLOCKED',
        ]);
        $authorization = $this->portion($source);

        $data = $this->postJson("/api/nutrition-calculations/{$source->id}/serving-daily-value-calculations", $this->payload($authorization, $profile))
            ->assertSuccessful()->json('data');

        $this->assertSame(['numerator' => '10', 'denominator' => '1'], $data['nutrients'][0]['portion']['value']);
        $this->assertNull($data['regulatory_results'][0]['value']);
        $this->assertNull($data['energy']['value']);
        $this->assertNull($data['daily_values'][0]['value']);
        $this->assertSame('UNAVAILABLE', $data['energy']['availability']);
    }

    public function test_authorized_energy_component_depending_on_blocked_mapping_blocks_the_entire_matrix(): void
    {
        [$source, $authorizedNutrientId] = $this->source();
        $blockedNutrient = Nutrient::query()->create([
            'name' => 'Componente bloqueado '.str()->uuid()->toString(),
            'unit' => 'g',
        ]);
        $source->nutrients()->create([
            'nutrient_id' => $blockedNutrient->id,
            'nutrient_name' => 'Componente bloqueado',
            'nutrient_unit' => 'g',
            'total_numerator' => '25',
            'total_denominator' => '1',
            'is_complete' => true,
            'has_missing_relation' => false,
            'reference_basis' => '100',
            'reference_unit' => 'g',
            'reference_numerator' => '25',
            'reference_denominator' => '1',
        ]);
        $profile = $this->authorizedProfile();
        $authorizedMapping = $this->mapping($profile, $authorizedNutrientId, 'AUTHORIZED', 'MAP-AUTHORIZED');
        $blockedMapping = $this->mapping($profile, (int) $blockedNutrient->id, 'BLOCKED', 'MAP-BLOCKED');
        $authorizedComponent = $this->energyComponent($profile, $authorizedMapping, 'ENE-AUTHORIZED');
        $blockedDependencyComponent = RegulatoryEnergyComponent::query()->create([
            'profile_id' => $profile->id,
            'ene_code' => 'ENE-BLOCKED-MAP',
            'component_name' => 'Componente dependente de MAP bloqueada',
            'mapping_id' => $blockedMapping->id,
            'factor_numerator' => '4',
            'factor_denominator' => '1',
            'factor_input_unit' => 'g',
            'factor_output_unit' => 'kcal',
            'technical_status' => 'AUTHORIZED',
            'source_reference' => 'ENE fixture',
            'overlap_rule_status' => 'NOT_REQUIRED',
            ...$this->evidence(
                $this->energyScope($profile, 'ENE-BLOCKED-MAP', $blockedMapping->map_code),
                version: $profile->version,
            ),
        ]);
        $this->applicability($source, $profile, $authorizedComponent, 'APPLICABLE');
        $this->applicability($source, $profile, $blockedDependencyComponent, 'APPLICABLE');
        $authorization = $this->portion($source);

        $data = $this->postJson("/api/nutrition-calculations/{$source->id}/serving-daily-value-calculations", $this->payload($authorization, $profile))
            ->assertSuccessful()->json('data');

        $this->assertSame(['numerator' => '10', 'denominator' => '1'], $data['nutrients'][0]['portion']['value']);
        $this->assertNull($data['energy']['value']);
        $this->assertSame('UNAVAILABLE', $data['energy']['availability']);
        $this->assertFalse($data['energy']['complete']);
        $this->assertSame('BLOCKED', collect($data['energy']['components'])->firstWhere('ene_code', 'ENE-BLOCKED-MAP')['participation']);
    }

    public function test_no_normative_vdr_is_not_asserted_under_blocked_profile(): void
    {
        [$source] = $this->source();
        $profile = RegulatoryProfile::query()->create([
            'code' => '004-BLOCKED-VDR',
            'version' => 'v1',
            'title' => 'Perfil bloqueado',
            'normative_source' => 'Fonte de teste',
            'normative_version' => 'v1',
            'consulted_on' => '2026-11-11',
            'technical_status' => 'BLOCKED',
        ]);
        RegulatoryDailyValue::query()->create([
            'profile_id' => $profile->id,
            'vdr_code' => 'VDR-NONE',
            'component_name' => 'Sem VDR normativo',
            'technical_status' => 'NO_NORMATIVE_VDR',
            'source_reference' => 'VDR fixture',
            ...$this->evidence(
                "{$this->profileScope($profile)}/vdr:VDR-NONE/no-normative-vdr",
                version: $profile->version,
            ),
        ]);
        $authorization = $this->portion($source);

        $data = $this->postJson("/api/nutrition-calculations/{$source->id}/serving-daily-value-calculations", $this->payload($authorization, $profile))
            ->assertSuccessful()->json('data');

        $this->assertSame('UNAVAILABLE', $data['daily_values'][0]['availability']);
        $this->assertNull($data['daily_values'][0]['value']);
        $this->assertContains('DAILY_VALUE_GATE_NOT_AUTHORIZED', array_column($data['daily_values'][0]['causes'], 'code'));
    }

    public function test_no_normative_vdr_requires_authorized_profile_and_complete_normative_provenance(): void
    {
        [$source] = $this->source();
        $profile = $this->authorizedProfile();
        $dailyValue = RegulatoryDailyValue::query()->create([
            'profile_id' => $profile->id,
            'vdr_code' => 'VDR-NORMATIVE-ABSENCE',
            'component_name' => 'Ausência normativa comprovada',
            'technical_status' => 'NO_NORMATIVE_VDR',
            'source_reference' => 'VDR fixture',
            ...$this->evidence(
                "{$this->profileScope($profile)}/vdr:VDR-NORMATIVE-ABSENCE/no-normative-vdr",
                version: $profile->version,
            ),
        ]);
        $authorization = $this->portion($source);

        $data = $this->postJson("/api/nutrition-calculations/{$source->id}/serving-daily-value-calculations", $this->payload($authorization, $profile))
            ->assertSuccessful()->json('data');
        $this->assertSame('NO_NORMATIVE_VDR', $data['daily_values'][0]['availability']);
        $this->assertNull($data['daily_values'][0]['value']);

        $dailyValue->source_reference = null;
        $this->assertFalse(app(ServingRegulatoryGateService::class)->noNormativeDailyValueAuthorized($dailyValue));
    }

    public function test_authorized_matrix_with_incomplete_nutrition_produces_only_partial_energy_and_daily_value(): void
    {
        [$source, $nutrientId] = $this->source(complete: false);
        $profile = $this->authorizedProfile();
        $mapping = $this->mapping($profile, $nutrientId, 'AUTHORIZED');
        $component = $this->energyComponent($profile, $mapping);
        $this->applicability($source, $profile, $component, 'APPLICABLE');
        $this->dailyValue($profile, $mapping);
        $authorization = $this->portion($source);

        $data = $this->postJson("/api/nutrition-calculations/{$source->id}/serving-daily-value-calculations", $this->payload($authorization, $profile))
            ->assertSuccessful()->json('data');

        $this->assertSame(['numerator' => '40', 'denominator' => '1'], $data['energy']['value']);
        $this->assertSame('PARTIAL', $data['energy']['availability']);
        $this->assertFalse($data['energy']['complete']);
        $this->assertSame(['numerator' => '20', 'denominator' => '1'], $data['daily_values'][0]['value']);
        $this->assertSame('PARTIAL', $data['daily_values'][0]['availability']);
        $this->assertFalse($data['daily_values'][0]['complete']);
    }

    public function test_authorized_matrix_with_no_numeric_source_keeps_energy_and_percentage_null(): void
    {
        [$source, $nutrientId] = $this->source(complete: false, numeric: false);
        $profile = $this->authorizedProfile();
        $mapping = $this->mapping($profile, $nutrientId, 'AUTHORIZED');
        $component = $this->energyComponent($profile, $mapping);
        $this->applicability($source, $profile, $component, 'APPLICABLE');
        $this->dailyValue($profile, $mapping);
        $authorization = $this->portion($source);

        $data = $this->postJson("/api/nutrition-calculations/{$source->id}/serving-daily-value-calculations", $this->payload($authorization, $profile))
            ->assertSuccessful()->json('data');

        $this->assertNull($data['energy']['value']);
        $this->assertFalse($data['energy']['complete']);
        $this->assertNull($data['daily_values'][0]['value']);
        $this->assertSame('UNAVAILABLE', $data['daily_values'][0]['availability']);
    }

    public function test_exact_zero_produces_complete_zero_daily_value_without_becoming_null(): void
    {
        [$source, $nutrientId] = $this->source(numerator: '0');
        $profile = $this->authorizedProfile();
        $mapping = $this->mapping($profile, $nutrientId, 'AUTHORIZED');
        $this->dailyValue($profile, $mapping);
        $authorization = $this->portion($source);

        $data = $this->postJson("/api/nutrition-calculations/{$source->id}/serving-daily-value-calculations", $this->payload($authorization, $profile))
            ->assertSuccessful()->json('data');

        $this->assertSame(['numerator' => '0', 'denominator' => '1'], $data['daily_values'][0]['value']);
        $this->assertSame('AVAILABLE', $data['daily_values'][0]['availability']);
        $this->assertTrue($data['daily_values'][0]['complete']);
    }

    public function test_not_applicable_requires_evidence_and_is_not_zero_or_missing_data(): void
    {
        [$source, $nutrientId] = $this->source();
        $profile = $this->authorizedProfile();
        $mapping = $this->mapping($profile, $nutrientId, 'AUTHORIZED');
        $component = $this->energyComponent($profile, $mapping);
        $this->applicability($source, $profile, $component, 'NOT_APPLICABLE');
        $authorization = $this->portion($source);

        $data = $this->postJson("/api/nutrition-calculations/{$source->id}/serving-daily-value-calculations", $this->payload($authorization, $profile))
            ->assertSuccessful()->json('data');

        $this->assertSame('NON_APPLICABLE', $data['energy']['components'][0]['participation']);
        $this->assertNull($data['energy']['components'][0]['contribution']);
        $this->assertNotEmpty($data['energy']['components'][0]['non_applicability_justification']);
    }

    /** @return array{NutritionCalculation, int} */
    private function source(bool $complete = true, bool $numeric = true, string $numerator = '50'): array
    {
        $recipe = Recipe::factory()->create(['yield_value' => '100', 'yield_unit' => 'g']);
        $source = NutritionCalculation::query()->create([
            'recipe_id' => $recipe->id,
            'yield_value' => '100',
            'yield_unit' => 'g',
            'rules_version' => NutritionCalculation::RULES_VERSION,
        ]);
        $nutrientId = (int) Nutrient::query()->create([
            'name' => 'Componente controlado '.str()->uuid()->toString(),
            'unit' => 'g',
        ])->getKey();
        $nutrient = $source->nutrients()->create([
            'nutrient_id' => $nutrientId,
            'nutrient_name' => 'Componente controlado',
            'nutrient_unit' => 'g',
            'total_numerator' => $numeric ? $numerator : null,
            'total_denominator' => $numeric ? '1' : null,
            'is_complete' => $complete,
            'has_missing_relation' => ! $complete,
            'reference_basis' => '100',
            'reference_unit' => 'g',
            'reference_numerator' => $numeric ? $numerator : null,
            'reference_denominator' => $numeric ? '1' : null,
        ]);

        if (! $complete) {
            $nutrient->states()->create(['state' => 'TRACE']);
        }

        return [$source, $nutrientId];
    }

    private function authorizedProfile(): RegulatoryProfile
    {
        $version = str()->uuid()->toString();

        return RegulatoryProfile::query()->create([
            'code' => '004-FIXTURE', 'version' => $version, 'title' => 'Fixture regulatória',
            'normative_source' => 'Fonte de teste', 'normative_version' => 'v1', 'consulted_on' => '2026-11-11',
            'technical_status' => 'AUTHORIZED',
            ...$this->evidence("profile:004-FIXTURE@{$version}", version: $version),
        ]);
    }

    private function mapping(
        RegulatoryProfile $profile,
        int $nutrientId,
        string $status,
        string $mapCode = 'MAP-001',
    ): RegulatoryNutrientMapping {
        $data = [
            'profile_id' => $profile->id, 'map_code' => $mapCode, 'component_name' => 'Componente',
            'nutrient_id' => $nutrientId, 'source_unit' => 'g', 'regulatory_unit' => 'g',
            'technical_status' => $status, 'source_reference' => 'MAP fixture',
            'conversion_status' => $status === 'AUTHORIZED' ? 'NOT_REQUIRED' : 'BLOCKED',
        ];

        if ($status === 'AUTHORIZED') {
            $data = [
                ...$data,
                ...$this->evidence(
                    $this->mappingScope($profile, $mapCode, $nutrientId),
                    version: $profile->version,
                ),
                ...$this->evidence(
                    $this->conversionScope($profile, $mapCode, $nutrientId, 'g', 'g'),
                    'conversion_',
                    $profile->version,
                ),
            ];
        }

        return RegulatoryNutrientMapping::query()->create($data);
    }

    private function energyComponent(
        RegulatoryProfile $profile,
        RegulatoryNutrientMapping $mapping,
        string $eneCode = 'ENE-001',
    ): RegulatoryEnergyComponent {
        return RegulatoryEnergyComponent::query()->create([
            'profile_id' => $profile->id, 'ene_code' => $eneCode, 'component_name' => 'Componente',
            'mapping_id' => $mapping->id, 'factor_numerator' => '4', 'factor_denominator' => '1',
            'factor_input_unit' => 'g', 'factor_output_unit' => 'kcal', 'technical_status' => 'AUTHORIZED',
            'source_reference' => 'ENE fixture', 'overlap_rule_status' => 'NOT_REQUIRED',
            ...$this->evidence(
                $this->energyScope($profile, $eneCode, $mapping->map_code),
                version: $profile->version,
            ),
        ]);
    }

    private function dailyValue(RegulatoryProfile $profile, RegulatoryNutrientMapping $mapping): RegulatoryDailyValue
    {
        return RegulatoryDailyValue::query()->create([
            'profile_id' => $profile->id, 'vdr_code' => 'VDR-001', 'component_name' => 'Componente',
            'mapping_id' => $mapping->id, 'vdr_numerator' => '50', 'vdr_denominator' => '1',
            'unit' => 'g', 'technical_status' => 'AUTHORIZED', 'source_reference' => 'VDR fixture',
            ...$this->evidence(
                "{$this->profileScope($profile)}/vdr:VDR-001/map:{$mapping->map_code}",
                version: $profile->version,
            ),
        ]);
    }

    private function applicability(NutritionCalculation $source, RegulatoryProfile $profile, RegulatoryEnergyComponent $component, string $status): void
    {
        RegulatoryEnergyApplicability::query()->create([
            'nutrition_calculation_id' => $source->id, 'profile_id' => $profile->id,
            'energy_component_id' => $component->id, 'status' => $status,
            'justification' => $status === 'NOT_APPLICABLE' ? 'Componente comprovadamente inaplicável à formulação.' : null,
            'source_reference' => 'Aplicabilidade fixture',
            ...$this->evidence(
                "nutrition-calculation:{$source->id}/{$this->energyScope($profile, $component->ene_code, $component->mapping->map_code)}",
                version: $profile->version,
            ),
        ]);
    }

    private function portion(NutritionCalculation $source): PortionAuthorization
    {
        return PortionAuthorization::query()->create([
            'nutrition_calculation_id' => $source->id, 'reference_code' => 'PORTION', 'reference_version' => 'v1',
            'reference_source' => 'Fixture', 'technical_status' => 'AUTHORIZED', 'portion_value' => '20', 'portion_unit' => 'g',
            'household_measure_mode' => 'WAIVED', 'household_measure' => null, 'justification' => 'Dispensa controlada.',
            ...$this->evidence("nutrition-calculation:{$source->id}/portion:PORTION@v1"),
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(PortionAuthorization $authorization, RegulatoryProfile $profile): array
    {
        return ['portion' => ['value' => '20', 'unit' => 'g', 'household_measure' => null], 'portion_authorization_id' => $authorization->id, 'regulatory_profile_id' => $profile->id];
    }

    /** @return array<string, string> */
    private function evidence(string $scope, string $prefix = '', string $version = 'v1'): array
    {
        return [
            $prefix.'evidence_reference' => 'EVIDENCE-'.$scope,
            $prefix.'evidence_source' => 'Automated test',
            $prefix.'evidence_version' => $version,
            $prefix.'evidence_scope' => $scope,
            $prefix.'evidence_kind' => 'TEST_FIXTURE',
            $prefix.'validated_by' => 'Automated test',
            $prefix.'validated_on' => '2026-11-11',
        ];
    }

    private function profileScope(RegulatoryProfile $profile): string
    {
        return "profile:{$profile->code}@{$profile->version}";
    }

    private function mappingScope(RegulatoryProfile $profile, string $mapCode, int $nutrientId): string
    {
        return "{$this->profileScope($profile)}/map:{$mapCode}/nutrient:{$nutrientId}";
    }

    private function conversionScope(
        RegulatoryProfile $profile,
        string $mapCode,
        int $nutrientId,
        string $sourceUnit,
        string $regulatoryUnit,
    ): string {
        return "{$this->mappingScope($profile, $mapCode, $nutrientId)}/conversion:{$sourceUnit}->{$regulatoryUnit}";
    }

    private function energyScope(RegulatoryProfile $profile, string $eneCode, string $mapCode): string
    {
        return "{$this->profileScope($profile)}/ene:{$eneCode}/map:{$mapCode}";
    }
}
