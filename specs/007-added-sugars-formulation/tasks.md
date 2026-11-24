# Tasks: Added Sugars Formulation Obtaining and Validation (007-added-sugars-formulation)

**Input**: Design documents from `/specs/007-added-sugars-formulation/`
**Prerequisites**: `plan.md` (required), `spec.md` (required), `research.md`, `data-model.md`, `contracts/added-sugars-formulation.md`, `quickstart.md`

**Tests**: Obrigatórios pela Constitution 2.0.0 e padrão do NutriLabel (testes escritos antes da implementação em cada fase/história, executados contra PostgreSQL real).

**Organization**: Tarefas agrupadas por User Story (P1 → P2 → P3) para permitir implementação e validação independentes.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Pode ser executada em paralelo (arquivos distintos, sem dependência de tarefas incompletas)
- **[Story]**: Identifica a User Story (`[US1]`, `[US2]`, `[US3]`) — apenas nas fases de User Story
- Todos os caminhos de arquivo são exatos e relativos à raiz do repositório

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Conferir contratos existentes e ambiente PostgreSQL antes de introduzir as migrations e serviços da Feature 007.

- [X] T001 Inspecionar `app/Services/NutritionCalculationArithmetic.php`, `app/Services/ServingDailyValueCalculationService.php`, `app/Services/ServingRegulatoryGateService.php`, `app/Services/DeclaredNutritionCalculationService.php`, `app/Services/DeclaredValuesRegulatoryGateService.php`, `app/Http/Requests/StoreServingDailyValueCalculationRequest.php` e `routes/api.php` para confirmar contratos retrocompatíveis das Features 003–006 (FR-001, FR-009, FR-012)
- [X] T002 [P] Confirmar que a suíte existente passa no PostgreSQL antes das alterações executando `php artisan test --compact` e inspecionando `database/migrations/2026_11_17_000005_protect_manual_food_versions_from_mutation.php` como referência do padrão de triggers PostgreSQL de imutabilidade

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Criar todo o schema PostgreSQL, triggers de imutabilidade e Models Eloquent que sustentam as User Stories 1, 2 e 3.

**⚠️ CRITICAL**: Nenhuma tarefa de User Story deve começar antes da conclusão integral desta fase.

### Tests de Persistência (escrever primeiro e confirmar falha)

- [X] T003 Criar testes estruturais PostgreSQL inicialmente falhos em `tests/Feature/AddedSugarsFormulationPersistenceTest.php` verificando colunas, tipos, `FOREIGN KEY ... ON DELETE RESTRICT`, `UNIQUE`, `CHECK` constraints literais (`ingredient_type IN ('SIMPLE', 'COMPOUND_INDUSTRIALIZED')`, `classification IN ('INCLUDED', 'EXCLUDED_NORMATIVE', 'MISSING', 'CONFLICTING')`, `amount_basis IN ('PER_100G', 'DIRECT_BATCH_GRAMS')`, `amount_value >= 0`, `PER_100G <= 100`, `amount_unit = 'g'`, `decision_status IN ('AUTHORIZED', 'REJECTED', 'REVOKED')`, `evidence_type IN ('DOCUMENT', 'TECHNICAL_REVIEW', 'TEST_FIXTURE')`, `validation_status IN ('PENDING', 'AUTHORIZED', 'REJECTED', 'REVOKED')`, coerência `is_regulatory_eligible`/`total_numerator`/`reference_numerator`/`is_confirmed_zero`) e bloqueio de `UPDATE`/`DELETE` por triggers PostgreSQL nas tabelas imutáveis (FR-001, FR-004, FR-006, FR-008, FR-011)

### Migrations & Triggers PostgreSQL

