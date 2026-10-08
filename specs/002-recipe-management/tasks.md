# Tasks: Recipe Management

**Input**: Design documents from `specs/002-recipe-management/`

**Prerequisites**: `plan.md`, `spec.md`, `research.md`, `data-model.md`,
`contracts/recipes.md`, `quickstart.md`

**Tests**: Testes automatizados são obrigatórios para os cenários aprovados e devem
usar PostgreSQL.

**Organization**: As tasks são agrupadas por User Story para permitir implementação
e validação incremental, respeitando as dependências técnicas compartilhadas.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode ser executada em paralelo por atuar em arquivo diferente e não
  depender de task incompleta.
- **[Story]**: identifica a User Story atendida (`US1`, `US2`, `US3`).
- Toda task indica o caminho exato dos arquivos afetados ou validados.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Confirmar que a infraestrutura de testes existente permanece alinhada
ao PostgreSQL antes de criar a modelagem.

- [X] T001 Confirmar em `phpunit.xml` que a suíte usa `DB_CONNECTION=pgsql`, executar a suíte baseline com `php artisan test` e não adicionar credenciais ao arquivo

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Criar schema, entidades e infraestrutura de teste compartilhados por
todas as User Stories.

**⚠️ CRITICAL**: Nenhuma User Story pode começar antes da conclusão desta fase.

- [X] T002 Criar `database/migrations/2026_10_07_000001_create_recipes_table.php` com `id BIGINT`, `name TEXT NOT NULL`, `yield_value NUMERIC` sem precisão/escala e obrigatório, `yield_unit VARCHAR(2) NOT NULL`, timestamps e constraints `yield_value > 0` com rejeição de `NaN`/`Infinity` e `yield_unit IN ('g', 'ml')`
- [X] T003 Criar `database/migrations/2026_10_07_000002_create_recipe_ingredients_table.php` com `id BIGINT`, `recipe_id` obrigatório e FK para `recipes.id` com cascade somente sobre os ingredientes, `food_id` obrigatório e FK para `foods.id` sem cascade para Food, `quantity NUMERIC` sem precisão/escala e obrigatório, `unit VARCHAR(1) NOT NULL`, `position BIGINT NOT NULL`, timestamps, índice em `food_id` e constraints `quantity > 0` com rejeição de `NaN`/`Infinity`, `unit = 'g'`, `position > 0` e `UNIQUE (recipe_id, position)`
- [X] T004 [P] Criar `app/Models/Recipe.php` com atributos persistíveis aprovados, valores decimais mantidos como strings sem casts `float`, `double` ou `decimal:N`, e relacionamento `hasMany` para `RecipeIngredient` ordenado por `position`
- [X] T005 [P] Criar `app/Models/RecipeIngredient.php` com atributos persistíveis aprovados, `quantity` mantida como string sem casts `float`, `double` ou `decimal:N`, relacionamento `belongsTo` com `Recipe` e relacionamento `belongsTo` com o `Food` existente, sem alterar `app/Models/Food.php`
- [X] T006 Criar factories de teste em `database/factories/RecipeFactory.php` e `database/factories/RecipeIngredientFactory.php` sem criar Food substituto, sem precisão decimal fixa e exigindo que os testes forneçam um `foods.id` existente

**Checkpoint**: Schema e Models compartilhados prontos; a Base Nutricional permanece
inalterada.

---

## Phase 3: User Story 1 - Criar e salvar uma receita (Priority: P1) 🎯 MVP

**Goal**: Criar atomicamente uma receita completa com nome, rendimento e um ou mais
ingredientes associados a Foods existentes.

**Independent Test**: Enviar `POST /api/recipes` com múltiplos ingredientes válidos,
consultar diretamente o banco e confirmar que nome, `food_id`, strings decimais,
unidades, posições e rendimento foram preservados; entradas inválidas não podem
persistir nenhuma parte do agregado.

### Tests for User Story 1

> Escrever estes testes primeiro e confirmar que falham antes da implementação.

