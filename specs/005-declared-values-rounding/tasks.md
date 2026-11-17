---

description: "Task list for Feature 005 — Declared Values, Rounding, and Non-Significant Quantities"
---

# Tasks: Declared Values, Rounding, and Non-Significant Quantities (005-declared-values-rounding)

**Input**: Design documents from `specs/005-declared-values-rounding/`

**Prerequisites**: `plan.md`, `spec.md`, `research.md`, `data-model.md`, `contracts/declared-values-rounding.md`, `quickstart.md`

**Tests**: obrigatórios. A Constitution 2.0.0 (seção "Banco de Dados e Testes" e "Autoridade Administrativa e Integridade Técnica") exige que testes automatizados comprovem os caminhos autorizados e os bloqueios seguros. Cada User Story começa pelos testes, que devem falhar antes da implementação.

**Organization**: as tasks seguem as User Stories da `spec.md` e mantêm todas as regras de NSQ, arredondamento, recálculo e obrigatoriedade em modo *deny-by-default* (`TEST_FIXTURE` restrito ao ambiente `testing`), sem alterar dados ou schemas das Features 001–004.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode rodar em paralelo (arquivo distinto, sem dependência de task incompleta da mesma fase)
- **[US1]–[US3]**: vincula a task à User Story correspondente
- Cada task cita o arquivo exato e os requisitos (`FR-*` / `SC-*`) que cobre

## Path Conventions

Monólito Laravel na raiz do repositório: `app/Http/{Controllers,Requests,Resources}`, `app/Models`, `app/Services`, `database/migrations`, `routes/api.php`, `tests/{Unit,Feature}`.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: confirmar a base técnica sem alterar stack, dependências ou credenciais.

- [X] T001 Confirmar em `composer.json`, `composer.lock` e `phpunit.xml` que o projeto usa Laravel 13.34.0, `brick/math` 1.0.0 como dependência direta e PostgreSQL nos testes, executando `composer show --direct` e `php artisan test --compact` como baseline, sem atualizar dependências nem versionar credenciais (Constitution II; SC-005)
- [X] T002 Inspecionar `app/Services/ServingDailyValueCalculationService.php`, `app/Services/ServingRegulatoryGateService.php`, `app/Http/Controllers/ServingDailyValueCalculationController.php`, `app/Http/Resources/ServingDailyValueCalculationResource.php`, `database/migrations/2026_11_10_000014_protect_serving_daily_value_data_from_mutation.php` e `routes/api.php` e registrar os padrões a reutilizar: transação `REPEATABLE READ`, `hasEvidence()`, `evidenceSnapshot()`, `cause()`, razões como strings via `getRawOriginal()` e serialização ordenada (FR-001, FR-002)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: schema PostgreSQL, Models, imutabilidade e helpers aritméticos compartilhados por todas as User Stories.

**⚠️ CRITICAL**: nenhuma User Story começa antes de T026 passar integralmente.

### Structural and arithmetic tests

- [X] T003 Criar `tests/Feature/DeclaredNutritionPersistenceTest.php` (via `php artisan make:test --phpunit DeclaredNutritionPersistenceTest`) com testes inicialmente falhos para as 12 tabelas novas: FKs com `restrictOnDelete`, unicidades, enums de `technical_status`/`target_kind`/`basis`/`availability`, pares racionais all-or-none com "numerador/denominador inteiros, denominador `> 0`, `scale(...) = 0`, zero canônico `0/1`, sem `Infinity`/`NaN`", tuplas `evidence_*` "integralmente nulas ou integralmente preenchidas" e obrigatórias quando `AUTHORIZED`, e bloqueio de `UPDATE`/`DELETE` por trigger em todas as tabelas (FR-003, FR-012, FR-013; SC-004)
- [X] T004 [P] Criar `tests/Unit/DeclaredValuesArithmeticTest.php` (via `php artisan make:test --phpunit --unit DeclaredValuesArithmeticTest`) com testes inicialmente falhos para os novos helpers de `NutritionCalculationArithmetic`: comparação exata de `BigRational`, `roundToStepHalfUp()` com empates exatos (ex.: `25/10` em step `1` → `3`; `125/100` em step `1/10` → `13/10`), dízimas (`1/3`), zero, numeradores maiores que `PHP_INT_MAX`, e `formatDecimal()` com `RoundingMode::Unnecessary` lançando exceção quando o step não é representável em `decimal_places` (FR-003; SC-001)

### Regulatory reference migrations (versionadas, deny-by-default)