- [X] T004 Criar `database/migrations/2026_11_23_000001_create_added_sugars_formulations_table.php` com `id` (`BIGSERIAL` PK), `recipe_id` (`BIGINT NOT NULL UNIQUE`, `FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE RESTRICT`), `current_version_number` (`INTEGER NOT NULL`, `CHECK (current_version_number >= 1)`) e `timestamps`
- [X] T005 Criar `database/migrations/2026_11_23_000002_create_added_sugars_formulation_versions_table.php` com `id`, `formulation_id` (`BIGINT NOT NULL`, FK `added_sugars_formulations(id)` `ON DELETE RESTRICT`), `recipe_id` (`BIGINT NOT NULL`, FK `recipes(id)` `ON DELETE RESTRICT`), `version_number` (`INTEGER NOT NULL`, `CHECK (version_number >= 1)`), `normative_source` (`TEXT NOT NULL`), `normative_version` (`TEXT NOT NULL`), `registered_by` (`TEXT NOT NULL`), `registered_on` (`DATE NOT NULL`), `change_reason` (`TEXT NULL`), `timestamps` e `UNIQUE (formulation_id, version_number)`
- [X] T006 Criar `database/migrations/2026_11_23_000003_create_added_sugars_formulation_ingredients_table.php` com `id`, `formulation_version_id` (FK `ON DELETE RESTRICT`), `food_id` (FK `foods(id)` `ON DELETE RESTRICT`), `position` (`INTEGER NOT NULL`, `CHECK (position >= 1)`), `ingredient_type` (`TEXT NOT NULL`, `CHECK (ingredient_type IN ('SIMPLE', 'COMPOUND_INDUSTRIALIZED'))`), `classification` (`TEXT NOT NULL`, `CHECK (classification IN ('INCLUDED', 'EXCLUDED_NORMATIVE', 'MISSING', 'CONFLICTING'))`), `amount_basis` (`TEXT NULL`, `CHECK (amount_basis IS NULL OR amount_basis IN ('PER_100G', 'DIRECT_BATCH_GRAMS'))`), `amount_value` (`NUMERIC(14, 6) NULL`, `CHECK (amount_value IS NULL OR amount_value >= 0)`), `amount_unit` (`TEXT NULL`, `CHECK (amount_unit IS NULL OR amount_unit = 'g')`), `exclusion_justification` (`TEXT NULL`), `conflict_notes` (`TEXT NULL`), `UNIQUE (formulation_version_id, position)` e `CHECK` constraints de coerência por classificação (`INCLUDED`, `PER_100G <= 100`, `EXCLUDED_NORMATIVE`, `CONFLICTING`)
- [X] T007 Criar `database/migrations/2026_11_23_000004_create_added_sugars_formulation_evidences_table.php` com `id`, `formulation_ingredient_id` (FK `added_sugars_formulation_ingredients(id)` `ON DELETE RESTRICT`), `source_type` (`TEXT NOT NULL`, `CHECK (source_type IN ('MANUFACTURER_SPEC', 'LAB_REPORT', 'OPERATIONAL_FORM', 'NORMATIVE_REFERENCE', 'TECHNICAL_TABLE', 'TEST_FIXTURE'))`), `source_name` (`TEXT NOT NULL`), `document_reference` (`TEXT NOT NULL`), `reported_amount_basis` (`TEXT NULL`, `CHECK` `PER_100G`/`DIRECT_BATCH_GRAMS`), `reported_amount_value` (`NUMERIC(14, 6) NULL`, `CHECK >= 0`), `reported_amount_unit` (`TEXT NULL`, `CHECK = 'g'`), `only_total_sugars_reported` (`BOOLEAN NOT NULL DEFAULT false`), `recorded_by` (`TEXT NOT NULL`), `recorded_on` (`DATE NOT NULL`) e `timestamps`
- [X] T008 Criar `database/migrations/2026_11_23_000005_create_added_sugars_formulation_validations_table.php` com `id`, `formulation_version_id` (FK `ON DELETE RESTRICT`), `decision_status` (`TEXT NOT NULL`, `CHECK (decision_status IN ('AUTHORIZED', 'REJECTED', 'REVOKED'))`), `normative_source` (`TEXT NOT NULL`), `normative_version` (`TEXT NOT NULL`), `evidence_type` (`TEXT NOT NULL`, `CHECK (evidence_type IN ('DOCUMENT', 'TECHNICAL_REVIEW', 'TEST_FIXTURE'))`), `evidence_reference` (`TEXT NOT NULL`), `evidence_source` (`TEXT NOT NULL`), `evidence_version` (`TEXT NOT NULL`), `evidence_scope` (`TEXT NOT NULL`), `validated_by` (`TEXT NOT NULL`), `validated_on` (`DATE NOT NULL`), `notes` (`TEXT NULL`) e `timestamps`
- [X] T009 Criar `database/migrations/2026_11_23_000006_create_added_sugars_evaluations_table.php` com todos os campos de snapshot conforme `data-model.md`, FKs `ON DELETE RESTRICT` para `nutrition_calculations`, `added_sugars_formulations`, `added_sugars_formulation_versions` e `added_sugars_formulation_validations`, `CHECK` constraints de `source_yield_unit`, `reference_unit`, `total_unit`, `validation_status`, pareamento de `numerator`/`denominator` e coerência de `is_regulatory_eligible`, `is_complete` e `is_confirmed_zero`
- [X] T010 Criar `database/migrations/2026_11_23_000007_create_added_sugars_evaluation_items_table.php` e `database/migrations/2026_11_23_000008_create_added_sugars_evaluation_causes_table.php` conforme `data-model.md` com FKs `ON DELETE RESTRICT` e `CHECK` constraints
- [X] T011 Criar `database/migrations/2026_11_23_000009_protect_added_sugars_history_from_mutation.php` com triggers PostgreSQL bloqueando `UPDATE` e `DELETE` em `added_sugars_formulation_versions`, `added_sugars_formulation_ingredients`, `added_sugars_formulation_evidences`, `added_sugars_formulation_validations`, `added_sugars_evaluations`, `added_sugars_evaluation_items` e `added_sugars_evaluation_causes` (FR-008)
- [X] T012 Criar `database/migrations/2026_11_23_000010_add_added_sugars_evaluation_to_serving_daily_values.php` adicionando colunas nullable aditivas em `serving_daily_value_calculations` (`added_sugars_evaluation_id` FK `ON DELETE RESTRICT` + snapshots) e em `serving_daily_value_regulatory_results` (`value_source_type` default `'NUTRITION_CALCULATION'`, `source_reference_numerator`, `source_reference_denominator`) sem quebrar registros históricos existentes (FR-009)

