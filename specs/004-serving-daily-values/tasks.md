# Tasks: Serving and Daily Values (004-serving-daily-values)

**Input**: artefatos em `specs/004-serving-daily-values/`

**Prerequisites**: `spec.md`, `plan.md`, `research.md`, `data-model.md`, `contracts/serving-daily-values.md`, `quickstart.md`, `regulatory-decision-register.md`

**Tests**: obrigatórios; cada User Story começa pelos testes inicialmente falhos correspondentes.
**Organization**: as tasks seguem as User Stories e mantêm os gates técnicos de MAP, ENE, VDR, fontes, unidades, conversões, aplicabilidade e dupla contagem em modo deny-by-default.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode ser executada em paralelo porque atua em arquivo distinto e não depende de trabalho incompleto da mesma fase.
- **[US1]–[US4]**: vincula a task à User Story correspondente.
- Cada task referencia o arquivo exato a criar ou alterar e os requisitos/critérios verificáveis que cobre.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: confirmar a base técnica existente sem alterar stack, dependências ou credenciais.

- [x] T001 Confirmar em `composer.json`, `composer.lock` e `phpunit.xml` que o projeto permanece em Laravel 13/PHP 8.4, usa PostgreSQL nos testes e já declara `brick/math` diretamente na versão resolvida, executando `composer show --direct`, `composer show brick/math` e a suíte baseline sem atualizar dependências ou versionar credenciais (FR-012, SC-007)
- [x] T002 Inspecionar `app/Services/NutritionCalculationArithmetic.php`, `app/Services/NutritionCalculationService.php`, `app/Http/Controllers/NutritionCalculationController.php`, `app/Http/Resources/NutritionCalculationResource.php` e `routes/api.php` e confirmar o padrão existente Controller → Service → Model, razões exatas, snapshots imutáveis e APIs sem autenticação adicionada por esta feature (FR-001–FR-003, FR-023)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: criar o schema PostgreSQL, Models e proteção imutável compartilhados por todas as User Stories.

**⚠️ CRITICAL**: nenhuma User Story começa antes de T025 passar integralmente.

### Structural tests

- [x] T003 Criar testes PostgreSQL inicialmente falhos em `tests/Feature/ServingDailyValuePersistenceTest.php` para todas as tabelas planejadas, FKs internas, ausência de cascades destrutivos para a Feature 003, unicidades, estados permitidos, pares racionais nullable, numeradores/denominadores inteiros, denominadores positivos, zero canônico `0/1`, valores `NUMERIC` finitos, tuplas de evidência completas ou integralmente nulas, exigência de evidência para estados autorizados, autorização própria de conversão, timestamps compatíveis com os treze Models e ausência de `float`, `double` ou escala fixa (FR-012, FR-019, FR-020; SC-003, SC-007, SC-008)

### Regulatory reference migrations

- [x] T004 Criar `database/migrations/2026_11_10_000001_create_regulatory_profiles_table.php` conforme `data-model.md`, com identidade única `code + version`, fonte/versão/data normativa, estado técnico `AUTHORIZED|BLOCKED`, tupla `evidence_*` completa quando autorizado, checks all-or-none e somente `created_at`, sem `updated_at` nem promoção automática do perfil documentado (FR-014, FR-016, FR-019)
- [x] T005 Criar `database/migrations/2026_11_10_000002_create_regulatory_nutrient_mappings_table.php` sem timestamps, com uma linha por MAP e perfil, FK opcional para `nutrients.id`, unidades, estado `AUTHORIZED|BLOCKED|UNAVAILABLE`, fonte e tupla `evidence_*`; adicionar `conversion_status=NOT_REQUIRED|AUTHORIZED|BLOCKED`, razão/unidades e tupla `conversion_evidence_*` próprias, exigindo evidência de compatibilidade para `NOT_REQUIRED` e fator/unidades/evidência para `AUTHORIZED`, sem considerar igualdade textual de unidade como equivalência nutricional (FR-016, FR-017; SC-006, SC-011)
- [x] T006 Criar `database/migrations/2026_11_10_000003_create_regulatory_energy_components_table.php` sem timestamps, com uma linha por ENE e perfil, MAP opcional, fator racional/unidades/fonte, estado `AUTHORIZED|BLOCKED|UNAVAILABLE`, tupla `evidence_*` e metadados de sobreposição, exigindo evidência para linha autorizada e bloqueando fator não autorizado ou combinação sobreposta sem regra `AUTHORIZED` (FR-014, FR-015; SC-005, SC-010)
- [x] T007 Criar `database/migrations/2026_11_10_000004_create_regulatory_daily_values_table.php` sem timestamps, com uma linha por VDR e perfil, MAP opcional, razão/unidade/fonte, tupla `evidence_*` e estados `AUTHORIZED|BLOCKED|NO_NORMATIVE_VDR|UNAVAILABLE`, exigindo razão/unidade/evidência para `AUTHORIZED` e evidência normativa da ausência para `NO_NORMATIVE_VDR`, sem permitir que VDR autorize MAP, numerador ou conversão (FR-016, FR-017; SC-006)
- [x] T008 Criar `database/migrations/2026_11_10_000005_create_portion_authorizations_table.php` com vínculo à execução nutricional, referência versionada, estado `AUTHORIZED|BLOCKED`, porção positiva/finita em `g|ml`, modos `PROVIDED|WAIVED|NOT_APPLICABLE`, checks condicionais de medida/justificativa, tupla `evidence_*` vinculável ao produto/formulação, somente `created_at` e ausência de `updated_at` (FR-004, FR-007, FR-008; SC-001, SC-008)
- [x] T009 Criar `database/migrations/2026_11_10_000006_create_regulatory_energy_applicabilities_table.php` sem timestamps, com vínculo único entre execução nutricional, perfil e ENE, estados `APPLICABLE|NOT_APPLICABLE|UNKNOWN` e tupla `evidence_*`; exigir evidência completa para `APPLICABLE`, justificativa e evidência para `NOT_APPLICABLE`, mantendo `UNKNOWN` e ausência de registro como bloqueio (FR-014, FR-015, FR-019; SC-009)