- [X] T005 Criar `database/migrations/2026_11_12_000001_create_regulatory_declaration_profiles_table.php` com `code`, `version`, `title`, `regulatory_profile_code`, `regulatory_profile_version`, `normative_source`, `normative_version`, `consulted_on`, `scope_recipe_id` nullable, `scope_nutrition_calculation_id` nullable, `technical_status IN ('AUTHORIZED','BLOCKED')`, tupla `evidence_*` + `validated_by`/`validated_on` com checks all-or-none e obrigatória quando `AUTHORIZED`, `UNIQUE (code, version)` e somente `created_at` (FR-001, FR-004, FR-010)
- [X] T006 Criar `database/migrations/2026_11_12_000002_create_regulatory_recalculation_policies_table.php` sem timestamps, com FK `declaration_profile_id`, `policy_code`, `normative_order_mode IN ('DECLARE_COMPONENTS_THEN_ENERGY_AND_VD')`, `energy_reference_100_input_source`, `energy_portion_input_source`, `daily_value_input_source`, `requires_complete_energy_inputs` boolean, `technical_status IN ('AUTHORIZED','BLOCKED','UNAVAILABLE')`, `source_reference` nullable, tupla `evidence_*`, `UNIQUE (declaration_profile_id)` e check "quando `AUTHORIZED`, exige `requires_complete_energy_inputs = true`, `source_reference` não vazio e tupla de evidência completa" (FR-005, FR-008)
- [X] T007 [P] Criar `database/migrations/2026_11_12_000003_create_regulatory_non_significant_rules_table.php` sem timestamps, com `rule_code`, `target_kind IN ('COMPONENT','ENERGY')`, `map_code` "obrigatório se `COMPONENT`; nulo se `ENERGY`", `basis IN ('REFERENCE_100','PORTION')`, `technical_status IN ('AUTHORIZED','BLOCKED','NOT_APPLICABLE','UNAVAILABLE')`, `comparison_operator IN ('LTE','LT')`, par `threshold_*` `NUMERIC`, `threshold_unit`, `declared_string`, par `declared_*`, `justification`, `source_reference`, tupla `evidence_*`; `UNIQUE (declaration_profile_id, rule_code)`, índices únicos parciais por `(declaration_profile_id, map_code, basis)` em `COMPONENT` e `(declaration_profile_id, basis)` em `ENERGY`; `AUTHORIZED` exige operador, threshold, unidade, declaração, fonte e evidência; `NOT_APPLICABLE` exige `justification` e evidência (FR-004, FR-009)
- [X] T008 [P] Criar `database/migrations/2026_11_12_000004_create_regulatory_rounding_rules_table.php` sem timestamps, com `rule_code`, `target_kind IN ('COMPONENT','ENERGY','DAILY_VALUE')`, `map_code`, `vdr_code`, `vdr_numerator_source IN ('DECLARED_COMPONENT','DECLARED_PORTION_ENERGY')` nullable, `basis IN ('REFERENCE_100','PORTION','PERCENT_VD')` compatível com `target_kind`, `unit`, `tie_breaker IN ('HALF_UP')`, `technical_status IN ('AUTHORIZED','BLOCKED','UNAVAILABLE')`, `source_reference`, tupla `evidence_*`; `UNIQUE (declaration_profile_id, rule_code)` e índices únicos parciais por alvo/base conforme `data-model.md` (FR-003, FR-004)
- [X] T009 Criar `database/migrations/2026_11_12_000005_create_regulatory_rounding_rule_ranges_table.php` sem timestamps, com FK `rounding_rule_id`, `range_order >= 1`, pares `min_*`/`max_*` nullable com `min_inclusive`/`max_inclusive`, par `step_*` obrigatório com `step_numerator > 0`, `decimal_places >= 0` e `UNIQUE (rounding_rule_id, range_order)` (depende de T008) (FR-003, FR-004)
- [X] T010 [P] Criar `database/migrations/2026_11_12_000006_create_regulatory_declaration_requirements_table.php` sem timestamps, com `requirement_code`, `target_kind IN ('COMPONENT','ENERGY','DAILY_VALUE')`, `map_code`, `vdr_code`, `requirement_level IN ('MANDATORY','CONDITIONAL','VOLUNTARY')`, `applicability_status IN ('APPLICABLE','NOT_APPLICABLE','UNKNOWN')`, `justification` "obrigatória se `NOT_APPLICABLE`", `technical_status IN ('AUTHORIZED','BLOCKED','UNAVAILABLE')`, `source_reference`, tupla `evidence_*` e `UNIQUE (declaration_profile_id, requirement_code)` (FR-011)

