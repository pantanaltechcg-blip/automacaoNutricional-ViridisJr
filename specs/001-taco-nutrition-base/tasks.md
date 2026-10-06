# Tasks: TACO Nutrition Base (001-taco-nutrition-base)

**Feature**: `001-taco-nutrition-base`

**Branch**: `001-taco-nutrition-base`

**Spec**: `specs/001-taco-nutrition-base/spec.md`

**Plan**: `specs/001-taco-nutrition-base/plan.md`

---

## Phase 1: Setup (Infraestrutura Compartilhada)

**Purpose**: Preparação do banco de dados e habilitação dos recursos necessários.

- [ ] T001 Habilitar a extensão `unaccent` do PostgreSQL via migration em `database/migrations/[timestamp]_enable_unaccent_extension.php`

- [ ] T002 Verificar que o arquivo `.env` contém a conexão PostgreSQL configurada para o ambiente de desenvolvimento

**Checkpoint**: Infraestrutura do banco pronta. As migrations de schema podem ser aplicadas.

---

## Phase 2: Foundational (Pré-requisitos Bloqueantes)

**Purpose**: Definição da precisão necessária, criação do schema relacional e dos Models base dos quais todas as User Stories dependem.

- [ ] T003 Analisar os valores numéricos presentes na fonte TACO fornecida ao projeto e determinar a representação `NUMERIC`/`DECIMAL` necessária para preservar os valores originais sem arredondamento ou perda de precisão

- [ ] T004 Criar migration `database/migrations/[timestamp]_create_foods_table.php` com os campos: `id` (PK padrão Laravel), `name` (string, not null), `category` (string, nullable), `source` (string, not null), `source_code` (string, nullable), `timestamps`

- [ ] T005 Criar migration `database/migrations/[timestamp]_create_nutrients_table.php` com os campos: `id` (PK padrão Laravel), `name` (string, not null), `unit` (string, not null), `timestamps`

- [ ] T006 Criar migration `database/migrations/[timestamp]_create_food_nutrients_table.php` com os campos: `id` (PK padrão Laravel), `food_id` (FK -> `foods.id`, cascade delete), `nutrient_id` (FK -> `nutrients.id`, cascade delete), `amount_value` (numérico nullable utilizando a precisão definida em T003), `status` (string, not null, valores permitidos: `EXACT`, `TRACE`, `NA`, `ABSENT`), `timestamps`; com unique constraint em `(food_id, nutrient_id)`

- [ ] T007 [P] Criar Eloquent Model `app/Models/Food.php` com `$fillable` contendo `name`, `category`, `source`, `source_code`; relacionamento `hasMany(FoodNutrient::class)`

- [ ] T008 [P] Criar Eloquent Model `app/Models/Nutrient.php` com `$fillable` contendo `name`, `unit`; relacionamento `hasMany(FoodNutrient::class)`

- [ ] T009 Criar Eloquent Model `app/Models/FoodNutrient.php` com `$fillable` contendo `food_id`, `nutrient_id`, `amount_value`, `status`; relacionamentos `belongsTo(Food::class)` e `belongsTo(Nutrient::class)`; constante ou enum para os estados `EXACT`, `TRACE`, `NA`, `ABSENT`

- [ ] T010 Executar `php artisan migrate` e confirmar que as três tabelas foram criadas com o schema correto

**Checkpoint**: Schema e Models criados. Nenhum dado nutricional precisa estar populado ainda. As User Stories podem começar.

---

## Phase 3: User Story 1 — Armazenamento de Dados da TACO (Priority: P1)

**Goal**: O sistema deve possuir uma base nutricional formada a partir dos dados da TACO, preservando todos os componentes nutricionais disponíveis na fonte, incluindo os estados `EXACT`, `TRACE`, `NA` e `ABSENT`, sem conversão silenciosa para zero.