### Historical aggregate migrations

- [x] T010 Criar `database/migrations/2026_11_10_000007_create_serving_daily_value_calculations_table.php` com única origem imutável, snapshots de produto/formulação, receita/rendimento, porção, medida ou justificativa de dispensa, referência/evidência da porção, perfil, fonte/versão/data normativa e evidência do perfil, razão energética nullable, completude/disponibilidade, `rules_version = serving-daily-values-v1`, somente `created_at`, ausência de `updated_at` e sem unique sobre a origem; registrar a migration corretiva `database/migrations/2026_11_11_164338_add_portion_reference_technical_status_to_serving_daily_value_calculations_table.php` para preservar no snapshot histórico o estado técnico `AUTHORIZED|BLOCKED` da referência de porção (FR-001, FR-019, FR-020; SC-008)
- [x] T011 Criar `database/migrations/2026_11_10_000008_create_serving_daily_value_nutrients_table.php` e `database/migrations/2026_11_10_000009_create_serving_daily_value_nutrient_states_table.php` sem timestamps, com uma linha por nutriente da origem, snapshots e razões nullable, completude/ausência separadas, estados somente `TRACE|NA|ABSENT|REEVALUATION`, unicidades aprovadas e proibição de representar `EXACT` ou ausência de relação como estado qualitativo (FR-005, FR-009–FR-012; SC-001, SC-003, SC-004)
- [x] T012 Criar `database/migrations/2026_11_10_000010_create_serving_daily_value_regulatory_results_table.php` sem timestamps, com uma linha por MAP considerada, inclusive bloqueada ou sem fonte, snapshot de proveniência/estado/evidência MAP, numerador por porção nullable, `conversion_status`, unidades, fator e evidência própria da conversão, completude e disponibilidade `AVAILABLE|PARTIAL|UNAVAILABLE` (FR-016, FR-017, FR-019; SC-006, SC-011)
- [x] T013 Criar `database/migrations/2026_11_10_000011_create_serving_daily_value_energy_components_table.php` sem timestamps, com uma linha por ENE considerada, aplicabilidade, participação `INCLUDED|NON_APPLICABLE|BLOCKED`, fator/entrada/contribuição racionais nullable, fontes/versões/evidência ENE, regra de sobreposição e evidência de aplicabilidade, copiando justificativa e evidência completas de não aplicabilidade (FR-014, FR-015, FR-019; SC-005, SC-009, SC-010)
- [x] T014 Criar `database/migrations/2026_11_10_000012_create_serving_daily_value_percentages_table.php` sem timestamps, com uma linha por VDR considerada, snapshot de VDR/MAP, fonte/versão/unidade/estado/evidência VDR, identificação e razão do numerador efetivamente usado, razão de `%VD` nullable, completude e disponibilidade `AVAILABLE|PARTIAL|UNAVAILABLE|NO_NORMATIVE_VDR` (FR-016, FR-017, FR-019; SC-006)
- [x] T015 Criar `database/migrations/2026_11_10_000013_create_serving_daily_value_result_causes_table.php` sem timestamps, com ledger separado dos estados nutricionais, escopos `REGULATORY_VALUE|ENERGY|DAILY_VALUE`, chave de escopo e categorias/códigos definidos em `data-model.md`, garantindo unicidade por execução/escopo/chave/código (FR-015, FR-017, FR-019; SC-005, SC-006, SC-011)
- [x] T016 Criar `database/migrations/2026_11_10_000014_protect_serving_daily_value_data_from_mutation.php` com triggers PostgreSQL reversíveis que bloqueiem `UPDATE` e `DELETE` em todas as seis tabelas de referência/aplicabilidade e nas sete tabelas do agregado histórico, preservando `created_at` apenas nas três raízes definidas e sem alterar as tabelas das Features 001–003 (FR-019, FR-020; SC-008)