- [ ] T007 [P] [US1] Criar testes do contrato `POST /api/recipes` em `tests/Feature/RecipeManagementTest.php` cobrindo criação com um e múltiplos ingredientes, rendimento positivo fracionário em `g` e `ml`, resposta ordenada, rendimento diferente da soma dos ingredientes, decimais retornados como strings, trim de `name`, `yield_value`, `yield_unit`, `ingredients.*.quantity` e `ingredients.*.unit`, conversão de vazio ou somente espaços para `null`, ausência de normalização de caixa, ausência de conversão de unidade e ausência de arredondamento
- [ ] T008 [P] [US1] Criar testes de integridade e rejeição em `tests/Feature/RecipePersistenceTest.php` cobrindo receita sem ingredientes, campo obrigatório ausente ou `null` após trim, `food_id` inexistente, quantidade zero/negativa, fração positiva, unidade diferente de `g`, rendimento zero/negativo, unidade de rendimento fora de `g`/`ml`, posição não positiva/repetida, precisão decimal sem escala fixa, rollback integral quando a persistência de um ingrediente falhar e ausência de criação, alteração ou remoção de Food

### Implementation for User Story 1

- [ ] T009 [US1] Criar `app/Http/Requests/StoreRecipeRequest.php` para validar após `TrimStrings` e `ConvertEmptyStringsToNull`: `name` textual obrigatório sem limite de tamanho não aprovado; `yield_value` como string decimal de base 10, positiva e sem limite de casas; `yield_unit` em `g`/`ml`; `ingredients` como array com ao menos um item; cada `food_id` existente em `foods.id`; cada `quantity` como string decimal positiva e sem limite de casas; `unit` exatamente `g`; `position` inteira, positiva e distinta, sem converter decimais para tipos numéricos
- [ ] T010 [US1] Criar `app/Services/RecipeService.php` com criação de Recipe e toda a coleção de RecipeIngredients em `DB::transaction`, preservando strings decimais, rendimento informado, unidades e posições, sem inferir rendimento, modificar Food ou executar cálculos nutricionais
- [ ] T011 [US1] Criar o método de criação em `app/Http/Controllers/RecipeController.php` para somente orquestrar `StoreRecipeRequest` e `RecipeService` e devolver o envelope `data` definido em `contracts/recipes.md`
- [ ] T012 [US1] Registrar somente `POST /api/recipes` em `routes/api.php` para a operação de criação aprovada
- [ ] T013 [US1] Executar os testes da User Story 1 em `tests/Feature/RecipeManagementTest.php` e `tests/Feature/RecipePersistenceTest.php` com PostgreSQL e confirmar que criação válida, validações e atomicidade passam

**Checkpoint**: Uma receita completa pode ser criada e persistida atomicamente sem
alterar a Base Nutricional.

---

## Phase 4: User Story 2 - Gerenciar ingredientes da receita (Priority: P2)

**Goal**: Substituir integralmente a coleção de ingredientes via PUT para adicionar,
editar, remover e reordenar entradas sem efeitos colaterais.

**Independent Test**: Partir de uma receita existente, enviar o estado completo por
`PUT /api/recipes/{recipe}` e confirmar diretamente no banco que itens enviados
formam a coleção final, itens omitidos foram removidos e somente valores enviados
explicitamente mudaram.

### Tests for User Story 2

> Escrever estes testes primeiro e confirmar que falham antes da implementação.

- [ ] T014 [P] [US2] Adicionar testes do contrato `PUT /api/recipes/{recipe}` em `tests/Feature/RecipeManagementTest.php` cobrindo adição, alteração de quantidade, troca explícita de `food_id`, remoção de ingrediente omitido, reordenação por `position`, coleção vazia rejeitada, payload completo obrigatório, trim e validação aninhada iguais ao POST e retorno da coleção final ordenada
- [ ] T015 [P] [US2] Adicionar testes de integridade da substituição em `tests/Feature/RecipePersistenceTest.php` cobrindo rollback integral diante de falha em qualquer ingrediente, posição única por receita, preservação de strings decimais, reordenação sem alterar Food/quantidade/unidade/rendimento, remoção de RecipeIngredient sem remover Food e edição sem criar ou modificar registros da Base Nutricional