### Eloquent Models

- [X] T013 [P] Criar `app/Models/AddedSugarsFormulation.php` com `$fillable`, relacionamento `belongsTo(Recipe::class)` e `hasMany(AddedSugarsFormulationVersion::class, 'formulation_id')`
- [X] T014 [P] Criar `app/Models/AddedSugarsFormulationVersion.php` com `$fillable`, casts (`registered_on`), relacionamentos `formulation()`, `recipe()`, `ingredients()` e `validations()`
- [X] T015 [P] Criar `app/Models/AddedSugarsFormulationIngredient.php` com `$fillable`, constantes de tipos/classificações/bases e relacionamentos `version()`, `food()` e `evidences()`
- [X] T016 [P] Criar `app/Models/AddedSugarsFormulationEvidence.php` com `$fillable`, casts (`only_total_sugars_reported`, `recorded_on`) e relacionamento `ingredient()`
- [X] T017 [P] Criar `app/Models/AddedSugarsFormulationValidation.php` com `$fillable`, constantes de `decision_status` e `evidence_type`, cast `validated_on` e relacionamento `version()`
- [X] T018 [P] Criar `app/Models/AddedSugarsEvaluation.php` com `RULES_VERSION = 'added-sugars-formulation-v1'`, `$fillable`, casts booleanos/data e relacionamentos `nutritionCalculation()`, `formulation()`, `version()`, `validation()`, `items()` e `causes()`
- [X] T019 [P] Criar `app/Models/AddedSugarsEvaluationItem.php` e `app/Models/AddedSugarsEvaluationCause.php` com `$fillable`, casts e relacionamentos para `evaluation()`, `nutritionCalculationIngredient()`, `formulationIngredient()` e `item()`
- [X] T020 Executar `php artisan test --compact tests/Feature/AddedSugarsFormulationPersistenceTest.php` e confirmar 100% de aprovação das constraints, FKs e triggers PostgreSQL

**Checkpoint**: Persistência e imutabilidade PostgreSQL prontas e testadas — User Stories podem iniciar.

---

## Phase 3: User Story 1 - Registrar e avaliar açúcares adicionados a partir da formulação do produto (Priority: P1) 🎯 MVP

**Goal**: Permitir registrar a formulação de açúcares adicionados de uma receita (ingredientes simples e compostos/industrializados, classificações `INCLUDED`, `EXCLUDED_NORMATIVE`, `MISSING`, `CONFLICTING`, evidências e bases `PER_100G`/`DIRECT_BATCH_GRAMS`), consultar a formulação e executar avaliações exatas vinculadas a uma `NutritionCalculation` da Feature 003 (com aritmética exata `BigRational`, sem alterar TACO e mantendo agregados `null` diante de qualquer pendência ou falta de validação).