### Models

- [x] T017 [P] Criar `app/Models/RegulatoryProfile.php` com `public const UPDATED_AT = null;` e `app/Models/RegulatoryNutrientMapping.php` com `public $timestamps = false;`, incluindo relacionamentos, estados, tuplas de evidência, autorização própria da conversão e razões como strings, sem promoção automática ou cast inexato (FR-014, FR-016, FR-019)
- [x] T018 [P] Criar `app/Models/RegulatoryEnergyComponent.php` e `app/Models/RegulatoryDailyValue.php` com `public $timestamps = false;`, relacionamentos internos, estados/evidências aprovados e fatores/VDR como strings racionais, sem lógica de gate ou cálculo nos Models (FR-014–FR-017)
- [x] T019 [P] Criar `app/Models/PortionAuthorization.php` com `public const UPDATED_AT = null;` e `app/Models/RegulatoryEnergyApplicability.php` com `public $timestamps = false;`, incluindo evidências, escopos e relacionamentos à execução/perfil/componentes, sem inferência de aprovação, medida ou não aplicabilidade (FR-004, FR-008, FR-014, FR-019)
- [x] T020 Criar `app/Models/ServingDailyValueCalculation.php` com `public const UPDATED_AT = null`, `rules_version`, snapshots completos de porção/dispensa/perfil/evidências, razões como strings e relacionamentos somente para o agregado snapshot, sem operações funcionais de update/delete nem reconstrução por entidades atuais (FR-019, FR-020; SC-008)
- [x] T021 [P] Criar `app/Models/ServingDailyValueNutrient.php` e `app/Models/ServingDailyValueNutrientState.php` com `$timestamps = false`, razões como strings, relacionamentos internos e dimensões separadas de número, completude, estados e ausência de relação (FR-009–FR-012; SC-003, SC-004)
- [x] T022 [P] Criar `app/Models/ServingDailyValueRegulatoryResult.php` com `$timestamps = false`, snapshots completos de proveniência/evidência MAP e autorização/evidência própria da conversão, razões como strings e disponibilidade independente, sem equivalências nutricionais ou conversões inferidas (FR-016, FR-017, FR-019)
- [x] T023 [P] Criar `app/Models/ServingDailyValueEnergyComponent.php` com `$timestamps = false`, snapshots completos ENE/aplicabilidade/evidências/sobreposição e razões como strings, sem aplicar fatores dentro do Model (FR-014, FR-015, FR-019)
- [x] T024 [P] Criar `app/Models/ServingDailyValuePercentage.php` e `app/Models/ServingDailyValueResultCause.php` com `$timestamps = false`, snapshots VDR/evidência/numerador e causas estruturadas, razões como strings e distinção entre `NO_NORMATIVE_VDR`, indisponibilidade regulatória e incompletude nutricional (FR-016, FR-017, FR-019)

### Foundation checkpoint

- [x] T025 Executar `php artisan migrate`, `php artisan migrate:status` e `php artisan test --compact tests/Feature/ServingDailyValuePersistenceTest.php` com PostgreSQL e confirmar constraints, FKs, unicidades, razões, evidências, criação/consulta dos treze Models sem colunas de timestamp inexistentes e bloqueio de `UPDATE`/`DELETE` de T003–T024 antes das User Stories

---

## Phase 3: User Story 1 — Obter valores nutricionais por porção (Priority: P1) 🎯 MVP

**Goal**: criar uma execução derivada para uma porção autorizada e calcular exatamente todos os nutrientes da origem, preservando zero, `null`, estados e incompletude.

**Independent Test**: com uma `NutritionCalculation` imutável e autorização de porção controlada, o POST cria nova execução e cada nutriente corresponde a `valor da origem × porção ÷ rendimento`, sem arredondar nem alterar a origem; resultados regulatórios bloqueados permanecem indisponíveis.

### Tests for User Story 1