### Implementation for User Story 2

- [ ] T016 [US2] Criar `app/Http/Requests/UpdateRecipeRequest.php` com o mesmo contrato completo e as mesmas regras de `StoreRecipeRequest`, incluindo strings decimais positivas, unidade de ingrediente `g`, rendimento em `g`/`ml`, ao menos um ingrediente e posições positivas distintas após a normalização HTTP padrão
- [ ] T017 [US2] Implementar em `app/Services/RecipeService.php` a substituição integral transacional: atualizar nome e rendimento, remover RecipeIngredients omitidos, recriar a coleção enviada com seus `food_id`, quantidades, unidades e posições e garantir que nenhuma falha deixe estado parcial ou modifique Food
- [ ] T018 [US2] Adicionar o método de atualização em `app/Http/Controllers/RecipeController.php` para somente orquestrar `UpdateRecipeRequest`, route model binding e `RecipeService`, sem regras de negócio no Controller
- [ ] T019 [US2] Registrar somente `PUT /api/recipes/{recipe}` em `routes/api.php`, sem PATCH, DELETE ou endpoints separados de ingredientes
- [ ] T020 [US2] Executar os testes da User Story 2 em `tests/Feature/RecipeManagementTest.php` e `tests/Feature/RecipePersistenceTest.php` com PostgreSQL e confirmar substituição integral, rollback, remoção e reordenação

**Checkpoint**: A composição pode ser gerenciada integralmente por PUT sem alterar
Food nem produzir estados parciais.

---

## Phase 5: User Story 3 - Consultar e editar uma receita existente (Priority: P3)

**Goal**: Reabrir uma receita persistida e reproduzir exatamente o último estado
salvo, incluindo associação com Food, ordem e decimais.

**Independent Test**: Criar e atualizar uma receita, executar
`GET /api/recipes/{recipe}` e confirmar que todos os campos correspondem ao último
PUT e que cada ingrediente continua associado ao Food explicitamente selecionado.

### Tests for User Story 3

> Escrever estes testes primeiro e confirmar que falham antes da implementação.

- [ ] T021 [P] [US3] Adicionar testes do contrato `GET /api/recipes/{recipe}` em `tests/Feature/RecipeManagementTest.php` cobrindo envelope `data`, receita inexistente sem criação ou alteração, ingredientes em ordem crescente de `position`, `food_id`, quantidade e rendimento como strings decimais, unidades e ausência de resultados nutricionais
- [ ] T022 [P] [US3] Adicionar testes de reabertura em `tests/Feature/RecipePersistenceTest.php` criando, atualizando e consultando novamente a receita para confirmar preservação de nome, rendimento, unidade do rendimento, coleção final, Foods selecionados, quantidades, unidades e posições sem arredondamento, conversão ou alteração da Base Nutricional

### Implementation for User Story 3

- [ ] T023 [US3] Implementar em `app/Services/RecipeService.php` a consulta da receita com RecipeIngredients e Foods existentes, carregando a coleção em ordem crescente de `position` e serializando `quantity` e `yield_value` como strings sem casts numéricos
- [ ] T024 [US3] Adicionar o método de consulta em `app/Http/Controllers/RecipeController.php` para somente orquestrar route model binding e `RecipeService` e devolver o envelope `data` sem nutrientes calculados
- [ ] T025 [US3] Registrar somente `GET /api/recipes/{recipe}` em `routes/api.php`, sem listagem ou busca de receitas
- [ ] T026 [US3] Executar os testes da User Story 3 em `tests/Feature/RecipeManagementTest.php` e `tests/Feature/RecipePersistenceTest.php` com PostgreSQL e confirmar consulta e reprodução integral após criação e PUT

**Checkpoint**: Todas as User Stories estão funcionais e a receita reaberta reproduz
o último estado válido persistido.

---

## Phase 6: Polish & Cross-Cutting Validation

**Purpose**: Validar migrations, suíte completa, qualidade e limites de escopo sem
adicionar funcionalidades.

