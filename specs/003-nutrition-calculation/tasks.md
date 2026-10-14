# Tasks: Nutrition Calculation (003-nutrition-calculation)

**Feature**: `003-nutrition-calculation`

**Branch**: `003-nutrition-calculation`

**Spec**: `specs/003-nutrition-calculation/spec.md`

**Plan**: `specs/003-nutrition-calculation/plan.md`

**Tests**: obrigatórios, com PostgreSQL e abordagem tests-first para aritmética, regras de domínio, contratos HTTP, persistência e imutabilidade.

---

## Phase 1: Setup — Dependência Matemática

**Purpose**: Tornar explícita e reproduzível a dependência aprovada antes de qualquer código importar `Brick\Math`.

- [X] T001 Declarar `brick/math` como dependência direta em `composer.json` com constraint compatível com a versão já resolvida `1.0.0` e atualizar `composer.lock` somente no necessário, sem resolver versão diferente nem atualizar/remover dependências não relacionadas; validar com `composer validate` e `composer show brick/math` que a versão resolvida permanece `1.0.0`

**Checkpoint**: `Brick\Math\BigRational` pode ser importado pela aplicação sem depender silenciosamente da transitividade de Laravel/Ramsey.

---

## Phase 2: Foundational — Schema e Models Fundamentais

**Purpose**: Criar o agregado relacional histórico exigido por todas as User Stories.

**⚠️ CRITICAL**: Nenhuma User Story pode começar antes de esta fase estar concluída.

### Teste estrutural inicial

- [X] T002 Criar testes PostgreSQL inicialmente falhos em `tests/Feature/NutritionCalculationPersistenceTest.php` para todas as tabelas, FKs internas, unicidades, checks de razões (numerador inteiro, denominador inteiro positivo, presença conjunta e zero canônico `0/1`), checks condicionais de contribuição e ausência de FKs vivas nos IDs históricos definidos em `data-model.md`

### Migrations

- [X] T003 Criar `database/migrations/[timestamp]_create_nutrition_calculations_table.php` com `id BIGINT`, `recipe_id BIGINT` obrigatório com FK para `recipes.id` sem cascade destrutivo, `yield_value NUMERIC` obrigatório positivo e finito, `yield_unit VARCHAR(2)` restrito a `g`/`ml`, `rules_version TEXT` obrigatório, somente `created_at` e ausência de `updated_at`

- [X] T004 Criar `database/migrations/[timestamp]_create_nutrition_calculation_ingredients_table.php` com FK interna obrigatória para `nutrition_calculations.id` com cascade, `recipe_ingredient_id BIGINT` histórico sem FK viva, `position BIGINT` positiva, `quantity NUMERIC` positiva e finita, `unit VARCHAR(1)` restrita a `g`, `food_id BIGINT` histórico sem FK viva, `food_source TEXT` obrigatório e `food_source_code TEXT` nullable; adicionar `UNIQUE (nutrition_calculation_id, recipe_ingredient_id)` e `UNIQUE (nutrition_calculation_id, position)`

- [X] T005 Criar `database/migrations/[timestamp]_create_nutrition_calculation_nutrients_table.php` com FK interna obrigatória para a execução, `nutrient_id BIGINT` histórico sem FK viva, `nutrient_name TEXT`, `nutrient_unit TEXT`, pares nullable `total_numerator/total_denominator` e `reference_numerator/reference_denominator` em `NUMERIC` sem escala fixa, `is_complete BOOLEAN`, `has_missing_relation BOOLEAN`, `reference_basis NUMERIC` restrito a `100` e `reference_unit VARCHAR(2)` restrita a `g`/`ml`; adicionar `UNIQUE (nutrition_calculation_id, nutrient_id)` e checks de razões/presença conjunta aprovados