- [x] T026 [P] [US1] Criar testes unitários inicialmente falhos em `tests/Unit/ServingDailyValueArithmeticTest.php` para multiplicação e divisão racional usando strings, frações reduzidas, decimais terminados, dízimas, valores muito precisos, zero canônico `0/1`, pares nullable e ausência de `float`, `double`, truncamento, escala fixa ou arredondamento (FR-005, FR-012; SC-001, SC-003, SC-007)
- [x] T027 [P] [US1] Criar testes inicialmente falhos em `tests/Feature/ServingDailyValueCalculationTest.php` para `POST /api/nutrition-calculations/{nutritionCalculation}/serving-daily-value-calculations`, cobrindo valor completo, zero `EXACT`, subtotal incompleto, ausência numérica, cada estado qualitativo, ausência de relação, todos os nutrientes sem omissão, porção fracionária, normalização HTTP, referência/porção bloqueada ou divergente, evidência ausente ou com escopo incompatível, `TEST_FIXTURE` fora de testing, unidade incompatível, medida obrigatória/dispensada, rollback e nova execução por chamada; medir consultas e falhar diante de N+1 na carga da origem/referências, confirmando também o padrão atual sem autenticação nova (FR-001–FR-012, FR-019, FR-020, FR-023; SC-001–SC-004, SC-007, SC-008, SC-011)

### Implementation for User Story 1

- [x] T028 [US1] Criar `app/Http/Requests/StoreServingDailyValueCalculationRequest.php` para validar strings decimais positivas, `g|ml`, medida nullable pós-`TrimStrings`/`ConvertEmptyStringsToNull` e IDs existentes, sem converter para float, decidir autorização técnica ou aceitar estado de aprovação fornecido pelo cliente (FR-004, FR-007, FR-008, FR-012)
- [x] T029 [US1] Estender `app/Services/NutritionCalculationArithmetic.php` somente com operações racionais genéricas necessárias a `valor × porção ÷ rendimento`, preservando a API da Feature 003, razões reduzidas e zero `0/1`, sem arredondamento ou casts inexatos (FR-005, FR-012; SC-001, SC-003, SC-007)
- [x] T030 [US1] Criar `app/Services/ServingRegulatoryGateService.php` com gate deny-by-default da autorização de porção: exigir vínculo à mesma `NutritionCalculation`, estado `AUTHORIZED`, tupla de evidência completa, escopo produto/formulação compatível, ambiente permitido, valor/unidade/medida idênticos à referência e compatibilidade com o rendimento, rejeitando antes da persistência sem inferir peso, densidade, conversão ou aprovação (FR-004, FR-006–FR-008; SC-001, SC-008)
- [x] T031 [US1] Criar `app/Services/ServingDailyValueCalculationService.php` para abrir transação PostgreSQL `REPEATABLE READ`, carregar de forma agrupada e sem N+1 exclusivamente o snapshot da Feature 003, autorização de porção e perfil, calcular todos os valores por porção, copiar estados/ausência/completude e snapshots completos de porção/dispensa/perfil/evidências, persistir raiz/nutrientes/estados com `rules_version = serving-daily-values-v1` e rollback integral, sem recalcular receita nem modificar funcionalidade/schema das Features 001–003 (FR-001–FR-012, FR-019, FR-020; SC-001–SC-004, SC-007, SC-008)
- [x] T032 [US1] Criar `app/Http/Resources/ServingDailyValueCalculationResource.php` para serializar o snapshot conforme `contracts/serving-daily-values.md`, incluindo proveniência/evidência da porção e do perfil, razões em strings, dimensões separadas, ordenação determinística e ausência de cálculo, arredondamento ou classificação de aptidão à rotulagem (FR-009–FR-012, FR-018, FR-019; SC-004, SC-007, SC-012)
- [x] T033 [US1] Criar `app/Http/Controllers/ServingDailyValueCalculationController.php` com ação de criação fina que resolve a origem, recebe o Form Request, delega ao Service e retorna o Resource, sem regra de domínio, gate ou aritmética no Controller (FR-001, FR-019)
- [x] T034 [US1] Registrar somente a rota de criação `POST /api/nutrition-calculations/{nutritionCalculation}/serving-daily-value-calculations` em `routes/api.php`, seguindo o padrão API existente e sem adicionar autenticação, listagem, atualização ou exclusão (FR-019, FR-023)
- [x] T035 [US1] Executar `php artisan test --compact tests/Unit/ServingDailyValueArithmeticTest.php tests/Feature/ServingDailyValueCalculationTest.php tests/Feature/ServingDailyValuePersistenceTest.php` e confirmar a User Story 1 sem liberar MAP, ENE ou VDR bloqueados

---

## Phase 4: User Story 2 — Obter energia derivada exata por fatores regulatórios (Priority: P2)

**Goal**: produzir energia somente após todos os gates estruturais da matriz ENE e preservar subtotal parcial apenas quando a matriz inteira está autorizada e a incompletude é nutricional.

