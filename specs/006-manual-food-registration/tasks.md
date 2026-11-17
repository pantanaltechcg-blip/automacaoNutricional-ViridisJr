# Tasks: Governed Manual Food Registration (006-manual-food-registration)

**Input**: Design documents from `/specs/006-manual-food-registration/`

**Prerequisites**: `plan.md` (required), `spec.md` (required for user stories), `research.md`, `data-model.md`, `contracts/manual-food-registration.md`, `quickstart.md`

**Tests**: Obrigatórios; cada fase/User Story inicia pelos testes inicialmente falhos correspondentes, com cobertura PostgreSQL, contratos HTTP, casos negativos, proteção da base TACO e imutabilidade histórica.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., `US1`, `US2`, `US3`)
- Include exact file paths in descriptions

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Confirmar a stack e os contratos existentes sem alterar dependências ou quebrar retrocompatibilidade das Features 001–004.

- [x] T001 Confirmar em `composer.json`, `composer.lock` e `phpunit.xml` a stack Laravel 13 / PHP 8.4+ / PostgreSQL 17 e executar a suíte baseline existente (`php artisan test --compact`) sem introduzir dependências novas (FR-001; SC-002)
- [x] T002 [P] Inspecionar `app/Models/Food.php`, `app/Models/FoodNutrient.php`, `app/Services/NutritionBaseService.php`, `app/Http/Requests/StoreRecipeRequest.php`, `app/Http/Requests/UpdateRecipeRequest.php` e `routes/api.php` para confirmar os contratos existentes das Features 001–003 que devem permanecer retrocompatíveis (FR-001, FR-009, FR-010; SC-002)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Criar o schema PostgreSQL, índices únicos normalizados, triggers de imutabilidade e proteção TACO, e os Models compartilhados por todas as User Stories.

**⚠️ CRITICAL**: Nenhuma User Story começa antes de `T013` passar integralmente.

### Tests for Foundation

- [x] T003 Criar testes estruturais PostgreSQL inicialmente falhos em `tests/Feature/ManualFoodPersistenceTest.php` verificando colunas, tipos, `FOREIGN KEY ... ON DELETE RESTRICT`, `UNIQUE`, `CHECK` constraints literais em `manual_food_registrations`, `manual_food_versions` e `manual_food_version_nutrients`, índices únicos normalizados em `foods`, trigger bloqueando vínculo em `manual_food_registrations` para `foods` com `UPPER(BTRIM(source)) = 'TACO'` e triggers `BEFORE UPDATE OR DELETE` bloqueando mutação em `manual_food_versions`, `manual_food_version_nutrients` e `foods` TACO (FR-001–FR-006, FR-008, FR-009; SC-001, SC-002)

### Migrations & Database Protection