**Independent Test**: Verificar que os dados armazenados correspondem à fonte TACO, que `status` distingue corretamente os diferentes estados, que valores numéricos são preservados e que `source` identifica a origem TACO.

### Preparação da Fonte

- [ ] T011 [US1] Preparar e validar uma fonte estruturada de importação derivada da TACO fornecida ao projeto, preservando identificação dos alimentos, nome/descrição, categoria/grupo quando disponível, código original quando disponível, componentes nutricionais, unidades, valores numéricos e marcadores `Tr`, `NA` e ausência explícita de valor

### Implementação — User Story 1

- [ ] T012 [US1] Criar `app/Services/NutritionBaseService.php` com a responsabilidade de persistir os dados da base nutricional, recebendo os dados estruturados de um alimento e seus componentes nutricionais, criando ou atualizando registros em `foods`, `nutrients` e `food_nutrients` sem converter estados qualitativos para zero

- [ ] T013 [US1] Implementar no `NutritionBaseService` a lógica de mapeamento definida no `data-model.md`: valor numérico → `status=EXACT` e `amount_value=<valor>`; `Tr` → `status=TRACE` e ausência de valor numérico; `NA` → `status=NA` e ausência de valor numérico; célula explicitamente existente na estrutura da fonte e sem valor → `status=ABSENT` e ausência de valor numérico; nunca criar registros `ABSENT` via produto cartesiano entre alimentos e nutrientes

- [ ] T014 [US1] Implementar um mecanismo controlado de importação no Laravel que consuma a fonte estruturada validada em T011 e utilize o `NutritionBaseService` para persistir os registros em `foods`, `nutrients` e `food_nutrients`, sem introduzir cálculos nutricionais

- [ ] T015 [US1] Executar o processo de importação em ambiente de desenvolvimento e comparar uma amostra dos registros persistidos com a fonte original TACO, verificando nome/descrição, categoria quando disponível, `source`, `source_code`, componentes nutricionais, unidades, valores e estados

- [ ] T016 [US1] Criar teste em `tests/Feature/NutritionBaseStorageTest.php` que verifica:
  - valor numérico armazenado com `status=EXACT` e `amount_value` preservado;
  - valor numérico zero continua sendo zero e é diferente de `TRACE`;
  - `Tr` armazenado como `TRACE` sem conversão para zero;
  - `NA` armazenado como `NA` sem conversão para zero;
  - ausência explícita na fonte armazenada como `ABSENT` somente quando aplicável;
  - `TRACE`, `NA` e `ABSENT` permanecem estados distintos;
  - nenhum registro `ABSENT` é criado apenas pela inexistência de uma relação alimento × nutriente;
  - unidade do componente nutricional é preservada;
  - `source` identifica a TACO;
  - `source_code` é preservado quando disponível;
  - a constraint única `(food_id, nutrient_id)` impede duplicatas

**Checkpoint**: User Story 1 completamente funcional e testável. Base populada com dados da TACO e estados preservados.

---

## Phase 4: User Story 2 — Consulta de Alimentos (Priority: P2)

**Goal**: Usuários podem pesquisar alimentos pelo nome usando busca parcial que ignora maiúsculas/minúsculas e acentuação, recebendo a composição nutricional por 100 g com os estados originais da fonte preservados.

**Independent Test**: Pesquisar por um termo sem acentuação deve localizar alimentos cujo nome possua acentuação correspondente; diferenças entre maiúsculas e minúsculas não devem impedir resultados; a resposta deve preservar os dados nutricionais e seus respectivos estados.

### Contrato — User Story 2

- [ ] T017 [US2] Atualizar os artefatos em `specs/001-taco-nutrition-base/contracts/` para documentar explicitamente a consulta `GET /api/foods` com o parâmetro de query `q`, vinculando a resposta ao formato definido em `search-response.json`

- [ ] T018 [US2] Registrar no contrato, antes da implementação do Controller, o comportamento aprovado pela equipe para o parâmetro `q` quando estiver ausente, vazio ou contiver apenas espaços, sem adicionar novos requisitos de pesquisa além do definido na specification