**Independent Test**: fixtures controladas com matriz completa/autorizada calculam exatamente as contribuições e a soma; matriz incompleta, bloqueada, desconhecida ou sobreposta mantém energia e subtotal `null` com causas explícitas.

### Tests for User Story 2

- [x] T036 [P] [US2] Criar testes inicialmente falhos em `tests/Feature/ServingDailyValueRegulatoryGateTest.php` para status autorizado sem evidência, evidência sem status, escopo incompatível, `TEST_FIXTURE` fora de testing, perfil autorizado com MAP/ENE dependente bloqueada, MAP autorizada/bloqueada/sem fonte, matriz ENE completa/incompleta, fator ou unidade bloqueados, aplicabilidade `APPLICABLE|NOT_APPLICABLE|UNKNOWN`, ausência de linha, justificativa/evidência completas ou incompletas, formulação nutricional incompleta, estados qualitativos, nenhum número, dupla contagem e preservação dos resultados independentes (FR-013–FR-015, FR-019; SC-004, SC-005, SC-009–SC-011)
- [x] T037 [P] [US2] Estender `tests/Unit/ServingDailyValueArithmeticTest.php` com testes inicialmente falhos para contribuição `numerador regulatório × fator`, soma exata de componentes, zero, subtotal parcial e dízimas, sem calcular quando o gate estrutural falhar (FR-012, FR-015; SC-005, SC-007)

### Implementation for User Story 2

- [x] T038 [US2] Implementar em `app/Services/ServingRegulatoryGateService.php` os gates MAP/ENE deny-by-default: status e evidência completos, origem/versão/responsável/data, escopo produto/formulação, ambiente, fonte quantitativa, identidade nutricional, unidade/grandeza, aplicabilidade evidenciada, prova de não aplicabilidade, fator, matriz completa e regra contra sobreposição; perfil autorizado não promove dependências, `TEST_FIXTURE` só vale em testing e o perfil real permanece bloqueado sem seed inventado (FR-013–FR-015; SC-005, SC-009–SC-011)
- [x] T039 [US2] Implementar em `app/Services/ServingDailyValueCalculationService.php` a criação dos snapshots completos MAP, conversão, ENE, aplicabilidade e causas: copiar proveniência/evidências utilizadas; linha bloqueada fica sem numerador/contribuição; `NOT_APPLICABLE` comprovado não vira dado faltante; `UNKNOWN`, fonte ausente, conversão sem autorização/evidência própria ou risco de dupla contagem bloqueiam a energia inteira sem subtotal (FR-014, FR-015, FR-019; SC-005, SC-009–SC-011)
- [x] T040 [US2] Implementar em `app/Services/ServingDailyValueCalculationService.php` o cálculo racional da energia após o gate completo: dados completos geram energia completa; incompletude exclusivamente nutricional soma contribuições numéricas autorizadas como subtotal `PARTIAL`; nenhum número gera `null`; energia TACO permanece separada (FR-013–FR-015, FR-018; SC-004, SC-005, SC-007)
- [x] T041 [US2] Completar `app/Http/Resources/ServingDailyValueCalculationResource.php` com `regulatory_results`, `energy.components`, proveniência/evidência MAP, autorização/evidência de conversão, fatores/fontes/evidências ENE, aplicabilidade/evidência, completude, disponibilidade e causas conforme contrato, sem chamar gates nem reclassificar subtotal como energia final (FR-013–FR-015, FR-018, FR-019; SC-005, SC-012)
- [x] T042 [US2] Executar `php artisan test --compact tests/Unit/ServingDailyValueArithmeticTest.php tests/Feature/ServingDailyValueRegulatoryGateTest.php tests/Feature/ServingDailyValueCalculationTest.php` e confirmar energia autorizada, bloqueios estruturais, subtotal nutricional e ausência de dupla contagem

---

## Phase 5: User Story 3 — Obter percentuais de valores diários (Priority: P3)

**Goal**: calcular `%VD` exato por linha somente quando MAP, numerador, VDR, unidade e eventual conversão estiverem autorizados.

**Independent Test**: uma linha VDR controlada e totalmente autorizada produz `numerador × 100 ÷ VDR`; cada gate ausente mantém somente o percentual dependente `null`, sem bloquear resultados independentes.

### Tests for User Story 3