**Independent Test**: Criar receita + `NutritionCalculation`, registrar formulação `v1` com ingredientes `EXCLUDED_NORMATIVE`, `INCLUDED` (`PER_100G` e `DIRECT_BATCH_GRAMS`), `MISSING` e `CONFLICTING`, executar avaliação e verificar contribuições individuais exatas, preservação de causas, agregados `null` quando pendente/incompleto e total + `100 g/100 ml` exatos (inclusive zero confirmado `0/1`) quando completo e autorizado.

### Tests for User Story 1 (escrever primeiro e confirmar falha)

- [X] T021 [P] [US1] Criar testes inicialmente falhos em `tests/Feature/AddedSugarsFormulationTest.php` cobrindo `POST/GET /api/recipes/{recipe}/added-sugars-formulations`, `POST /api/nutrition-calculations/{nutritionCalculation}/added-sugars-evaluations` e `GET /api/added-sugars-evaluations/{addedSugarsEvaluation}`: registro de `SIMPLE` e `COMPOUND_INDUSTRIALIZED`, `PER_100G` (inclusive `<= 100`) e `DIRECT_BATCH_GRAMS` (inclusive rejeição quando excede `ingredient_quantity`), `EXCLUDED_NORMATIVE` com justificativa, `MISSING`, `only_total_sugars_reported`, `CONFLICTING`, cálculo racional exato sem `float`, `is_confirmed_zero = true` para formulação isenta validada, e preservação integral da TACO (FR-001–FR-006, FR-011; SC-001, SC-002, SC-005)

### Implementation for User Story 1

- [X] T022 [P] [US1] Criar `app/Http/Requests/StoreAddedSugarsFormulationRequest.php` validando `normative_source`, `normative_version`, `registered_by`, `registered_on`, `change_reason` (obrigatório quando já existir versão anterior), `ingredients` (posições únicas, `food_id` existente, `ingredient_type`, `classification`, strings decimais exatas `>= 0`, `<= 100` para `PER_100G`, `amount_unit = 'g'`, proibição de `INCLUDED` quando todas as evidências tiverem `only_total_sugars_reported = true`, `exclusion_justification` para `EXCLUDED_NORMATIVE`, `conflict_notes` para `CONFLICTING`) e `evidences` (FR-002, FR-003, FR-004)
- [X] T023 [P] [US1] Criar `app/Http/Requests/StoreAddedSugarsEvaluationRequest.php` validando `formulation_version_id` (`required|integer|exists:added_sugars_formulation_versions,id`)
- [X] T024 [P] [US1] Criar `app/Http/Resources/AddedSugarsFormulationResource.php` e `app/Http/Resources/AddedSugarsEvaluationResource.php` serializando de forma determinística (sem `float`, razões em `numerator`/`denominator` string, estado de validação, itens ordenados por `position` e causas) conforme `contracts/added-sugars-formulation.md`
- [X] T025 [US1] Implementar `app/Services/AddedSugarsFormulationService.php` (`createVersion` em transação `REPEATABLE READ` e `retrieve` com *eager loading* sem N+1) sem alterar `foods`, `nutrients` ou `recipes` (FR-001, FR-003, FR-008)
- [X] T026 [US1] Implementar `app/Services/AddedSugarsEvaluationService.php` (`create` em transação `REPEATABLE READ` e `retrieve`) pareando `NutritionCalculationIngredient` por `(position, food_id)` com a versão da formulação, reutilizando `NutritionCalculationArithmetic` (`BigRational`) para `PER_100G` (`quantity × amount_value ÷ 100`), `DIRECT_BATCH_GRAMS` (com checagem `amount_value <= ingredient_quantity`), `EXCLUDED_NORMATIVE` (`0/1`), consolidação do total e normalização para `100 g/100 ml` (`total × 100 ÷ yield_value`), mantendo `total = null` e `reference = null` sempre que houver qualquer item incompleto/ausente/conflitante/descoberto ou versão não autorizada (FR-005, FR-006, FR-011)
- [X] T027 [US1] Criar `app/Http/Controllers/AddedSugarsFormulationController.php` e `app/Http/Controllers/AddedSugarsEvaluationController.php` (finos, apenas delegando aos Services e Resources) e registrar as rotas em `routes/api.php` (FR-001, FR-005)