### Implementação — User Story 2

- [ ] T019 [US2] Adicionar método de busca em `app/Services/NutritionBaseService.php` que executa pesquisa parcial em `foods.name`, utilizando recursos do PostgreSQL compatíveis com a decisão documentada no `research.md` para ignorar diferenças entre maiúsculas/minúsculas e acentuação, incluindo no resultado os registros de `food_nutrients` e seus respectivos `nutrients`

- [ ] T020 [US2] Criar `app/Http/Requests/FoodSearchRequest.php` aplicando somente as regras de validação do parâmetro `q` aprovadas e documentadas em T018

- [ ] T021 [US2] Criar `app/Http/Controllers/FoodController.php` com método `search(FoodSearchRequest $request)` responsável apenas por receber a requisição validada, delegar a busca ao `NutritionBaseService` e retornar resposta JSON conforme o contrato em `specs/001-taco-nutrition-base/contracts/search-response.json`

- [ ] T022 [US2] Registrar rota em `routes/api.php`: `GET /api/foods` com parâmetro `q`, apontando para o método de busca do `FoodController`

- [ ] T023 [US2] Criar teste em `tests/Feature/FoodSearchTest.php` que verifica:
  - busca por termo parcial retorna alimentos correspondentes;
  - busca ignora diferenças entre maiúsculas e minúsculas;
  - busca ignora diferenças de acentuação;
  - busca sem resultados retorna lista vazia;
  - comportamento de `q` ausente, vazio ou composto por espaços corresponde exatamente ao definido no contrato em T018;
  - resposta JSON segue o contrato;
  - `source` e `source_code` são preservados;
  - componentes nutricionais retornam `name`, `unit`, `amount_value` e `status`;
  - estados `EXACT`, `TRACE`, `NA` e `ABSENT` não são convertidos silenciosamente para zero

**Checkpoint**: User Story 2 completamente funcional. A pesquisa retorna dados corretos de acordo com o contrato aprovado.

---

## Phase 5: User Story 3 — Disponibilização para Receitas (Priority: P3)

**Goal**: Alimentos retornados pela base nutricional devem possuir identificação não ambígua e estável para que futuras funcionalidades, como receitas, possam referenciá-los mantendo sua rastreabilidade e dados nutricionais.

**Independent Test**: Um alimento retornado pela consulta deve possuir uma identificação estável juntamente com `source`, `source_code` quando disponível e suas informações nutricionais preservadas.

### Validação — User Story 3

- [ ] T024 [US3] Criar teste garantindo que cada alimento retornado pela consulta possua identificação não ambígua e estável, preservando `source`, `source_code` quando disponível e sua associação com os dados nutricionais, permitindo seu uso posterior como referência por outras funcionalidades sem implementar nesta feature endpoints adicionais, receitas ou relacionamentos de receita

**Checkpoint**: User Story 3 validada sem introdução de funcionalidades adicionais fora da specification.

---

## Phase Final: Polish & Cross-Cutting Concerns

**Purpose**: Validação end-to-end e encerramento da feature.

- [ ] T025 [P] Executar `php artisan test` e confirmar que todos os testes relacionados à feature passam sem falhas

- [ ] T026 Executar o roteiro de validação descrito em `specs/001-taco-nutrition-base/quickstart.md` e confirmar os critérios definidos

- [ ] T027 Revisar que nenhum código desta feature:
  - realiza cálculos nutricionais;
  - interpreta matematicamente `TRACE`, `NA` ou `ABSENT`;
  - converte esses estados para zero;
  - implementa receitas;
  - implementa porções;
  - implementa %VD;
  - implementa regras de arredondamento;
  - implementa rotulagem;
  - implementa PDF/PNG;
  - implementa autenticação;
  - cria registros `ABSENT` por produto cartesiano;
  - acopla as entidades principais do domínio ao nome TACO por meio de classes como `TacoFood`, `TacoNutrient` ou equivalentes

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: Sem dependências — pode iniciar imediatamente.