- [x] T004 Criar `database/migrations/2026_11_17_000001_create_manual_food_registrations_table.php` conforme `data-model.md` com `id` (`BIGINT` PK), `food_id` (`BIGINT NOT NULL UNIQUE`, `FOREIGN KEY (food_id) REFERENCES foods(id) ON DELETE RESTRICT`), `is_active` (`BOOLEAN NOT NULL DEFAULT true`), `current_version_number` (`INTEGER NOT NULL`, `CHECK (current_version_number >= 1)`), `reference_unit` (`VARCHAR(2) NOT NULL DEFAULT 'g'`, `CHECK (reference_unit = 'g')`), `created_at` e `updated_at` (FR-001–FR-003, FR-007)
- [x] T005 Criar `database/migrations/2026_11_17_000002_create_manual_food_versions_table.php` conforme `data-model.md` sem `updated_at`, com `id` (`BIGINT` PK), `manual_food_registration_id` (`BIGINT NOT NULL`, `FOREIGN KEY (manual_food_registration_id) REFERENCES manual_food_registrations(id) ON DELETE RESTRICT`), `version_number` (`INTEGER NOT NULL`, `CHECK (version_number >= 1)`), `name` (`TEXT NOT NULL`, `CHECK (BTRIM(name) <> '')`), `category` (`TEXT NULL`, `CHECK (category IS NULL OR BTRIM(category) <> '')`), `source` (`TEXT NOT NULL`, `CHECK (BTRIM(source) <> '' AND UPPER(BTRIM(source)) <> 'TACO')`), `source_code` (`TEXT NOT NULL`, `CHECK (BTRIM(source_code) <> '')`), `reference_unit` (`VARCHAR(2) NOT NULL`, `CHECK (reference_unit = 'g')`), `evidence_reference` (`TEXT NOT NULL`, `CHECK (BTRIM(evidence_reference) <> '')`), `evidence_source` (`TEXT NOT NULL`, `CHECK (BTRIM(evidence_source) <> '')`), `registered_by` (`TEXT NOT NULL`, `CHECK (BTRIM(registered_by) <> '')`), `registered_on` (`DATE NOT NULL`), `change_reason` (`TEXT NULL`, `CHECK (version_number = 1 OR (change_reason IS NOT NULL AND BTRIM(change_reason) <> ''))`), `created_at` (`TIMESTAMP NOT NULL`) e `UNIQUE (manual_food_registration_id, version_number)` (FR-001–FR-003, FR-008; SC-001)
- [x] T006 Criar `database/migrations/2026_11_17_000003_create_manual_food_version_nutrients_table.php` conforme `data-model.md` sem timestamps, com `id` (`BIGINT` PK), `manual_food_version_id` (`BIGINT NOT NULL`, `FOREIGN KEY (manual_food_version_id) REFERENCES manual_food_versions(id) ON DELETE RESTRICT`), `nutrient_id` (`BIGINT NOT NULL`, `FOREIGN KEY (nutrient_id) REFERENCES nutrients(id) ON DELETE RESTRICT`), `amount_value` (`DECIMAL(7, 2) NULL`), `status` (`TEXT NOT NULL`, `CHECK (status IN ('EXACT', 'TRACE', 'NA', 'ABSENT', 'REEVALUATION'))`), `CHECK ((status = 'EXACT' AND amount_value IS NOT NULL AND amount_value >= 0 AND amount_value <> 'Infinity'::numeric AND amount_value <> 'NaN'::numeric) OR (status <> 'EXACT' AND amount_value IS NULL))` e `UNIQUE (manual_food_version_id, nutrient_id)` (FR-004, FR-005; SC-001)
- [x] T007 Criar `database/migrations/2026_11_17_000004_add_normalized_identity_indexes_to_foods_table.php` adicionando os índices únicos funcionais `foods_normalized_source_code_unique` em `(UPPER(BTRIM(source)), UPPER(BTRIM(source_code))) WHERE source_code IS NOT NULL` e `foods_normalized_source_name_unique` em `(UPPER(BTRIM(source)), LOWER(BTRIM(name)))` na tabela `foods`, sem alterar ou reescrever registros existentes da TACO (FR-006; SC-002)
- [x] T008 Criar `database/migrations/2026_11_17_000005_protect_manual_food_versions_from_mutation.php` com as funções e triggers PostgreSQL reversíveis `prevent_manual_food_version_mutation()` (`BEFORE UPDATE OR DELETE` em `manual_food_versions` e `manual_food_version_nutrients`) e `prevent_taco_food_manual_registration()` (`BEFORE INSERT OR UPDATE` em `manual_food_registrations` bloqueando vínculo a qualquer `foods` com `UPPER(BTRIM(source)) = 'TACO'` e `BEFORE UPDATE OR DELETE` em `foods` quando `UPPER(BTRIM(OLD.source)) = 'TACO'` impedindo alteração de `source` para fora de `'TACO'`) (FR-001, FR-008, FR-009; SC-002)

### Models

- [x] T009 [P] Atualizar `app/Models/Food.php` adicionando o relacionamento `manualRegistration(): HasOne` para `ManualFoodRegistration`, mantendo `fillable` e `foodNutrients()` existentes inalterados (FR-001, FR-010)
- [x] T010 [P] Criar `app/Models/ManualFoodRegistration.php` com casts apropriados (`is_active` booleano, `current_version_number` inteiro) e relacionamentos `food(): BelongsTo`, `versions(): HasMany` (ordenado por `version_number`) e `currentVersion(): HasOne` (FR-001, FR-007, FR-008)
- [x] T011 [P] Criar `app/Models/ManualFoodVersion.php` com `public const UPDATED_AT = null;`, `registered_on` preservado em formato `Y-m-d` e relacionamentos `registration(): BelongsTo` e `nutrients(): HasMany` ordenado por `nutrient_id` (FR-002, FR-008)
- [x] T012 [P] Criar `app/Models/ManualFoodVersionNutrient.php` com `public $timestamps = false;`, constantes de status reutilizadas de `FoodNutrient` (`STATUS_EXACT`, `STATUS_TRACE`, `STATUS_NA`, `STATUS_ABSENT`, `STATUS_REEVALUATION`) e relacionamentos `version(): BelongsTo` e `nutrient(): BelongsTo` (FR-004, FR-005)