**Checkpoint**: User Story 1 funcional e testável de forma independente.

---

## Phase 4: User Story 2 - Governar validação técnica, evidências e ingredientes compostos ou industrializados (Priority: P2)

**Goal**: Garantir governança estrita *deny-by-default* com trilha *append-only* de decisões de validação técnica (`AUTHORIZED`, `REJECTED`, `REVOKED`), restrição de `TEST_FIXTURE` exclusivamente a `APP_ENV=testing`, rejeição de autoaprovação por `OPERATIONAL_FORM` e registro detalhado de causas impeditivas.

**Independent Test**: Submeter evidências de `OPERATIONAL_FORM` e `MANUFACTURER_SPEC`, tentar avaliar antes de validar (`PENDING`), após `REJECTED`, após `REVOKED`, com `evidence_scope` divergente e com `TEST_FIXTURE` em ambiente não-`testing` — confirmando bloqueio estrito (`total = null`, `reference = null`, `is_regulatory_eligible = false`) em todos os cenários negativos e liberação apenas com validação técnica válida `AUTHORIZED`.

### Tests for User Story 2 (escrever primeiro e confirmar falha)

- [X] T028 [P] [US2] Estender `tests/Feature/AddedSugarsFormulationTest.php` com testes inicialmente falhos para `POST /api/added-sugars-formulation-versions/{version}/validations` e gates *deny-by-default*: bloqueio em `PENDING`, `REJECTED`, `REVOKED`, escopo `evidence_scope` inválido, `TEST_FIXTURE` bloqueado fora de `testing` (simulando `APP_ENV=production`), `OPERATIONAL_FORM` não liberando sem validação técnica `AUTHORIZED`, `notes` obrigatório para `REJECTED`/`REVOKED`, e ordem cronológica da trilha *append-only* (FR-003, FR-007, FR-008; SC-002, SC-004)

### Implementation for User Story 2

- [X] T029 [P] [US2] Criar `app/Http/Requests/StoreAddedSugarsFormulationValidationRequest.php` validando `decision_status` (`AUTHORIZED`, `REJECTED`, `REVOKED`), `normative_source`, `normative_version`, `evidence_type` (`DOCUMENT`, `TECHNICAL_REVIEW`, `TEST_FIXTURE`), `evidence_reference`, `evidence_source`, `evidence_version`, `evidence_scope`, `validated_by`, `validated_on` e `notes` obrigatório em `REJECTED`/`REVOKED` (FR-007, FR-008)
- [X] T030 [P] [US2] Criar `app/Http/Resources/AddedSugarsFormulationValidationResource.php` conforme `contracts/added-sugars-formulation.md`
- [X] T031 [US2] Implementar `app/Services/AddedSugarsRegulatoryGateService.php` avaliando a última decisão *append-only* de uma `AddedSugarsFormulationVersion` em modo *deny-by-default*: exigindo `AUTHORIZED`, campos de evidência não vazios, `evidence_scope === "recipe:{recipe_id}/added-sugars-formulation:v{version_number}"`, bloqueando `TEST_FIXTURE` fora de `app()->environment('testing')`, e retornando causas padronizadas (`FORMULATION_VALIDATION_PENDING`, `FORMULATION_VALIDATION_REJECTED`, `FORMULATION_VALIDATION_REVOKED`, `FORMULATION_VALIDATION_SCOPE_MISMATCH`, `TEST_FIXTURE_NOT_ALLOWED`) (FR-003, FR-007)
- [X] T032 [US2] Integrar `AddedSugarsFormulationService::recordValidation` (append-only em transação `REPEATABLE READ`) e `AddedSugarsEvaluationService` com `AddedSugarsRegulatoryGateService` para que toda avaliação aplique os gates *deny-by-default* e snapshot dos metadados da validação vigente (FR-007, FR-008)
- [X] T033 [US2] Adicionar método `storeValidation` em `app/Http/Controllers/AddedSugarsFormulationController.php` e registrar `POST /api/added-sugars-formulation-versions/{version}/validations` em `routes/api.php`