### Historical snapshot migrations

- [X] T011 Criar `database/migrations/2026_11_12_000007_create_declared_nutrition_calculations_table.php` com FKs `serving_daily_value_calculation_id` e `source_nutrition_calculation_id` (`restrictOnDelete`, sem `unique`), cópias de origem (recipe, versões 003/004, rendimento, porção, medida caseira, autorização e evidência da porção, perfil 004), snapshot completo do `declaration_profile_*` e `recalculation_policy_*`, energia derivada 004 (`source_portion_energy_*`), blocos `reference_100_*` e `portion_*` de energia (exata recalculada, `nsq_applied`, `declared_energy_string`, par `declared_energy_*`, `energy_unit`, `energy_is_complete`, `energy_availability IN ('AVAILABLE','UNAVAILABLE')`, snapshots de regras NSQ/arredondamento e evidências), `is_label_ready` boolean, `rules_version` default `'declared-values-rounding-v1'` e somente `created_at` (FR-003, FR-006, FR-008, FR-011, FR-012)
- [X] T012 Criar `database/migrations/2026_11_12_000008_create_declared_nutrition_components_table.php` sem timestamps, com FK raiz e FK opcional `serving_daily_value_regulatory_result_id`, metadados MAP/conversão/evidências, `source_is_complete`, `source_has_missing_relation` e campos simétricos `reference_100_*` e `portion_*` (`source_*`, `regulatory_*`, `is_exact_zero`, `nsq_rule_code`, `nsq_rule_status`, `nsq_applied`, `nsq_evidence_*`, `rounding_rule_code`, `rounding_rule_status`, `rounding_tie_breaker`, `rounding_step_*`, `rounding_decimal_places`, `rounding_evidence_*`, `declared_string`, `declared_*`, `is_complete`, `availability IN ('AVAILABLE','UNAVAILABLE')`) e `UNIQUE (declared_nutrition_calculation_id, map_code)` (FR-003, FR-006, FR-009)
- [X] T013 [P] Criar `database/migrations/2026_11_12_000009_create_declared_nutrition_component_states_table.php` sem timestamps, com `state IN ('TRACE','NA','ABSENT','REEVALUATION')` e `UNIQUE (declared_nutrition_component_id, state)` (FR-007)
- [X] T014 [P] Criar `database/migrations/2026_11_12_000010_create_declared_nutrition_energy_components_table.php` sem timestamps, com `basis IN ('REFERENCE_100','PORTION')`, `ene_code`, `map_code`, `applicability_status`, `participation IN ('INCLUDED','NON_APPLICABLE','BLOCKED')`, par `factor_*`, unidades, `input_declared_string`, par `input_declared_*`, par `contribution_*`, snapshots ENE/aplicabilidade/sobreposição/evidências e `UNIQUE (declared_nutrition_calculation_id, basis, ene_code)` (FR-005, FR-008)
- [X] T015 [P] Criar `database/migrations/2026_11_12_000011_create_declared_nutrition_percentages_table.php` sem timestamps, com FK opcional `serving_daily_value_percentage_id`, snapshot VDR (par, unidade, fonte, versão, status, evidência), `source_derived_percentage_*` e `source_derived_availability`, `numerator_identity`, `input_declared_*`, `unrounded_percentage_*`, snapshot da regra de arredondamento, `declared_percentage_string`, par `declared_percentage_*`, `is_complete`, `availability IN ('AVAILABLE','UNAVAILABLE','NO_NORMATIVE_VDR')` e `UNIQUE (declared_nutrition_calculation_id, vdr_code)` (FR-003, FR-005, FR-009)
- [X] T016 [P] Criar `database/migrations/2026_11_12_000012_create_declared_nutrition_causes_table.php` sem timestamps, com `scope IN ('COMPONENT_REFERENCE_100','COMPONENT_PORTION','ENERGY_REFERENCE_100','ENERGY_PORTION','DAILY_VALUE','LABEL_READINESS')`, `scope_key`, `category` (lista de `data-model.md`), `code`, `detail`, `source_reference` e `UNIQUE (declared_nutrition_calculation_id, scope, scope_key, code)` (FR-006, FR-011)
- [X] T017 Criar `database/migrations/2026_11_12_000013_protect_declared_nutrition_data_from_mutation.php` com a função `prevent_declared_nutrition_mutation()` e triggers reversíveis `BEFORE UPDATE OR DELETE` nas 12 tabelas de T005–T016, sem tocar tabelas das Features 001–004 (FR-002, FR-012, FR-013; SC-004, SC-005)