### Foundation Checkpoint

- [x] T013 Executar `php artisan migrate`, `php artisan migrate:status` e `php artisan test --compact tests/Feature/ManualFoodPersistenceTest.php` confirmando que o schema PostgreSQL, constraints, índices e triggers de imutabilidade passam integralmente antes das User Stories (FR-001–FR-009; SC-001, SC-002)

---

## Phase 3: User Story 1 — Cadastrar alimento ausente da TACO com origem e composição rastreáveis (Priority: P1) 🎯 MVP

**Goal**: Permitir cadastrar manualmente alimentos simples ausentes da TACO com identificação, origem distinta da TACO, evidência documental, base de referência `100 g` e composição nutricional (valores exatos não negativos e/ou estados qualitativos), além de consultar o registro governado mantendo a TACO integralmente preservada.

**Independent Test**: Enviar `POST /api/manual-foods` com alimento válido contendo nutrientes `EXACT` e qualitativos (`TRACE`, `NA`, `ABSENT`, `REEVALUATION`) e consultar `GET /api/manual-foods/{food}`, comprovando retorno `201`/`200`, preservação dos estados sem conversão para zero, rejeição de entradas inválidas ou `source = 'TACO'` e `404` para alimentos TACO.

### Tests for User Story 1

- [x] T014 [P] [US1] Criar testes inicialmente falhos em `tests/Feature/ManualFoodRegistrationTest.php` cobrindo `POST /api/manual-foods` e `GET /api/manual-foods/{food}`: cadastro válido com `EXACT` (`0.00` e decimais até 2 casas) e estados qualitativos (`TRACE`, `NA`, `ABSENT`, `REEVALUATION`), omissão de nutrientes não informados sem conversão para zero, normalização HTTP (`TrimStrings`/`ConvertEmptyStringsToNull`), rejeição de campos obrigatórios ausentes, rejeição de `source = 'TACO'` (qualquer caixa/espaço), rejeição de `reference_unit != 'g'`, rejeição de `amount_value` como `float`/negativo/acima de `99999.99`/mais de 2 casas decimais, rejeição de `amount_value != null` em estado qualitativo, rejeição de `nutrients` vazio ou com `nutrient_id` inexistente/repetido, `404` ao consultar alimento TACO em `GET /api/manual-foods/{food}` e preservação integral da base TACO (FR-001–FR-005, FR-008; SC-001–SC-003)

### Implementation for User Story 1

- [x] T015 [US1] Criar `app/Http/Requests/StoreManualFoodRequest.php` validando `name` (`required`, `string`), `category` (`nullable`, `string`), `source` (`required`, `string`, rejeitando `UPPER(BTRIM(source)) === 'TACO'`), `source_code` (`required`, `string`), `reference_unit` (`required`, `string`, `Rule::in(['g'])`), `evidence_reference` (`required`, `string`), `evidence_source` (`required`, `string`), `registered_by` (`required`, `string`), `registered_on` (`required`, `date_format:Y-m-d`), `nutrients` (`required`, `array`, `min:1`), `nutrients.*.nutrient_id` (`required`, `integer`, `distinct`, `Rule::exists('nutrients', 'id')`), `nutrients.*.status` (`required`, `string`, `Rule::in(['EXACT', 'TRACE', 'NA', 'ABSENT', 'REEVALUATION'])`) e validação customizada estrita de `nutrients.*.amount_value` como string decimal `^\d{1,5}(?:\.\d{1,2})?$` quando `status = 'EXACT'` e obrigatoriamente `null` nos demais estados (FR-001–FR-005; SC-001, SC-003)
- [x] T016 [US1] Criar `app/Services/ManualFoodRegistrationService.php` implementando `create(array $data): ManualFoodRegistration` (em transação `REPEATABLE READ`, criando `Food`, `FoodNutrient`, `ManualFoodRegistration` com `is_active = true` e `current_version_number = 1`, `ManualFoodVersion` `v1` e `ManualFoodVersionNutrient`) e `retrieve(Food $food): ManualFoodRegistration` (lançando `ModelNotFoundException` se `food` não possuir `manualRegistration`, protegendo registros TACO) (FR-001–FR-005, FR-008; SC-001–SC-003)
- [x] T017 [US1] Criar `app/Http/Resources/ManualFoodRegistrationResource.php` serializando `id` (`food_id`), `is_active`, `current_version_number`, `reference_unit`, `name`, `category`, `source`, `source_code`, `current_version` e `versions` ordenadas por `version_number` com `nutrients` ordenados por `nutrient_id` e `amount_value` formatado em string com 2 casas decimais quando `EXACT` ou `null` nos estados qualitativos, conforme `contracts/manual-food-registration.md` (FR-001, FR-004, FR-008; SC-001)
- [x] T018 [US1] Criar `app/Http/Controllers/ManualFoodRegistrationController.php` com ações finas `store` (`201 Created`) e `show` (`200 OK`) delegando ao `ManualFoodRegistrationService` e retornando `ManualFoodRegistrationResource` (FR-001, FR-008)
- [x] T019 [US1] Registrar as rotas `POST /api/manual-foods` e `GET /api/manual-foods/{food}` em `routes/api.php` (FR-001, FR-010)
- [x] T020 [US1] Executar `php artisan test --compact tests/Feature/ManualFoodRegistrationTest.php` e confirmar que todos os cenários da User Story 1 passam