- [X] T006 Criar `database/migrations/[timestamp]_create_nutrition_calculation_contributions_table.php` com FKs internas obrigatórias para ingrediente e nutriente snapshot, `food_nutrient_id BIGINT` histórico nullable sem FK viva, `input_status TEXT` nullable restrito a `EXACT`, `TRACE`, `NA`, `ABSENT`, `REEVALUATION`, `input_amount_value NUMERIC(7,2)` nullable, `is_missing_relation BOOLEAN`, par nullable `value_numerator/value_denominator` em `NUMERIC` sem escala fixa e `UNIQUE (nutrition_calculation_ingredient_id, nutrition_calculation_nutrient_id)`; aplicar checks: relação ausente não possui relação/status/valor/razão, `EXACT` possui relação/status/valor/razão e estado qualitativo possui relação/status sem valor/razão

- [X] T007 Criar `database/migrations/[timestamp]_create_nutrition_calculation_nutrient_states_table.php` com FK interna obrigatória para `nutrition_calculation_nutrients.id`, `state TEXT` restrito somente a `TRACE`, `NA`, `ABSENT`, `REEVALUATION`, cascade interno e `UNIQUE (nutrition_calculation_nutrient_id, state)`, sem permitir `EXACT` nem representar ausência de relação como estado

### Models

- [X] T008 [P] Criar `app/Models/NutritionCalculation.php` para a raiz imutável, mantendo `created_at` e configurando explicitamente `public const UPDATED_AT = null;` para não tentar gravar `updated_at`, com `rules_version = nutrition-calculation-v1`, valores `NUMERIC` mantidos como strings e relacionamentos internos com ingredientes e nutrientes; não adicionar operações de update/delete nem depender do estado atual da Recipe para leitura histórica

- [X] T009 [P] Criar `app/Models/NutritionCalculationIngredient.php` com `public $timestamps = false;`, sem tentar gravar `created_at` ou `updated_at`, valores numéricos preservados como strings, relacionamentos internos com execução e contribuições e campos históricos `recipe_ingredient_id`/`food_id` sem relacionamentos Eloquent vivos para `RecipeIngredient` ou `Food`

- [X] T010 [P] Criar `app/Models/NutritionCalculationNutrient.php` com `public $timestamps = false;`, sem tentar gravar `created_at` ou `updated_at`, valores de razão preservados como strings, relacionamentos internos com execução, contribuições e estados, sem relacionamento vivo obrigatório com `Nutrient`

- [X] T011 [P] Criar `app/Models/NutritionCalculationContribution.php` com `public $timestamps = false;`, sem tentar gravar `created_at` ou `updated_at`, valores numéricos/razões preservados como strings e relacionamentos apenas para os snapshots internos de ingrediente e nutriente, mantendo `food_nutrient_id` como identificação histórica sem relacionamento vivo obrigatório

- [X] T012 [P] Criar `app/Models/NutritionCalculationNutrientState.php` com `public $timestamps = false;`, sem tentar gravar `created_at` ou `updated_at`, relacionamento interno para o resultado por nutriente e representação restrita aos quatro estados qualitativos aprovados, sem `EXACT` e sem estado inventado para ausência de relação

### Validação da fundação

- [X] T013 Executar `php artisan migrate`, `php artisan migrate:status` e `php artisan test --compact tests/Feature/NutritionCalculationPersistenceTest.php`, confirmando o schema PostgreSQL e todos os constraints estruturais de T002–T012 antes das User Stories

**Checkpoint**: Schema e Models históricos existem, preservam strings/razões sem casts inexatos e não modificam `Recipe`, `RecipeIngredient`, `Food`, `Nutrient` ou `FoodNutrient`.

---

## Phase 3: User Story 1 — Calcular contribuição de cada ingrediente (Priority: P1) 🎯 MVP técnico

**Goal**: Calcular exatamente a contribuição de cada ocorrência de ingrediente para todos os Nutrients, preservando `EXACT`, zero, estados qualitativos e ausência de relação.

**Independent Test**: Com fixtures PostgreSQL controladas, verificar `quantity × amount_value ÷ 100` como razão exata, inclusive dízima e zero `0/1`, e confirmar uma contribuição para cada ocorrência × Nutrient sem N+1.

### Tests for User Story 1

- [X] T014 [P] [US1] Criar testes unitários inicialmente falhos em `tests/Unit/NutritionCalculationArithmeticTest.php` para construção de decimais a partir de strings, multiplicação, divisão, soma, fração reduzida, decimal terminado, dízima periódica, zero canônico `0/1` e ausência de `float`, `double`, escala arbitrária ou arredondamento