**Checkpoint**: User Stories 1 e 2 funcionam e passam todos os cenários de governança e bloqueio *deny-by-default*.

---

## Phase 5: User Story 3 - Versionar formulações, preservar snapshots históricos e integrar com Features 004 e 005 (Priority: P3)

**Goal**: Garantir que novas versões da formulação e novas decisões de validação nunca alterem snapshots históricos já persistidos, e habilitar o consumo explícito e opcional de `added_sugars_evaluation_id` na Feature 004 (`MAP-004` e `VDR-004`) com propagação transparente para a Feature 005 (`100 g/100 ml`, porção, NSQ, arredondamento e `%VD` final), sem criar fator energético adicional nem quebrar execuções existentes.

**Independent Test**: Criar avaliação elegível, criar versão `v2` e revogar `v1` confirmando imutabilidade integral da avaliação anterior; testar integração com Feature 004 (rejeição `422` por `NutritionCalculation` divergente, consumo elegível em `MAP-004` + `VDR-004` autorizado vs. `VDR-004` bloqueado, propagação de causas quando avaliação bloqueada, ausência de fator energético adicional) e propagação ponta a ponta até a Feature 005 (`DeclaredNutritionCalculation`).

### Tests for User Story 3 (escrever primeiro e confirmar falha)

- [X] T034 [P] [US3] Criar testes inicialmente falhos em `tests/Feature/AddedSugarsEvaluationIntegrationTest.php` cobrindo: versionamento `v1 -> v2` + revogação mantendo snapshots anteriores idênticos; rejeição `422` na Feature 004 quando `added_sugars_evaluation_id` pertence a outra `NutritionCalculation`; consumo elegível em `MAP-004` na Feature 004 (porção exata `total × portion ÷ yield` + `source_reference` `100 g/100 ml`, `VDR-004` liberado apenas quando autorizado, nenhum fator energético adicional); propagação de bloqueio/causas quando avaliação não elegível; consumo ponta a ponta pela Feature 005 (`DeclaredNutritionCalculation` com NSQ, arredondamento e `%VD` final para `MAP-004`); e retrocompatibilidade total quando `added_sugars_evaluation_id` é omitido (FR-008, FR-009, FR-010; SC-003)

### Implementation for User Story 3

- [X] T035 [P] [US3] Estender `app/Http/Requests/StoreServingDailyValueCalculationRequest.php` aceitando campo opcional `added_sugars_evaluation_id` (`nullable|integer|exists:added_sugars_evaluations,id`) mantendo 100% de retrocompatibilidade
- [X] T036 [P] [US3] Atualizar `$fillable` e relações em `app/Models/ServingDailyValueCalculation.php` (`addedSugarsEvaluation()` + colunas de snapshot) e `app/Models/ServingDailyValueRegulatoryResult.php` (`value_source_type`, `source_reference_numerator`, `source_reference_denominator`)
- [X] T037 [US3] Estender `app/Services/ServingDailyValueCalculationService.php` para validar `evaluation.nutrition_calculation_id === source.id` (lançando `ValidationException` `422` se divergir), persistir snapshot da avaliação no root da Feature 004, preencher `MAP-004` com valor exato por porção (`total × portion ÷ yield`) e `source_reference` (`100 g/100 ml`) quando elegível e perfil autorizado (sem adicionar energia e respeitando autorização individual de `VDR-004`), ou manter `MAP-004`/`VDR-004` `null` com causas herdadas quando a avaliação estiver bloqueada/incompleta (FR-009, FR-010)
- [X] T038 [US3] Atualizar `app/Http/Resources/ServingDailyValueCalculationResource.php` para expor de forma retrocompatível os metadados de `added_sugars_evaluation` (quando referenciada) e `value_source_type` / `source_reference` em `regulatory_results`
- [X] T039 [US3] Estender `app/Services/DeclaredValuesRegulatoryGateService.php` e `app/Services/DeclaredNutritionCalculationService.php` para reconhecer `MAP-004` com `value_source_type === 'ADDED_SUGARS_FORMULATION'` e snapshot elegível da Feature 004, consumindo `source_reference_numerator/denominator` (para `REFERENCE_100`) e `value_numerator/denominator` (para `PORTION`) sob todas as regras de NSQ, arredondamento, recálculo de `%VD` e `is_label_ready` da Feature 005 sem exigir `FoodNutrient` (FR-009, FR-010)
- [X] T040 [US3] Verificar que `tests/Feature/AddedSugarsEvaluationIntegrationTest.php` passa integralmente (`php artisan test --compact tests/Feature/AddedSugarsEvaluationIntegrationTest.php`)
- [X] T041 [US3] Executar regressão direcionada das Features 003, 004, 005 e 006 (`php artisan test --compact tests/Feature/NutritionCalculationTest.php tests/Feature/ServingDailyValueCalculationTest.php tests/Feature/ServingDailyValueRegulatoryGateTest.php tests/Feature/DeclaredNutritionCalculationTest.php tests/Feature/ManualFoodRegistrationTest.php`)