**Checkpoint**: User Story 1 (MVP) funcional e testada independentemente.

---

## Phase 4: User Story 2 — Controlar duplicidades e disponibilidade imediata na busca e em receitas (Priority: P2)

**Goal**: Bloquear estritamente duplicidades por identidade exata normalizada, disponibilizar imediatamente alimentos manuais ativos na busca (`GET /api/foods`) e em receitas/cálculos, e permitir inativação/reativação lógica sem afetar alimentos TACO ou receitas preexistentes.

**Independent Test**: Validar rejeição de duplicidades por `(source, source_code)` e `(source, name)` contra TACO e catálogo manual; comprovar aparição imediata do alimento manual ativo em `GET /api/foods?q=...` e aceitação em `POST /api/recipes` + `POST /api/recipes/{recipe}/nutrition-calculations`; e comprovar que após `PATCH /api/manual-foods/{food}/status` (`is_active = false`) o item desaparece da busca e é bloqueado em novas receitas sem quebrar receitas existentes.

### Tests for User Story 2

- [x] T021 [P] [US2] Estender `tests/Feature/ManualFoodRegistrationTest.php` com testes inicialmente falhos para bloqueio estrito de duplicidade por `(source, source_code)` (`UPPER(BTRIM(...))`) e por `(source, name)` (`UPPER(BTRIM(source))` + `unaccent(LOWER(BTRIM(name)))`) contra alimentos TACO e manuais, disponibilidade imediata do alimento manual ativo em `GET /api/foods?q=...` (preservando o contrato exato `['category', 'id', 'name', 'nutrients', 'source', 'source_code']` da Feature 001), uso imediato em `POST /api/recipes` e `POST /api/recipes/{recipe}/nutrition-calculations`, inativação/reativação via `PATCH /api/manual-foods/{food}/status` (`404` para TACO), omissão de inativos em `GET /api/foods?q=...`, bloqueio de inativos em `POST /api/recipes` e na adição de novos ingredientes em `PUT /api/recipes/{recipe}`, e permissão de manter ingrediente manual inativo já pertencente à receita durante `PUT /api/recipes/{recipe}` (FR-006, FR-007, FR-010; SC-002, SC-004)

### Implementation for User Story 2