- [x] T043 [P] [US3] Estender `tests/Feature/ServingDailyValueRegulatoryGateTest.php` com testes inicialmente falhos para VDR autorizado com evidência, `NO_NORMATIVE_VDR`, bloqueado ou indisponível; MAP sem nutriente/numerador; valor completo/parcial/null/zero; identidade/unidade/grandeza compatíveis ou incompatíveis; `NOT_REQUIRED` conceitualmente inválido; conversão autorizada com fator/evidência próprios ou bloqueada; evidência VDR/MAP/conversão ausente ou incompatível; energia sem matriz; causas distintas e continuidade de outros resultados (FR-016, FR-017, FR-019; SC-003, SC-004, SC-006, SC-011)
- [x] T044 [P] [US3] Estender `tests/Unit/ServingDailyValueArithmeticTest.php` com testes inicialmente falhos para `%VD = numerador regulatório × 100 ÷ VDR`, fração reduzida, zero completo, subtotal parcial e divisão não terminada, sem arredondamento ou cálculo quando o numerador for `null` (FR-012, FR-016–FR-018; SC-006, SC-007)

### Implementation for User Story 3

- [x] T045 [US3] Implementar em `app/Services/ServingRegulatoryGateService.php` os gates independentes por VDR: MAP/numerador/VDR com status e evidência compatíveis, identidade nutricional, unidade e grandeza autorizadas; `NOT_REQUIRED` somente com compatibilidade comprovada e conversão `AUTHORIZED` somente com fator/evidência próprios; distinguir ausência normativa, bloqueio, fonte ausente e incompatibilidades sem inventar equivalências como mg versus mg NE ou folato versus DFE (FR-016, FR-017; SC-006, SC-011)
- [x] T046 [US3] Implementar em `app/Services/ServingDailyValueCalculationService.php` snapshots completos VDR/evidência/numerador e `%VD` racional: completo, parcial com causas herdadas, zero completo, `null`, `NO_NORMATIVE_VDR` e indisponibilidade regulatória, sem usar energia TACO nem percentual derivado como valor final de rótulo (FR-016–FR-019; SC-003, SC-004, SC-006, SC-007)
- [x] T047 [US3] Completar `app/Http/Resources/ServingDailyValueCalculationResource.php` com `daily_values` determinísticos, fonte/versão/unidade/estado/evidência VDR, numerador efetivamente usado e todos os campos de disponibilidade/causas previstos no contrato, mantendo `%VD` exato distinto do percentual final de rotulagem (FR-016–FR-019; SC-006, SC-012)
- [x] T048 [US3] Executar `php artisan test --compact tests/Unit/ServingDailyValueArithmeticTest.php tests/Feature/ServingDailyValueRegulatoryGateTest.php tests/Feature/ServingDailyValueCalculationTest.php` e confirmar todos os caminhos de VDR, numerador, unidade, conversão, zero, parcial e `null`

---

## Phase 6: User Story 4 — Manter rastreabilidade do resultado derivado (Priority: P4)

**Goal**: consultar execuções históricas somente por seus snapshots, impedir mutação e criar nova execução a cada solicitação.

**Independent Test**: após criar e registrar a resposta, mudanças nas entidades atuais e novas versões de referências não alteram o GET histórico; novo POST cria outro ID e falha intermediária não deixa agregado parcial.

### Tests for User Story 4

- [x] T049 [P] [US4] Criar testes inicialmente falhos em `tests/Feature/ServingDailyValueHistoricalSnapshotTest.php` que preservem uma resposta, alterem depois Recipe/ingredientes/Base quando permitido pela transação de teste, criem novas versões de referências e autorizações e confirmem GET histórico idêntico, baseado somente nos snapshots, incluindo produto/formulação, porção/dispensa, fontes, versões normativas, datas de consulta, evidências/escopos, proveniência MAP, conversão, ENE/aplicabilidade, VDR/numerador, estados, causas e `rules_version`; confirmar também que autorizações posteriores não promovem o histórico (FR-001–FR-003, FR-019, FR-020; SC-002, SC-008, SC-009)
- [x] T050 [P] [US4] Estender `tests/Feature/ServingDailyValuePersistenceTest.php` com testes inicialmente falhos para bloqueio PostgreSQL de `UPDATE`/`DELETE` em todas as tabelas protegidas, transação `REPEATABLE READ`, rollback integral, uma única origem, nenhuma cascata destrutiva e ausência de agregado parcial (FR-019, FR-020; SC-008)
- [x] T051 [P] [US4] Estender `tests/Feature/ServingDailyValueCalculationTest.php` com testes inicialmente falhos do contrato `GET /api/serving-daily-value-calculations/{servingDailyValueCalculation}`, inexistente sem reconstrução, ordenação determinística, duas chamadas POST com IDs distintos e nenhuma operação PUT/PATCH/DELETE/listagem (FR-019, FR-023; SC-008, SC-012)

### Implementation for User Story 4