- [X] T015 [P] [US1] Criar testes inicialmente falhos em `tests/Feature/NutritionCalculationTest.php` para contribuição `EXACT` positiva e zero, `TRACE`, `NA`, `ABSENT`, `REEVALUATION`, ausência explícita de `FoodNutrient`, múltiplos Nutrients, energia da fonte como Nutrient comum, mesmo Food em ocorrências diferentes, todos os Nutrients existentes e carregamento agrupado sem N+1 nem produto cartesiano persistido na Base Nutricional

### Implementation for User Story 1

- [X] T016 [US1] Criar `app/Services/NutritionCalculationArithmetic.php` como helper técnico mínimo baseado em `Brick\Math\BigRational`, recebendo somente strings decimais e produzindo razões reduzidas com numerador inteiro, denominador inteiro positivo e zero `0/1`, sem `float`, `double`, escala fixa ou arredondamento

- [X] T017 [US1] Criar `app/Services/NutritionCalculationService.php` e implementar a etapa de contribuições: carregar todos os `Nutrients` uma vez, carregar em consulta agrupada os `FoodNutrients` dos Foods distintos, indexar em memória, percorrer cada ocorrência de ingrediente × Nutrient e calcular `quantity × amount_value ÷ 100` somente para `EXACT`, preservando estados qualitativos ou `is_missing_relation` sem valor/status inventado

- [X] T018 [US1] Executar `php artisan test --compact tests/Unit/NutritionCalculationArithmeticTest.php tests/Feature/NutritionCalculationTest.php` e confirmar que a User Story 1 passa sem modificar dados atuais de Recipe ou Base Nutricional

**Checkpoint**: Cada contribuição pode ser calculada e explicada de modo exato e independente, mas ainda não existe consolidação ou endpoint HTTP.

---

## Phase 4: User Story 2 — Consolidar composição da receita (Priority: P2)

**Goal**: Consolidar separadamente cada Nutrient, somando somente `EXACT` e preservando estados, ausência de relação e completude.

**Independent Test**: Consolidar fixtures com `EXACT + EXACT`, todas as combinações qualitativas e ausência de relação, confirmando valor somente quando existe `EXACT`, conjunto sem precedência e `is_complete = true` exclusivamente quando todas as contribuições são `EXACT`.

### Tests for User Story 2

- [X] T019 [US2] Estender `tests/Feature/NutritionCalculationTest.php` com testes inicialmente falhos para `EXACT + EXACT`, `EXACT` combinado separadamente com `TRACE`, `NA`, `ABSENT`, `REEVALUATION` e ausência de relação, estados homogêneos sem `EXACT`, estados diferentes sem `EXACT`, estados + ausência sem `EXACT` e todos sem relação; verificar subtotal apenas dos `EXACT`, total nulo sem `EXACT`, conjunto completo sem precedência, indicador de ausência separado e `is_complete` verdadeiro somente para todas as contribuições `EXACT`

### Implementation for User Story 2

- [X] T020 [US2] Implementar em `app/Services/NutritionCalculationService.php` a consolidação racional por `nutrient_id`, somando somente contribuições `EXACT`, mantendo Nutrients separados, preservando todos os estados qualitativos sem precedência, mantendo `has_missing_relation` separado e não produzindo valor numérico quando não existir `EXACT`

- [X] T021 [US2] Executar `php artisan test --compact tests/Feature/NutritionCalculationTest.php` e confirmar integralmente a matriz de consolidação aprovada, incluindo zero numérico distinto de todos os estados e ausência de conversão/omissão silenciosa

**Checkpoint**: Os totais/subtotais e as indicações de incompletude estão corretos; normalização e API ainda não foram implementadas.

---

## Phase 5: User Story 3 — Normalizar pelo rendimento e criar execução (Priority: P3)

**Goal**: Normalizar total/subtotal para 100 g ou 100 ml e criar atomicamente uma nova execução preservada pelo contrato POST.

**Independent Test**: Solicitar uma execução para Recipe persistida e verificar contribuição, total/subtotal, referência racional, snapshot, versão e nova identificação, incluindo referência incompleta que preserva estados/ausência.