### Models

- [X] T018 [P] Criar `app/Models/RegulatoryDeclarationProfile.php` (`public const UPDATED_AT = null;`) com relações `recalculationPolicy()`, `nonSignificantRules()`, `roundingRules()`, `requirements()`, e `app/Models/RegulatoryRecalculationPolicy.php` (`public $timestamps = false;`), sem lógica de gate (FR-004)
- [X] T019 [P] Criar `app/Models/RegulatoryNonSignificantRule.php`, `app/Models/RegulatoryRoundingRule.php` (relação `ranges()` ordenada por `range_order`) e `app/Models/RegulatoryRoundingRuleRange.php`, todos com `public $timestamps = false;` e razões lidas como strings (FR-003, FR-004)
- [X] T020 [P] Criar `app/Models/RegulatoryDeclarationRequirement.php` com `public $timestamps = false;` (FR-011)
- [X] T021 Criar `app/Models/DeclaredNutritionCalculation.php` com `public const UPDATED_AT = null;`, `RULES_VERSION = 'declared-values-rounding-v1'`, `$guarded = []` e relações `components()`, `energyComponents()`, `percentages()`, `causes()`, `servingDailyValueCalculation()` (FR-012)
- [X] T022 [P] Criar `app/Models/DeclaredNutritionComponent.php` (relação `states()`) e `app/Models/DeclaredNutritionComponentState.php`, ambos com `public $timestamps = false;` (FR-003, FR-007)
- [X] T023 [P] Criar `app/Models/DeclaredNutritionEnergyComponent.php`, `app/Models/DeclaredNutritionPercentage.php` e `app/Models/DeclaredNutritionCause.php`, todos com `public $timestamps = false;` (FR-005, FR-009)

### Shared services

- [X] T024 Estender `app/Services/NutritionCalculationArithmetic.php` apenas com helpers genéricos: `compare(BigRational, BigRational): int`, `roundToStepHalfUp(BigRational $value, BigRational $step): BigRational` (via `dividedBy($step)->toScale(0, RoundingMode::HalfUp)` × step) e `formatDecimal(BigRational $value, int $decimalPlaces): string` (via `toScale($decimalPlaces, RoundingMode::Unnecessary)`), sem regras regulatórias e sem alterar métodos existentes (FR-003; Constitution IV)
- [X] T025 Criar `app/Services/DeclaredValuesRegulatoryGateService.php` (via `php artisan make:class`) com `assertProfileMatchesSource(RegulatoryDeclarationProfile, ServingDailyValueCalculation): void` lançando `ValidationException` em divergência de `regulatory_profile_code`, `regulatory_profile_version`, `normative_source`, `normative_version`, `scope_recipe_id` ou `scope_nutrition_calculation_id`, e `profileAuthorized()` reutilizando `ServingRegulatoryGateService::hasEvidence()` com escopo `declaration-profile:{code}@{version}` (FR-001, FR-004, FR-010)

### Foundation checkpoint

- [X] T026 Executar `php artisan migrate`, `php artisan migrate:status`, `php artisan test --compact tests/Feature/DeclaredNutritionPersistenceTest.php` e `php artisan test --compact tests/Unit/DeclaredValuesArithmeticTest.php` e confirmar que T003–T025 passam e que a suíte das Features 001–004 continua verde

**Checkpoint**: fundação pronta — User Stories podem começar.

---

## Phase 3: User Story 1 — Valores declarados em 100 g/100 ml e porção com NSQ e arredondamento (Priority: P1) 🎯 MVP

**Goal**: a partir de uma execução imutável da Feature 004, criar a execução declarada e determinar, por componente `MAP` e por base, o valor declarado (NSQ ou arredondamento autorizado), preservando valores exatos de origem e nunca convertendo estados qualitativos, ausência de relação, parciais ou bloqueios em zero.

**Independent Test**: com fixtures `TEST_FIXTURE` de perfil 004 e de `RegulatoryDeclarationProfile` compatível com regras NSQ/arredondamento autorizadas, o `POST` cria nova execução; cada componente completo recebe `declared.display` + `declared.ratio` corretos em ambas as bases; componentes em `TRACE`/`NA`/`ABSENT`/`REEVALUATION`, parciais ou bloqueados ficam `null` com causa.