- [x] T052 [US4] Implementar em `app/Services/ServingDailyValueCalculationService.php` a consulta read-only do agregado completo com eager loading determinístico e sem acessar Recipe, Food, Nutrient, NutritionCalculation ou referências regulatórias atuais para reconstruir o histórico (FR-001–FR-003, FR-019, FR-020; SC-002, SC-008)
- [x] T053 [US4] Completar `app/Http/Resources/ServingDailyValueCalculationResource.php` para criação e consulta histórica compartilharem exatamente a mesma representação contratual, incluindo proveniência e evidências originais, e dependerem somente das relações internas snapshotadas (FR-019, FR-020; SC-008)
- [x] T054 [US4] Adicionar ação de consulta em `app/Http/Controllers/ServingDailyValueCalculationController.php` que apenas delega ao Service e retorna o Resource, sem regras, cálculo ou reconstrução (FR-019, FR-020)
- [x] T055 [US4] Registrar somente `GET /api/serving-daily-value-calculations/{servingDailyValueCalculation}` em `routes/api.php`, sem listagem, atualização, exclusão, endpoint administrativo ou endpoint de resultado atual (FR-019, FR-023)
- [x] T056 [US4] Executar `php artisan test --compact tests/Feature/ServingDailyValueHistoricalSnapshotTest.php tests/Feature/ServingDailyValuePersistenceTest.php tests/Feature/ServingDailyValueCalculationTest.php` e confirmar imutabilidade, consulta histórica, execução independente e atomicidade

---

## Phase 7: Polish & Cross-Cutting Validation

**Purpose**: validar regressão, contrato, bloqueios, escopo e documentação sem liberar dados regulatórios não autorizados.

- [x] T057 Executar `php artisan migrate`, `php artisan migrate:status` e `php artisan test` com PostgreSQL, confirmando migrations aplicadas, suíte completa das Features 001–004 passando e nenhuma mutação de migrations já aplicadas
- [x] T058 Executar `vendor/bin/pint --dirty --format agent`, repetir os testes afetados, executar `vendor/bin/pint --test` e `git diff --check`, além de verificar separadamente espaços finais em arquivos não rastreados, corrigindo somente arquivos desta feature
- [x] T059 Executar integralmente `specs/004-serving-daily-values/quickstart.md` com fixtures `TEST_FIXTURE` criadas e consumidas somente no ambiente testing, validando POST/GET, proporção, zero/`null`, estados, evidência composta, porção autorizada, bloqueios MAP/ENE/VDR, energia parcial somente após matriz autorizada, `%VD` independente, criação de novas versões sem mutar referências, rejeição de UPDATE/DELETE, imutabilidade e nova execução
- [x] T060 Auditar `specs/004-serving-daily-values/regulatory-decision-register.md` contra `app/Services/ServingRegulatoryGateService.php`, `app/Services/ServingDailyValueCalculationService.php` e os testes da feature, confirmando que status isolado ou tupla textual não autorizam cálculo, `TEST_FIXTURE` não passa fora de testing, nenhuma linha MAP/ENE/VDR real bloqueada foi promovida, nenhuma fonte/conversão foi inventada e o caso “GELEIA DE AMORA” continua condicionado conforme FR-021/FR-022 (SC-005, SC-006, SC-010, SC-011, SC-013)
- [x] T061 Revisar `app/`, `database/migrations/`, `routes/api.php`, `tests/` e `specs/004-serving-daily-values/contracts/serving-daily-values.md` e confirmar rastreabilidade integral FR-001–FR-023/SC-001–SC-013, ausência de arredondamento regulatório, rotulagem final/frontal, açúcares adicionados sem regra, PDF/PNG, frontend, autenticação, cadastro manual de alimentos, endpoints administrativos, edição/exclusão, jobs, filas ou cache; registrar somente correções documentais estritamente necessárias em `specs/004-serving-daily-values/quickstart.md`

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: sem dependências; confirma a base existente.
- **Phase 2 (Foundational)**: depende de T001–T002; bloqueia todas as User Stories.
- **Phase 3 (US1)**: depende de T025; constitui o MVP independente de valores por porção.
- **Phase 4 (US2)**: depende de T035 porque energia consome os valores por porção e snapshots MAP.
- **Phase 5 (US3)**: depende de T042 porque `%VD` consome numeradores regulatórios e, para energia, o resultado ENE válido.
- **Phase 6 (US4)**: depende de T048 para validar o agregado completo, mas os testes T049–T051 devem ser escritos antes de T052–T055.
- **Phase 7 (Polish)**: depende de T056 e valida a feature completa.

### Foundational dependency graph