### Tests for User Story 3

- [X] T022 [US3] Estender `tests/Feature/NutritionCalculationTest.php` com testes inicialmente falhos para `total_or_subtotal × 100 ÷ yield_value`, referência 100 g/100 ml, rendimento diferente da soma dos ingredientes, ausência de conversão g↔ml/densidade, referência nula sem `EXACT` e referência numérica incompleta preservando exatamente estados e ausência do subtotal

- [X] T023 [US3] Adicionar em `tests/Feature/NutritionCalculationTest.php` testes inicialmente falhos do contrato `POST /api/recipes/{recipe}/nutrition-calculations`: sem payload funcional, cada chamada cria novo ID, `rules_version = nutrition-calculation-v1`, snapshot mínimo completo, contribuições/resultados como razões de strings, ordenação determinística e rollback sem execução parcial em qualquer falha

### Implementation for User Story 3

- [X] T024 [US3] Implementar em `app/Services/NutritionCalculationArithmetic.php` a normalização racional `total_or_subtotal × 100 ÷ yield_value`, produzindo referência quando existir valor numérico e nenhuma referência numérica sem `EXACT`, sem conversão g↔ml, densidade, rendimento inferido ou arredondamento

- [X] T025 [US3] Implementar em `app/Services/NutritionCalculationService.php` a criação integral de uma execução dentro de única transação PostgreSQL `REPEATABLE READ`: ler Recipe/ingredientes/Base em snapshot coerente, calcular, consolidar, normalizar e persistir raiz, snapshots, matriz de contribuições e estados com `rules_version = nutrition-calculation-v1`; qualquer falha deve executar rollback integral e nova solicitação nunca deve substituir execução anterior

- [X] T026 [P] [US3] Criar `app/Http/Resources/NutritionCalculationResource.php` para serializar somente o agregado preservado conforme `specs/003-nutrition-calculation/contracts/nutrition-calculations.md`, usando strings para decimais/razões, ordenação determinística e dimensões separadas de valor, estados, ausência e completude, sem cálculo no Resource

- [X] T027 [US3] Criar `app/Http/Controllers/NutritionCalculationController.php` com ação de criação que apenas recebe a Recipe resolvida, delega ao `NutritionCalculationService` e retorna `NutritionCalculationResource`, sem cálculo ou regra de domínio no Controller

- [X] T028 [US3] Registrar somente a rota de criação `POST /api/recipes/{recipe}/nutrition-calculations` em `routes/api.php`, sem PUT, PATCH, DELETE, listagem, filtro, paginação ou endpoint de “resultado atual”

- [X] T029 [US3] Executar `php artisan test --compact tests/Unit/NutritionCalculationArithmeticTest.php tests/Feature/NutritionCalculationTest.php` e confirmar a criação atômica, referências 100 g/100 ml e contrato POST antes de iniciar consulta histórica

**Checkpoint**: O sistema cria uma execução completa, nova e reproduzível; nenhuma operação histórica de consulta foi adicionada ainda.

---

## Phase 6: User Story 4 — Consulta histórica, imutabilidade e atomicidade (Priority: P4)

**Goal**: Consultar execuções antigas exclusivamente pelo snapshot preservado e impedir alterações ou exclusões funcionais e no PostgreSQL.

**Independent Test**: Criar uma execução, alterar Recipe/FoodNutrient atuais, solicitar outra execução e confirmar que o GET antigo permanece byte semanticamente idêntico; tentativas de UPDATE/DELETE histórico falham e rollback não deixa registros parciais.

### Tests for User Story 4

- [X] T030 [US4] Estender `tests/Feature/NutritionCalculationPersistenceTest.php` com testes inicialmente falhos que criam uma execução, registram sua resposta histórica, alteram depois Recipe, RecipeIngredient, FoodNutrient, os campos relevantes de rastreabilidade do Food original e `Nutrient.name` e/ou `Nutrient.unit` sem alterar IDs primários, consultam novamente a execução antiga e confirmam resposta histórica idêntica baseada nos dados copiados no snapshot, não nos valores atuais; cobrir também nova execução com novo ID sem substituir a anterior, `rules_version` preservada, rollback integral, restauração automática das fontes pela transação de teste, Base Nutricional/Recipe inalteradas ao fim do teste e bloqueio PostgreSQL de UPDATE/DELETE em todas as cinco tabelas históricas