### Tests for User Story 1 ⚠️

> Escrever primeiro e garantir que falham antes da implementação.

- [X] T027 [P] [US1] Criar `tests/Feature/DeclaredNutritionRegulatoryGateTest.php` cobrindo: divergência de perfil/versão/fonte normativa/escopo rejeita com `422` sem persistir; `TEST_FIXTURE` nunca autoriza fora de `testing`; escopo de evidência deve ser exatamente igual; regra NSQ/arredondamento `AUTHORIZED` sem evidência é bloqueada; regra de arredondamento autorizada sem faixas, com faixas sobrepostas ou sem faixa aplicável ao valor é bloqueada com causa `ROUNDING_RANGE_MISMATCH` (FR-001, FR-004, FR-010)
- [X] T028 [P] [US1] Criar `tests/Feature/DeclaredNutritionCalculationTest.php` com cenários de US1: arredondamento `HALF_UP` correto em `REFERENCE_100` e `PORTION`; NSQ aplicada só na base que satisfaz o limite (sem contaminação entre bases); zero exato `0/1` (`is_exact_zero = true`, `nsq_applied = false`) distinto de zero por NSQ (`is_exact_zero = false`, `nsq_applied = true`); `TRACE`/`NA`/`ABSENT`/`REEVALUATION`, `has_missing_relation`, `is_complete = false` e MAP bloqueada resultam em `declared = null` com causa e sem NSQ; valores exatos da 003/004 preservados; nenhuma linha das Features 001–004 alterada (FR-003, FR-006–FR-009; SC-001, SC-002, SC-005)

### Implementation for User Story 1

- [X] T029 [US1] Adicionar a `app/Services/DeclaredValuesRegulatoryGateService.php` os métodos `nonSignificantRuleAuthorized()`, `roundingRuleAuthorized()` (exige ≥ 1 faixa, sem sobreposição) e `resolveRange(RegulatoryRoundingRule, BigRational): ?RegulatoryRoundingRuleRange` (exatamente uma faixa aplicável), com escopos `{declaration-profile}/nsq:{rule_code}` e `{declaration-profile}/rounding:{rule_code}` (FR-004)
- [X] T030 [US1] Criar `app/Services/DeclaredNutritionCalculationService.php` (via `php artisan make:class`) com `create(ServingDailyValueCalculation, array): DeclaredNutritionCalculation` em `DB::transaction` + `REPEATABLE READ`, carregando com eager loading a execução 004 (`nutrients.states`, `regulatoryResults`, `energyComponents`, `percentages`), `NutritionCalculationNutrient` de origem (valores `reference_*`) e o `RegulatoryDeclarationProfile` com regras; chamando `assertProfileMatchesSource()` antes de qualquer insert (FR-001, FR-002, FR-012)
- [X] T031 [US1] Implementar em `app/Services/DeclaredNutritionCalculationService.php` o método `componentRows()`: para cada `ServingDailyValueRegulatoryResult`, calcular `regulatory_*` em `REFERENCE_100` (origem 003 × conversão MAP snapshotada) e `PORTION` (valor regulatório 004), aplicar bloqueios (MAP/conversão, estados qualitativos, ausência de relação, `is_complete = false`, sem número) → `null` + causa; senão NSQ autorizada (`LTE`/`LT`) → `declared_string`/`declared_*` da regra; senão arredondamento via `roundToStepHalfUp()` + `formatDecimal()` com a faixa resolvida; gravar `is_exact_zero` e snapshots de regras/evidências (FR-003, FR-004, FR-006–FR-009)
- [X] T032 [US1] Persistir em `app/Services/DeclaredNutritionCalculationService.php` a raiz `DeclaredNutritionCalculation` (cópias de origem e snapshot de perfil/política), `DeclaredNutritionComponent`, `DeclaredNutritionComponentState` e `DeclaredNutritionCause` nos escopos `COMPONENT_REFERENCE_100`/`COMPONENT_PORTION`, e implementar `retrieve()` com eager loading ordenado por `map_code` e `(scope, scope_key, code)` (FR-006, FR-012)
- [X] T033 [P] [US1] Criar `app/Http/Requests/StoreDeclaredNutritionCalculationRequest.php` (via `php artisan make:request`) validando `regulatory_declaration_profile_id` como `required|integer|min:1|exists:regulatory_declaration_profiles,id` e nada mais (contrato: cliente não envia regras, status ou evidências)
- [X] T034 [P] [US1] Criar `app/Http/Resources/DeclaredNutritionCalculationResource.php` com o bloco raiz, `declaration_profile`, `recalculation_policy` e `components[]` (`reference_100` / `portion` com `source_exact`, `regulatory_exact`, `is_exact_zero`, `nsq_applied`, `nsq_rule`, `rounding_rule`, `declared: {display, ratio}` ou `null`, `complete`, `availability`, `causes`), `qualitative_states` na ordem `['TRACE','NA','ABSENT','REEVALUATION']` e razões como strings, conforme `contracts/declared-values-rounding.md`
- [X] T035 [US1] Criar `app/Http/Controllers/DeclaredNutritionCalculationController.php` (via `php artisan make:controller`) com `store(ServingDailyValueCalculation, StoreDeclaredNutritionCalculationRequest, DeclaredNutritionCalculationService)` retornando o Resource com `201`, e `show(DeclaredNutritionCalculation, DeclaredNutritionCalculationService)` via `retrieve()`
- [X] T036 [US1] Registrar em `routes/api.php` `POST /serving-daily-value-calculations/{servingDailyValueCalculation}/declared-nutrition-calculations` e `GET /declared-nutrition-calculations/{declaredNutritionCalculation}` apontando para `DeclaredNutritionCalculationController`, sem alterar rotas existentes
- [X] T037 [US1] Executar `php artisan test --compact tests/Feature/DeclaredNutritionRegulatoryGateTest.php tests/Feature/DeclaredNutritionCalculationTest.php` e confirmar T027–T028 verdes