- [ ] T027 Executar `php artisan migrate:status` e `php artisan test` com PostgreSQL conforme `phpunit.xml`, confirmando migrations aplicadas e compatibilidade de todos os testes existentes e novos
- [ ] T028 Executar `vendor/bin/pint --test` e `git diff --check`, revisar `app/`, `database/migrations/`, `routes/api.php` e `tests/Feature/` e confirmar ausência de autenticação, draft, cálculos nutricionais, rotulagem, PDF/PNG, histórico, DELETE, PATCH, listagem, busca de receitas e endpoints separados de ingredientes
- [ ] T029 Executar integralmente os cenários manuais de `specs/002-recipe-management/quickstart.md` e confirmar criação, GET, PUT integral, normalização HTTP, rejeições, precisão decimal, atomicidade, preservação da ordem e ausência de modificação na Base Nutricional

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 — Setup**: sem dependências; inicia imediatamente.
- **Phase 2 — Foundational**: depende de T001 e bloqueia todas as User Stories.
- **Phase 3 — US1**: depende da Phase 2 e entrega o MVP de criação persistente.
- **Phase 4 — US2**: depende da US1 porque amplia `RecipeService`,
  `RecipeController`, routes e testes já criados.
- **Phase 5 — US3**: depende da US1; pode ser projetada independentemente da lógica
  de substituição, mas deve ser integrada depois da US2 para evitar conflitos nos
  mesmos arquivos.
- **Phase 6 — Polish**: depende das três User Stories concluídas.

### User Story Dependencies

- **US1 (P1)**: inicia após Foundational; não depende de outra história.
- **US2 (P2)**: depende da infraestrutura e da criação de receita entregues pela US1.
- **US3 (P3)**: depende da receita persistida pela US1 e valida o estado final após
  a edição da US2.

### Within Each User Story

- Testes devem ser escritos e executados com falha antes da implementação.
- Form Request antes do método de Service que consome os dados validados.
- Service antes do método de Controller.
- Controller antes do registro final da rota.
- Testes da história devem passar antes do próximo checkpoint.

### Parallel Opportunities

- T004 e T005 podem ser executadas em paralelo após as migrations.
- T007 e T008 podem ser executadas em paralelo.
- T014 e T015 podem ser executadas em paralelo.
- T021 e T022 podem ser executadas em paralelo.
- As implementações das histórias são sequenciais porque compartilham
  `RecipeService.php`, `RecipeController.php`, `routes/api.php` e os mesmos arquivos
  de teste.

---

## Parallel Examples

### User Story 1

```text
T007: tests/Feature/RecipeManagementTest.php
T008: tests/Feature/RecipePersistenceTest.php
```

### User Story 2

```text
T014: tests/Feature/RecipeManagementTest.php
T015: tests/Feature/RecipePersistenceTest.php
```

### User Story 3

```text
T021: tests/Feature/RecipeManagementTest.php
T022: tests/Feature/RecipePersistenceTest.php
```

---

## Implementation Strategy

### MVP First — User Story 1

1. Concluir Setup e Foundational.
2. Escrever e executar os testes da US1 com falha.
3. Implementar somente criação transacional.
4. Executar T013 e parar para validar o MVP.

### Incremental Delivery

1. **US1**: criar e salvar receita completa.
2. **US2**: substituir integralmente ingredientes via PUT.
3. **US3**: consultar e reabrir o estado persistido.
4. **Polish**: executar suíte, quickstart e revisão de escopo.

## Notes

- Não modificar `Food`, `Nutrient`, `FoodNutrient`, importação TACO ou busca de
  alimentos.
- Não criar relacionamento inverso em `Food`; `RecipeIngredient belongsTo Food` é
  suficiente para esta feature.
- Não usar precisão/escala fixa, casts `float`, `double` ou `decimal:N`.
- Não remover ou excepcionar `TrimStrings` e `ConvertEmptyStringsToNull`.
- Não criar endpoints, entidades ou comportamentos além dos documentados.
- Não implementar cálculos nutricionais ou armazenar seus resultados.