### Implementation for User Story 4

- [X] T031 [US4] Criar migration não destrutiva `database/migrations/[timestamp]_protect_nutrition_calculation_history_from_mutation.php` com proteção PostgreSQL contra `UPDATE` e `DELETE` nas cinco tabelas históricas, incluindo reversão segura no `down`, sem auditoria avançada, event sourcing ou operação funcional de exclusão

- [X] T032 [US4] Implementar em `app/Services/NutritionCalculationService.php` a consulta read-only de uma execução com todo o agregado snapshot, sem métodos de update/delete e sem reconstruir qualquer campo a partir de Recipe, RecipeIngredient, Food, Nutrient ou FoodNutrient atuais

- [X] T033 [US4] Completar `app/Http/Resources/NutritionCalculationResource.php` para que criação e consulta histórica compartilhem exatamente a representação aprovada e dependam somente das relações internas carregadas do agregado preservado

- [X] T034 [US4] Adicionar ao `app/Http/Controllers/NutritionCalculationController.php` a ação de consulta que apenas delega a leitura histórica ao Service e retorna o Resource, sem cálculo ou acesso direto às fontes atuais

- [X] T035 [US4] Registrar somente a rota de consulta `GET /api/nutrition-calculations/{calculation}` em `routes/api.php`, sem adicionar qualquer outra operação sobre resultados

- [X] T036 [US4] Executar `php artisan test --compact tests/Feature/NutritionCalculationPersistenceTest.php tests/Feature/NutritionCalculationTest.php` e confirmar imutabilidade, consulta histórica, novas execuções independentes e atomicidade integral

**Checkpoint**: Criação e consulta estão completas; resultados antigos não dependem das fontes atuais e não aceitam mutação.

---

## Phase 7: Polish & Validação Geral

**Purpose**: Validar migrations, suíte, contrato, Quickstart, dependências e limites de escopo sem adicionar funcionalidade.

- [X] T037 Executar `php artisan migrate` e `php artisan migrate:status` em PostgreSQL e confirmar todas as migrations de `database/migrations/` desta feature aplicadas, constraints ativas e ausência de alterações nas migrations já aplicadas das features 001/002

- [X] T038 Executar a suíte completa de `tests/` com `php artisan test` e `vendor/bin/phpunit`, confirmando que todos os testes existentes e os novos testes da feature passam em PostgreSQL

- [X] T039 Executar `vendor/bin/pint --test` sobre os arquivos PHP da feature e `git diff --check` na raiz `.` do repositório, corrigindo somente formatação da implementação da feature quando necessário e confirmando ausência de whitespace errors

- [X] T040 Executar integralmente `specs/003-nutrition-calculation/quickstart.md`, validando cálculo real de Recipe, POST/GET, snapshot, versão, estados, ausência, 100 g, 100 ml, precisão, alteração da Recipe sem mutação histórica e nova execução independente

- [X] T041 Verificar o caso real “GELEIA DE AMORA” conforme `specs/003-nutrition-calculation/quickstart.md`: confirmar `Açúcar, cristal` `source_code=492` e procurar somente mapeamentos explicitamente aprovados para amora e suco de limão; executar/comparar aproximadamente 65,21 g/100 g apenas se os três Foods estiverem inequivocamente aprovados e, caso contrário, registrar o bloqueio sem marcar o resultado nutricional como PASS e sem criar Food sintético, aproximação textual, composição manual, escolha arbitrária ou ajuste forçado