**Checkpoint**: US1 funcional e testável isoladamente (MVP).

---

## Phase 4: User Story 2 — Recalcular energia e `%VD` regulatórios finais na ordem normativa (Priority: P2)

**Goal**: recalcular a energia regulatória final em `100 g/100 ml` e na porção e o `%VD` final a partir dos valores **declarados**, mantendo lado a lado os derivados exatos da Feature 004, sem energia/`%VD` parciais.

**Independent Test**: fixture em que a energia calculada sobre os valores declarados difere do arredondamento direto da energia exata 004; verificar `energy.source_portion_derived_exact` preservado, `energy.reference_100`/`energy.portion` recalculados e `daily_values[*].declared_percentage` = `round(input_declared × 100 ÷ vdr)`; qualquer componente energético aplicável `null`/parcial → energia `null`.

### Tests for User Story 2 ⚠️

- [X] T038 [P] [US2] Adicionar a `tests/Feature/DeclaredNutritionCalculationTest.php` cenários de US2: energia recalculada sobre declarados ≠ arredondamento do derivado 004 (ambos preservados); componente zerado por NSQ contribui com `0`; NSQ de energia por base; componente energético aplicável `null`/parcial/bloqueado → energia da base `null`, `availability = 'UNAVAILABLE'`, sem subtotal; matriz ENE com `UNKNOWN`/`BLOCKED`/sobreposição sem regra → energia `null`; política de recálculo bloqueada ou `requires_complete_energy_inputs = false` → energia e `%VD` `null` com causa `POLICY_BLOCK` (FR-005, FR-008; SC-001, SC-002)
- [X] T039 [P] [US2] Adicionar a `tests/Feature/DeclaredNutritionCalculationTest.php` cenários de `%VD`: `%VD` final usa o valor declarado na porção (não o exato 004); `%VD` de energia usa a energia final declarada na porção; `NO_NORMATIVE_VDR` preserva `availability = 'NO_NORMATIVE_VDR'` e `declared_percentage = null`; VDR/regra de arredondamento bloqueados → `null` com causa, nunca substituído pelo derivado 004 (FR-005, FR-009; SC-001)

### Implementation for User Story 2