**Checkpoint**: Todas as User Stories (P1, P2, P3) implementadas e integradas com retrocompatibilidade comprovada.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Auditoria final, regressão completa, formatação e verificação de higiene do repositório.

- [X] T042 Executar a suíte completa de testes da aplicação em PostgreSQL (`php artisan test --compact`) confirmando 0 falhas e 0 regressões nas Features 001–007
- [X] T043 [P] Executar formatação obrigatória com Laravel Pint (`vendor/bin/pint --dirty --format agent`) e revisar `git diff --check`
- [X] T044 [P] Auditar a implementação contra todos os cenários de `specs/007-added-sugars-formulation/quickstart.md` e contra as regras críticas da Constitution 2.0.0 (zero `float`, zero mutação TACO, zero conversão silenciosa para zero, `TEST_FIXTURE` isolado em `testing`, imutabilidade verificada)
- [X] T045 Revisar `git status` e arquivos não rastreados para garantir ausência de arquivos temporários ou credenciais antes de preparar revisão/commit

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: Sem dependências — inicia imediatamente (`T001`–`T002`).
- **Foundational (Phase 2)**: Depende de Phase 1 — **BLOQUEIA** todas as User Stories (`T003` → `T004`–`T012` → `T013`–`T019` → `T020`).
- **User Story 1 (Phase 3 — P1)**: Depende de Phase 2 (`T021` → `T022`–`T024` em paralelo → `T025`–`T027`).
- **User Story 2 (Phase 4 — P2)**: Depende de Phase 2 e integra-se aos Services de US1 (`T028` → `T029`–`T030` em paralelo → `T031`–`T033`).
- **User Story 3 (Phase 5 — P3)**: Depende de US1 e US2 concluídas para validar o ciclo completo com Features 004 e 005 (`T034` → `T035`–`T036` em paralelo → `T037`–`T041`).
- **Polish (Phase 6)**: Depende da conclusão de todas as User Stories (`T042`–`T045`).

### Parallel Opportunities

- **Phase 2**: `T013`–`T019` (todos os 8 Models Eloquent) podem ser criados em paralelo após `T004`–`T012`.
- **Phase 3 (US1)**: `T022`, `T023` e `T024` (Requests e Resources) podem ser criados em paralelo após `T021`.
- **Phase 4 (US2)**: `T029` e `T030` (Validation Request e Validation Resource) podem ser criados em paralelo após `T028`.
- **Phase 5 (US3)**: `T035` e `T036` (Request e Models da Feature 004) podem ser atualizados em paralelo após `T034`.
- **Phase 6**: `T043` e `T044` podem rodar em paralelo após `T042`.

---

## Implementation Strategy

### MVP First (User Story 1 Only)
1. Concluir Phase 1 (Setup) e Phase 2 (Foundational: migrations, triggers e Models + `T020` verde).
2. Concluir Phase 3 (User Story 1: registro versionado, consulta e avaliação exata vinculada à `NutritionCalculation`).
3. Validar independentemente US1 antes de avançar para US2 e US3.

### Incremental Delivery
1. Setup + Foundational → Persistência imutável PostgreSQL validada.
2. US1 → Formulação + avaliação exata de açúcares adicionados (MVP).
3. US2 → Governança *deny-by-default* + trilha *append-only* de validações técnicas.
4. US3 → Integração retrocompatível com Features 004 (`MAP-004`/`VDR-004`) e 005 (`DeclaredNutritionCalculation`).
5. Polish → Suíte completa, Pint e auditoria contra Constitution 2.0.0.