- [x] T022 [US2] Implementar em `app/Services/ManualFoodRegistrationService.php` a verificação transacional de duplicidade normalizada (`UPPER(BTRIM(source)) + UPPER(BTRIM(source_code))` e `UPPER(BTRIM(source)) + unaccent(LOWER(BTRIM(name)))`) lançando `ValidationException` no campo correspondente antes da escrita, além do método `updateStatus(Food $food, bool $isActive): ManualFoodRegistration` (lançando `ModelNotFoundException` para alimentos TACO ou inexistentes) (FR-001, FR-006, FR-007; SC-002, SC-004)
- [x] T023 [P] [US2] Criar `app/Http/Requests/UpdateManualFoodStatusRequest.php` validando `is_active` (`required`, `boolean`), adicionar a ação `updateStatus` em `app/Http/Controllers/ManualFoodRegistrationController.php` e registrar a rota `PATCH /api/manual-foods/{food}/status` em `routes/api.php` (FR-007; SC-004)
- [x] T024 [US2] Atualizar `app/Services/NutritionBaseService.php` no método `search(string $query)` para excluir apenas alimentos vinculados a `manual_food_registrations` com `is_active = false` (`whereDoesntHave('manualRegistration', fn ($q) => $q->where('is_active', false))`), mantendo integralmente os alimentos TACO e o formato de saída da Feature 001 (FR-007, FR-010; SC-004)
- [x] T025 [US2] Atualizar `app/Http/Requests/StoreRecipeRequest.php` e `app/Http/Requests/UpdateRecipeRequest.php` para rejeitar `ingredients.*.food_id` correspondente a alimento manual com `is_active = false` (em `UpdateRecipeRequest`, permitindo manter um `food_id` inativo somente se já pertencer à receita sendo editada) (FR-007, FR-009; SC-002, SC-004)
- [x] T026 [US2] Executar `php artisan test --compact tests/Feature/ManualFoodRegistrationTest.php tests/Feature/FoodSearchTest.php tests/Feature/RecipeManagementTest.php tests/Feature/RecipePersistenceTest.php` e confirmar User Story 2 com zero regressão nas Features 001 e 002

**Checkpoint**: User Stories 1 e 2 funcionais e testáveis independentemente.

---

## Phase 5: User Story 3 — Versionar correções futuras preservando cálculos e snapshots históricos (Priority: P3)

**Goal**: Permitir atualizar alimentos manuais gerando uma nova versão imutável (`version_number + 1`) com justificativa obrigatória (`change_reason`), atualizando apenas a projeção vigente para novos cálculos sem alterar nenhuma versão anterior nem qualquer cálculo histórico já produzido.

**Independent Test**: Criar alimento manual (`v1`), gerar cálculo nutricional na Feature 003, atualizar o alimento via `PUT /api/manual-foods/{food}` (`v2`), confirmar em `GET /api/manual-foods/{food}` a preservação de `v1` e `v2`, verificar que o `GET /api/nutrition-calculations/{id}` anterior permanece inalterado e confirmar que uma nova execução de cálculo reflete `v2`.

### Tests for User Story 3

- [x] T027 [P] [US3] Criar testes inicialmente falhos em `tests/Feature/ManualFoodVersioningHistoryTest.php` cobrindo `PUT /api/manual-foods/{food}`: exigência de `change_reason` não vazio após trim, incremento de `current_version_number`, preservação imutável de todas as versões anteriores e seus nutrientes em `GET /api/manual-foods/{food}`, permissão de manter a própria identidade ao atualizar junto com bloqueio de colisão com outros alimentos, bloqueio `404` para alimentos TACO, execução de `NutritionCalculation` antes e depois da atualização comprovando que o cálculo histórico anterior permanece 100% idêntico enquanto o novo cálculo usa a nova versão, e preservação de cálculos históricos após inativação do alimento manual (FR-008, FR-009; SC-001, SC-002)

### Implementation for User Story 3

- [x] T028 [P] [US3] Criar `app/Http/Requests/UpdateManualFoodRequest.php` reutilizando as regras de `StoreManualFoodRequest` e exigindo `change_reason` (`required`, `string`) não vazio após trim (FR-008)
- [x] T029 [US3] Implementar `update(Food $food, array $data): ManualFoodRegistration` em `app/Services/ManualFoodRegistrationService.php` sob transação `REPEATABLE READ`, exigindo `manualRegistration` existente, validando duplicidade normalizada excluindo `$food->id`, incrementando `current_version_number`, inserindo nova linha imutável em `manual_food_versions` e `manual_food_version_nutrients` e sincronizando a projeção vigente em `foods` e `food_nutrients` sem modificar snapshots históricos (FR-008, FR-009; SC-001, SC-002)
- [x] T030 [US3] Adicionar a ação `update` em `app/Http/Controllers/ManualFoodRegistrationController.php` e registrar a rota `PUT /api/manual-foods/{food}` em `routes/api.php` (FR-008, FR-009)
- [x] T031 [US3] Executar `php artisan test --compact tests/Feature/ManualFoodVersioningHistoryTest.php tests/Feature/NutritionCalculationTest.php tests/Feature/NutritionCalculationPersistenceTest.php` e confirmar User Story 3 e preservação histórica integral

**Checkpoint**: Todas as User Stories (`US1`, `US2`, `US3`) funcionais e verificadas independentemente.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Verificação final de estilo, integridade Git e regressão completa end-to-end.