```text
T003 (structural tests)
  ↓
T004 RegulatoryProfile
  ├─→ T005 MAP ───────────────┐
  ├─→ T006 ENE ───────────────┤
  ├─→ T007 VDR ───────────────┤
  ├─→ T008 PortionAuthorization
  └─→ T009 Applicability ─────┤
                              ↓
T010 root → T011 nutrients/states → T012 MAP results
         ├────────────────────→ T013 ENE snapshots
         ├────────────────────→ T014 VDR percentages
         └────────────────────→ T015 causes
                              ↓
T016 immutability → T017–T024 Models → T025 checkpoint
```

### User Story dependency graph

```text
US1: porção exata e snapshot base
  ↓
US2: MAP + matriz energética + energia
  ↓
US3: VDR + %VD
  ↓
US4: consulta histórica do agregado completo
  ↓
Polish e validação transversal
```

### Within Each User Story

1. Escrever e confirmar a falha dos testes da story.
2. Implementar aritmética/gates antes da orquestração do Service.
3. Persistir snapshots dentro da mesma transação `REPEATABLE READ`.
4. Serializar no Resource; Controller permanece fino.
5. Registrar somente a rota aprovada.
6. Executar o checkpoint da story antes da próxima fase.

---

## Parallel Opportunities

- T017–T019 e T021–T024 podem ser executadas em paralelo após as migrations correspondentes, pois criam Models distintos.
- T026 e T027 podem ser executadas em paralelo após T025, pois alteram arquivos de teste distintos.
- T036 e T037 podem ser executadas em paralelo após T035.
- T043 e T044 podem ser executadas em paralelo após T042.
- T049–T051 podem ser executadas em paralelo após T048.
- Tasks marcadas `[P]` nunca dispensam seus pré-requisitos explícitos nem autorizam dados regulatórios reais.

## Parallel Example: User Story 2

```text
T036: testes de gates em tests/Feature/ServingDailyValueRegulatoryGateTest.php
T037: testes de aritmética em tests/Unit/ServingDailyValueArithmeticTest.php
                     ↓
T038 → T039 → T040 → T041 → T042
```

## Implementation Strategy

### MVP First — User Story 1

1. Concluir Setup e Foundational.
2. Escrever T026–T027 antes da implementação.
3. Entregar valores por porção exatos e persistidos com todos os resultados regulatórios dependentes bloqueados.
4. Validar T035 antes de implementar energia.

### Incremental Delivery

1. **US1**: proporção, estados, zero/`null`, porção autorizada e criação atômica.
2. **US2**: MAP/ENE, aplicabilidade, prevenção de dupla contagem e energia exata/partial segura.
3. **US3**: VDR e `%VD` exato independente, com gates completos.
4. **US4**: consulta histórica, imutabilidade e novas execuções independentes.
5. **Polish**: regressão, quickstart, escopo e auditoria dos bloqueios reais.

## Requirement and Test Traceability

| Grupo | Implementação principal | Testes correspondentes |
|---|---|---|
| FR-001–FR-003, FR-020 | T031, T052 | T027, T049–T051 |
| FR-004, FR-006–FR-008 | T028, T030 | T027 |
| FR-005, FR-009–FR-012 | T029, T031 | T026–T027 |
| FR-013–FR-015 | T038–T041 | T036–T037 |
| FR-016–FR-017 | T045–T047 | T043–T044 |
| FR-018 | T029, T032, T040, T046 | T026, T037, T044 |
| FR-019 | T004–T024, T031, T039, T046, T052–T055 | T003, T027, T036, T043, T049–T051 |
| FR-021–FR-023 | T034, T055, T060–T061 | T027, T051 e suíte de regressão |
| SC-001–SC-004, SC-007 | T029–T032 | T026–T027 |
| SC-005, SC-009–SC-011 | T038–T041 | T036–T037 |
| SC-006 | T045–T047 | T043–T044 |
| SC-008 | T010–T024, T031, T052–T055 | T003, T049–T051 |
| SC-012–SC-013 | T032, T041, T047, T060–T061 | quickstart e suíte completa |

## Notes

- Fixtures autorizadas existem somente em testes com `evidence_kind=TEST_FIXTURE`, são bloqueadas fora de `testing` e nenhuma task promove o registro regulatório real para `AUTHORIZED`.
- Ausência, incompatibilidade, estado qualitativo, aplicabilidade desconhecida e bloqueio regulatório nunca são zero.
- Energia parcial só existe após matriz ENE estruturalmente completa e autorizada; falha estrutural mantém energia e subtotal `null`.
- `%VD` é independente por linha e exige MAP, numerador, VDR, unidade e conversão autorizados.
- A feature preserva resultados racionais exatos anteriores à declaração final; arredondamento e apresentação pertencem à futura feature de rotulagem.
- Nenhuma task deve ser marcada concluída antes de seu teste/checkpoint correspondente passar.