- [X] T040 [US2] Adicionar a `app/Services/DeclaredValuesRegulatoryGateService.php` `recalculationPolicyAuthorized()` (exige `AUTHORIZED`, `requires_complete_energy_inputs = true`, `source_reference` e evidência no escopo `{declaration-profile}/policy:{policy_code}`) e `energyMatrixAuthorized()` sobre os snapshots `ServingDailyValueEnergyComponent` (nenhum `UNKNOWN`, nenhum `BLOCKED`, sobreposição só com regra `AUTHORIZED`) (FR-005, FR-008)
- [X] T041 [US2] Implementar em `app/Services/DeclaredNutritionCalculationService.php` `energyRows()` para cada base: exigir política e matriz autorizadas; para cada ENE `APPLICABLE` exigir valor declarado completo do MAP correspondente; somar `input_declared × factor` em `BigRational`; aplicar NSQ de energia e arredondamento autorizado para `(ENERGY, basis)`; caso contrário manter `null` com causas `ENERGY_REFERENCE_100`/`ENERGY_PORTION`; persistir `DeclaredNutritionEnergyComponent` e os campos de energia da raiz (FR-005, FR-008)
- [X] T042 [US2] Implementar em `app/Services/DeclaredNutritionCalculationService.php` `percentageRows()`: copiar `source_derived_*` do `ServingDailyValuePercentage`; tratar `NO_NORMATIVE_VDR`; selecionar entrada declarada conforme `vdr_numerator_source` (`DECLARED_COMPONENT` → componente na porção; `DECLARED_PORTION_ENERGY` → energia final na porção), exigindo completude e unidade compatível; calcular `input × 100 ÷ vdr` e arredondar pela regra `(DAILY_VALUE, PERCENT_VD, vdr_code)`; persistir `DeclaredNutritionPercentage` e causas `DAILY_VALUE` (FR-005, FR-009)
- [X] T043 [US2] Estender `app/Http/Resources/DeclaredNutritionCalculationResource.php` com `energy` (`source_portion_derived_exact`, `reference_100`, `portion` com `recalculated_exact`, `nsq_applied`, `declared`, `unit`, `complete`, `availability`, `causes`, `components` ordenados por `ene_code`) e `daily_values[]` ordenados por `vdr_code`, conforme o contrato
- [X] T044 [US2] Executar `php artisan test --compact tests/Feature/DeclaredNutritionCalculationTest.php` e confirmar T038–T039 verdes e T028 sem regressão

**Checkpoint**: US1 e US2 funcionam independentemente.

---

## Phase 5: User Story 3 — Aptidão à rotulagem e snapshot histórico imutável (Priority: P3)

**Goal**: avaliar `is_label_ready` de forma cumulativa e garantir que cada execução seja persistida e reproduzida integralmente sem depender do estado atual das referências.

**Independent Test**: criar execução completa e autorizada → `is_label_ready = true`; remover/bloquear um requisito obrigatório → `false` com causas `LABEL_READINESS`; novo `POST` cria novo `id`; `GET` reproduz o snapshot após mudanças nas referências; `UPDATE`/`DELETE` falham por trigger.

### Tests for User Story 3 ⚠️

- [X] T045 [P] [US3] Adicionar a `tests/Feature/DeclaredNutritionCalculationTest.php` cenários de `is_label_ready`: `true` apenas com perfil, política, porção/medida caseira e todos os requisitos `MANDATORY`/`APPLICABLE` completos em ambas as bases, energia final e `%VD` aplicáveis (`AVAILABLE` ou `NO_NORMATIVE_VDR`); conjunto de requisitos vazio, requisito `UNKNOWN`/`BLOCKED`, açúcares totais/adicionados ou gorduras trans indisponíveis, ou porção não autorizada → `false` com todas as causas listadas, preservando valores declarados dos componentes independentes; `NOT_APPLICABLE` com justificativa não bloqueia (FR-006, FR-011; SC-003)
- [X] T046 [P] [US3] Adicionar a `tests/Feature/DeclaredNutritionPersistenceTest.php` cenários históricos: dois `POST` para a mesma origem geram `id`s distintos sem alterar o anterior; `GET` após inserir nova versão de perfil/regras retorna exatamente o snapshot original; `UPDATE`/`DELETE` em cada tabela da Feature 005 lançam exceção; contagem e conteúdo de tabelas das Features 001–004 inalterados após o `POST` (FR-012, FR-013; SC-004, SC-005)

### Implementation for User Story 3

- [X] T047 [US3] Adicionar a `app/Services/DeclaredValuesRegulatoryGateService.php` `requirementAuthorized()` (escopo `{declaration-profile}/requirement:{requirement_code}`; `NOT_APPLICABLE` exige justificativa) e `evaluateLabelReadiness(array $components, array $energy, array $percentages, ...): array{ready: bool, causes: list<array>}` aplicando todos os critérios cumulativos de `research.md` §5 Etapa 4 (FR-011)
- [X] T048 [US3] Integrar em `app/Services/DeclaredNutritionCalculationService.php` a etapa 4 após componentes, energia e `%VD`: gravar `is_label_ready` na raiz e causas no escopo `LABEL_READINESS`, garantindo que o `retrieve()` leia apenas o agregado persistido (FR-011, FR-012)
- [X] T049 [US3] Estender `app/Http/Resources/DeclaredNutritionCalculationResource.php` com `is_label_ready` e `label_readiness_causes` ordenadas por `(scope, scope_key, code)`
- [X] T050 [US3] Executar `php artisan test --compact tests/Feature/DeclaredNutritionCalculationTest.php tests/Feature/DeclaredNutritionPersistenceTest.php` e confirmar T045–T046 verdes