- [X] T042 Revisar `composer.json`, `composer.lock`, `routes/api.php`, Models, Services e migrations para confirmar `brick/math` direto em 1.0.0, somente os dois endpoints aprovados, Service sem update/delete, proteção PostgreSQL ativa, ausência de N+1 e inexistência de porção, medida caseira, %VD, arredondamento, energia por fatores, açúcares adicionados, rotulagem, preview, PDF/PNG, autenticação, cache, jobs, filas, histórico editável ou alteração funcional das features 001/002

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: sem dependências; T001 bloqueia qualquer importação `Brick\Math`.
- **Phase 2 (Foundational)**: depende da Phase 1 e bloqueia todas as User Stories.
- **Phase 3 (US1)**: depende da Phase 2; fornece aritmética e contribuições.
- **Phase 4 (US2)**: depende da US1; consolida as contribuições.
- **Phase 5 (US3)**: depende da US1 e US2; normaliza e persiste a execução completa pelo POST.
- **Phase 6 (US4)**: depende da US3; consulta e protege o agregado histórico já criado.
- **Phase 7 (Polish)**: depende de todas as User Stories concluídas.

### User Story Dependencies

```text
US1 — contribuições exatas/qualitativas
  ↓
US2 — consolidação por Nutrient
  ↓
US3 — normalização + criação da execução
  ↓
US4 — consulta histórica + imutabilidade
```

As histórias são testáveis em seus checkpoints, mas a dependência matemática do motor exige a ordem acima.

### Within Each User Story

- Escrever os testes da fase e confirmar que falham antes do código correspondente.
- Implementar aritmética antes de contribuição; contribuição antes de consolidação; consolidação antes de normalização/persistência.
- Implementar Service antes de Controller/rotas.
- Persistir o agregado completo antes de expor consulta histórica.
- Executar a validação estreita da fase antes de avançar.

## Parallel Opportunities

- T008–T012 podem ser implementadas em paralelo após T003–T007 porque alteram Models distintos.
- T014 e T015 podem ser escritos em paralelo porque usam arquivos de teste distintos.
- T026 deve ser concluída antes de T027, pois o Controller depende de `NutritionCalculationResource`.
- Revisões preparatórias de T041 e T042 podem ocorrer em paralelo após a US4, mas a conclusão da Phase 7 permanece sequencial para evitar interferência no banco de testes.

## Parallel Example: Foundational Models

```text
Task T008: "Criar app/Models/NutritionCalculation.php"
Task T009: "Criar app/Models/NutritionCalculationIngredient.php"
Task T010: "Criar app/Models/NutritionCalculationNutrient.php"
Task T011: "Criar app/Models/NutritionCalculationContribution.php"
Task T012: "Criar app/Models/NutritionCalculationNutrientState.php"
```

## Parallel Example: User Story 1 Tests

```text
Task T014: "Criar tests/Unit/NutritionCalculationArithmeticTest.php"
Task T015: "Criar/estender tests/Feature/NutritionCalculationTest.php"
```

---

## Implementation Strategy

### MVP técnico — User Story 1

1. Completar Phase 1 e fixar `brick/math` 1.0.0 como dependência direta.
2. Completar Phase 2 e validar o schema histórico.
3. Escrever testes da US1 antes do código.
4. Implementar aritmética racional e contribuições.
5. Parar em T018 e validar a US1 independentemente.

### Entrega incremental

1. Setup + Foundational → dependência e agregado relacional prontos.
2. US1 → contribuições por ocorrência × Nutrient.
3. US2 → totais/subtotais e completude.
4. US3 → referência por rendimento e POST transacional.
5. US4 → GET histórico e imutabilidade em aplicação/banco.
6. Polish → validação completa e Quickstart.

## Notes

- `[P]` indica arquivos distintos sem dependência incompleta; não autoriza execução fora da ordem de fases.
- `[US1]`–`[US4]` mapeiam diretamente às histórias da Specification.
- Fixtures controladas existem somente no banco de testes e não alteram/substituem a TACO real.
- Os IDs de RecipeIngredient, Food, Nutrient e FoodNutrient copiados para o snapshot são históricos; somente FKs internas e a FK raiz aprovada para Recipe são vivas.
- Nenhuma task cria entidade de domínio além das cinco aprovadas; `NutritionCalculationArithmetic` é helper técnico mínimo, não entidade persistida.
- O bloqueio do caso real da geleia não bloqueia os testes genéricos do motor e não autoriza marcar 65,21 como validado.
- Nenhuma task implementa funcionalidades excluídas ou altera contratos das features 001/002.