- [x] T032 [P] Executar `vendor/bin/pint --format agent` e `git diff --check` para validar estilo PHP e ausência de erros de formatação ou espaçamento
- [x] T033 Executar a suíte completa de testes do repositório (`php artisan test --compact`) validando todos os fluxos descritos em `specs/006-manual-food-registration/quickstart.md` e regressão zero nas Features 001–004 (SC-001–SC-004)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: Sem dependências — inicia imediatamente (`T001`, `T002`).
- **Foundational (Phase 2)**: Depende da Phase 1 — **BLOQUEIA** todas as User Stories até a conclusão de `T013`.
- **User Stories (Phases 3–5)**: Dependem da conclusão da Phase 2 (`T013`).
  - **User Story 1 (P1)**: Independente após `T013`; entrega o MVP completo de cadastro e consulta governados.
  - **User Story 2 (P2)**: Depende de `ManualFoodRegistrationService` e `ManualFoodRegistrationController` criados na `US1` (`T016`, `T018`, `T019`) para estender busca, duplicidade e status.
  - **User Story 3 (P3)**: Depende de `ManualFoodRegistrationService` e `ManualFoodRegistrationController` criados na `US1` (`T016`, `T018`, `T019`).
- **Polish (Phase 6)**: Depende da conclusão de todas as User Stories (`T031`).

### Within Each User Story

- Testes inicialmente falhos (`T003`, `T014`, `T021`, `T027`) DEVEM ser criados antes da implementação correspondente.
- Migrations e Models antes de Requests/Services.
- Requests e Services antes de Controllers e rotas em `routes/api.php`.
- Checkpoint de testes ao final de cada fase.

### Parallel Opportunities

- Na Phase 1: `T001` e `T002` em paralelo.
- Na Phase 2: `T009`, `T010`, `T011` e `T012` (Models distintos) em paralelo após as migrations.
- Na Phase 4: `T021` (testes) e `T023` (Form Request de status) em paralelo antes da conclusão dos ajustes em `NutritionBaseService` e `RecipeRequest`s.
- Na Phase 5: `T027` (testes) e `T028` (`UpdateManualFoodRequest`) em paralelo.
- Na Phase 6: `T032` pode rodar antes ou em paralelo à preparação de `T033` (reexecutando `T033` após qualquer ajuste de formatação).

---

## Parallel Example: Foundational Models (Phase 2)

```text
# Após finalizar as migrations T004–T008, criar/atualizar os 4 Models em paralelo:
T009 [P] Atualizar app/Models/Food.php
T010 [P] Criar app/Models/ManualFoodRegistration.php
T011 [P] Criar app/Models/ManualFoodVersion.php
T012 [P] Criar app/Models/ManualFoodVersionNutrient.php
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Concluir Phase 1: Setup (`T001`–`T002`).
2. Concluir Phase 2: Foundational (`T003`–`T013`) — schema PostgreSQL, proteção TACO e triggers de imutabilidade.
3. Concluir Phase 3: User Story 1 (`T014`–`T020`).
4. **STOP and VALIDATE**: Confirmar que `ManualFoodPersistenceTest` e `ManualFoodRegistrationTest` (US1) passam integralmente.

### Incremental Delivery

1. Setup + Foundational → base imutável pronta (`T001`–`T013`).
2. Adicionar User Story 1 → cadastro e consulta governados entregues (`T014`–`T020`).
3. Adicionar User Story 2 → duplicidades, busca integrada, receitas/cálculos e inativação lógica entregues (`T021`–`T026`).
4. Adicionar User Story 3 → versionamento imutável por atualização e prova de imutabilidade histórica entregues (`T027`–`T031`).
5. Finalizar com Pint, `git diff --check` e suíte completa (`T032`–`T033`).

---

## Notes

- Tarefas `[P]` atuam em arquivos distintos e não dependem de tarefas incompletas da mesma fase.
- Rótulos `[US1]`, `[US2]` e `[US3]` rastreiam diretamente cada tarefa às User Stories de `spec.md`.
- Nenhum código de aplicação, migration ou teste deve ser criado fora das fases planejadas; antes de executar `/speckit-implement`, recomenda-se rodar `/speckit-analyze` para validar consistência cruzada entre `spec.md`, `plan.md`, `data-model.md`, `contracts/` e `tasks.md`.