**Checkpoint**: todas as User Stories funcionais e independentes.

---

## Phase 6: Polish & Cross-Cutting Concerns

- [X] T051 [P] Revisar `app/Services/DeclaredNutritionCalculationService.php` e `app/Services/DeclaredValuesRegulatoryGateService.php` quanto a PHPDoc com array shapes, tipos de retorno explícitos, chaves em todas as estruturas de controle e ausência de `float`/`round()`/`(float)` (AGENTS.md; FR-003)
- [X] T052 [P] Verificar ausência de consultas N+1 no `create()`/`retrieve()` (eager loading completo) em `app/Services/DeclaredNutritionCalculationService.php`
- [X] T053 Confirmar que não foram criados Seeders, migrations de dados ou comandos que promovam regras da RDC nº 429/2020 / IN nº 75/2020 a `AUTHORIZED` (`database/seeders/DatabaseSeeder.php` inalterado) (FR-010; Constitution I)
- [X] T054 Executar `vendor/bin/pint --dirty --format agent` e corrigir formatação dos arquivos PHP alterados
- [X] T055 Executar a suíte completa `php artisan test --compact` e os cenários de `specs/005-declared-values-rounding/quickstart.md`, confirmando ausência de regressão nas Features 001–004 (SC-005)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sem dependências.
- **Foundational (Phase 2)**: depende de Setup; **bloqueia** todas as User Stories.
- **US1 (Phase 3)**: depende de Phase 2.
- **US2 (Phase 4)**: depende de Phase 2 e consome os valores declarados de US1 (T031) como entrada; testável isoladamente via fixtures.
- **US3 (Phase 5)**: depende de Phase 2; a avaliação de `is_label_ready` consome os resultados de US1 e US2.
- **Polish (Phase 6)**: depende das User Stories desejadas.

### Within Each Phase

- Testes antes da implementação (devem falhar primeiro).
- Migrations → Models → Services → Request/Resource → Controller → rotas.
- T009 depende de T008; T011 antes de T012–T016; T017 depois de T005–T016.
- T030 → T031 → T032 (mesmo arquivo, sequenciais); T041 → T042 → T048 (mesmo arquivo).

### Parallel Opportunities

- Phase 2: T004 com T003; T007, T008, T010 em paralelo após T005/T006; T013–T016 em paralelo após T012; T018–T020 e T022–T023 em paralelo.
- US1: T027 ∥ T028; T033 ∥ T034.
- US2: T038 e T039 tocam o mesmo arquivo de teste — escrever em sequência ou em blocos separados.
- US3: T045 ∥ T046 (arquivos distintos).

---

## Parallel Example: User Story 1

```bash
# Testes de US1 em paralelo:
Task: "T027 Gate tests in tests/Feature/DeclaredNutritionRegulatoryGateTest.php"
Task: "T028 Calculation tests in tests/Feature/DeclaredNutritionCalculationTest.php"

# Camada HTTP em paralelo (após T030–T032):
Task: "T033 StoreDeclaredNutritionCalculationRequest"
Task: "T034 DeclaredNutritionCalculationResource"
```

---

## Implementation Strategy

### MVP First (User Story 1)

1. Phase 1 (Setup) → Phase 2 (Foundational, T026 verde).
2. Phase 3 (US1) → **parar e validar**: valores declarados em ambas as bases, NSQ e bloqueios seguros.

### Incremental Delivery

1. Setup + Foundational → base pronta.
2. US1 → valores declarados (MVP).
3. US2 → energia e `%VD` finais recalculados.
4. US3 → `is_label_ready` e garantias históricas.
5. Polish → Pint, N+1, suíte completa e quickstart.

---

## Notes

- `[P]` = arquivos distintos sem dependência pendente.
- Nenhuma task cria ou promove valores regulatórios reais; caminhos autorizados usam apenas fixtures `TEST_FIXTURE` dentro dos testes.
- Commit após cada task ou grupo lógico; parar em cada checkpoint para validar a story.