- **Phase 2 (Foundational)**: Depende da Phase 1 — **bloqueia todas as User Stories**.

- **Phase 3 (US1 — P1)**: Depende da Phase 2.

- **Phase 4 (US2 — P2)**: Depende da Phase 2. A implementação da busca pode utilizar dados de teste, mas a validação com dados reais depende da Base Nutricional populada pela US1.

- **Phase 5 (US3 — P3)**: Depende da User Story 2, pois valida a identificação estável dos alimentos disponibilizados pela consulta.

- **Phase Final**: Depende das User Stories da feature concluídas.

### Within Each User Story

#### User Story 1

```text
Fonte estruturada
    ↓
NutritionBaseService
    ↓
Mapeamento dos estados
    ↓
Mecanismo de importação
    ↓
Execução e comparação
    ↓
Testes
```

#### User Story 2

```text
Contrato HTTP
    ↓
Regra do parâmetro q
    ↓
NutritionBaseService.search()
    ↓
FoodSearchRequest
    ↓
FoodController
    ↓
Route
    ↓
Testes
```

#### User Story 3

```text
Consulta existente
    ↓
Validação da identificação estável
```

---

## Parallel Opportunities

- T007 e T008 (`Food` e `Nutrient`) podem ser desenvolvidos em paralelo após as migrations correspondentes estarem definidas.

- Partes dos testes de T016 podem ser preparadas enquanto o mecanismo de importação é implementado, desde que utilizem apenas o comportamento já aprovado.

- T025 e a preparação para T026 podem ocorrer em paralelo após todas as User Stories estarem concluídas.

---

## Parallel Example: Foundational Phase

```text
# T007 e T008 podem ser desenvolvidas em paralelo:

Task: "Criar app/Models/Food.php"

Task: "Criar app/Models/Nutrient.php"
```

---

## Implementation Strategy

### Incremento Inicial da Feature — User Story 1

1. Completar **Phase 1**: Setup

2. Completar **Phase 2**: Foundational

3. Completar **Phase 3**: User Story 1 — armazenamento/importação da Base Nutricional

4. **PARAR E VALIDAR**:
   - base populada;
   - fonte preservada;
   - componentes preservados;
   - unidades preservadas;
   - `EXACT`, `TRACE`, `NA` e `ABSENT` corretamente distintos;
   - ausência de conversão silenciosa para zero

5. Seguir para US2 somente após a validação da US1

### Entrega Incremental

1. Setup + Foundational → infraestrutura, schema e Models prontos

2. US1 → Base Nutricional criada, importada e testada

3. US2 → Pesquisa de alimentos funcional conforme contrato

4. US3 → Identificação estável validada para integração futura com receitas

5. Phase Final → validação completa da feature

---

## Notes

- `[P]` = tasks em arquivos diferentes e sem dependências diretas podem ser executadas em paralelo.

- `[US1]`, `[US2]`, `[US3]` = mapeia a task à User Story correspondente da specification.

- Nenhuma task desta lista implementa receitas, cálculos nutricionais, porções, %VD, arredondamento, rotulagem, PDF/PNG ou autenticação.

- O campo `status` nunca deve ser automaticamente convertido para representação numérica zero.

- `EXACT`, `TRACE`, `NA` e `ABSENT` representam estados distintos.

- Registros `ABSENT` só devem ser criados quando a própria estrutura da fonte apresentar uma posição explícita sem valor — jamais via produto cartesiano entre todos os alimentos e nutrientes.

- A TACO é a fonte desta feature, mas as entidades principais permanecem `Food`, `Nutrient` e `FoodNutrient`.

- O mecanismo concreto de importação não é definido obrigatoriamente como Seeder, Controller ou endpoint HTTP; deve apenas respeitar os artefatos aprovados da feature.