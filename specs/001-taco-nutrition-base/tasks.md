# Tasks: TACO Nutrition Base (001-taco-nutrition-base)

**Feature**: `001-taco-nutrition-base`

**Branch**: `001-taco-nutrition-base`

**Spec**: `specs/001-taco-nutrition-base/spec.md`

**Plan**: `specs/001-taco-nutrition-base/plan.md`

---

## Phase 1: Setup (Infraestrutura Compartilhada)

**Purpose**: Preparação do banco de dados e habilitação dos recursos necessários.

- [X] T001 Habilitar a extensão `unaccent` do PostgreSQL via migration em `database/migrations/[timestamp]_enable_unaccent_extension.php`

- [X] T002 Verificar que o arquivo `.env` contém a conexão PostgreSQL configurada para o ambiente de desenvolvimento

**Checkpoint**: Infraestrutura do banco pronta. As migrations de schema podem ser aplicadas.

---

## Phase 2: Foundational (Pré-requisitos Bloqueantes)

**Purpose**: Definição da precisão necessária, criação do schema relacional e dos Models base dos quais todas as User Stories dependem.

- [X] T003 Analisar os valores numéricos presentes na fonte autoritativa `resources/data/taco/source/taco_4_edicao_ampliada_e_revisada.pdf` e determinar a representação `NUMERIC`/`DECIMAL` necessária para preservar os valores originais sem arredondamento ou perda de precisão

- [X] T004 Criar migration `database/migrations/[timestamp]_create_foods_table.php` com os campos: `id` (PK padrão Laravel), `name` (string, not null), `category` (string, nullable), `source` (string, not null), `source_code` (string, nullable), `timestamps`

- [X] T005 Criar migration `database/migrations/[timestamp]_create_nutrients_table.php` com os campos: `id` (PK padrão Laravel), `name` (string, not null), `unit` (string, not null), `timestamps`

- [X] T006 Criar migration `database/migrations/[timestamp]_create_food_nutrients_table.php` com os campos: `id` (PK padrão Laravel), `food_id` (FK -> `foods.id`, cascade delete), `nutrient_id` (FK -> `nutrients.id`, cascade delete), `amount_value` (numérico nullable utilizando a precisão definida em T003), `status` (string, not null, valores permitidos: `EXACT`, `TRACE`, `NA`, `ABSENT`), `timestamps`; com unique constraint em `(food_id, nutrient_id)`

- [X] T007 [P] Criar Eloquent Model `app/Models/Food.php` com `$fillable` contendo `name`, `category`, `source`, `source_code`; relacionamento `hasMany(FoodNutrient::class)`

- [X] T008 [P] Criar Eloquent Model `app/Models/Nutrient.php` com `$fillable` contendo `name`, `unit`; relacionamento `hasMany(FoodNutrient::class)`

- [X] T009 Criar Eloquent Model `app/Models/FoodNutrient.php` com `$fillable` contendo `food_id`, `nutrient_id`, `amount_value`, `status`; relacionamentos `belongsTo(Food::class)` e `belongsTo(Nutrient::class)`; constante ou enum para os estados `EXACT`, `TRACE`, `NA`, `ABSENT`

- [X] T010 Executar `php artisan migrate` e confirmar que as três tabelas foram criadas com o schema correto

**Checkpoint**: Schema e Models criados. Nenhum dado nutricional precisa estar populado ainda. As User Stories podem começar.

---

## Phase 3: User Story 1 — Armazenamento de Dados da TACO (Priority: P1)

**Goal**: O sistema deve possuir uma base nutricional formada a partir dos dados da TACO, preservando todos os componentes nutricionais disponíveis na fonte, incluindo os estados `EXACT`, `TRACE`, `NA`, `ABSENT` e `REEVALUATION`, sem conversão silenciosa para zero.

**Independent Test**: Verificar que os dados armazenados correspondem à fonte TACO, que `status` distingue corretamente os diferentes estados, que valores numéricos são preservados e que `source` identifica a origem TACO.

### Adequação do Estado

- [X] T011 [US1] Criar migration adicional não destrutiva em `database/migrations/[timestamp]_add_reevaluation_status_to_food_nutrients_table.php` para permitir `REEVALUATION` em `food_nutrients.status`, preservando os estados `EXACT`, `TRACE`, `NA` e `ABSENT`, sem reescrever migrations já aplicadas nem alterar dados existentes

- [X] T012 [US1] Atualizar `app/Models/FoodNutrient.php` para incluir `REEVALUATION` entre os estados permitidos, preservando as constantes ou enumerações já implementadas para `EXACT`, `TRACE`, `NA` e `ABSENT`

### Preparação da Fonte

- [X] T013 [US1] Preparar e validar, exclusivamente a partir da fonte autoritativa `resources/data/taco/source/taco_4_edicao_ampliada_e_revisada.pdf` — Tabela Brasileira de Composição de Alimentos — TACO, 4ª edição revisada e ampliada, NEPA/UNICAMP, 2011 — os artefatos `resources/data/taco/processed/foods.csv` com os alimentos e dados aprovados para `Food`, `resources/data/taco/processed/nutrients.csv` com os componentes nutricionais e suas unidades, e `resources/data/taco/processed/food_nutrients.csv` com as relações entre alimento e nutriente, preservando valores e estados `EXACT`, `TRACE`, `NA`, `ABSENT` e `REEVALUATION`

### Implementação — User Story 1

- [X] T014 [US1] Criar `app/Services/NutritionBaseService.php` com a responsabilidade de persistir os dados da base nutricional, recebendo os dados estruturados de um alimento e seus componentes nutricionais; criar ou atualizar `Food` pela identidade `source + source_code`, preservando `foods.id` na reimportação e tratando `source_code` ausente como problema de importação sem fallback pelo nome; reutilizar `Nutrient` pela identidade `name + unit`; e criar ou atualizar `FoodNutrient` pela combinação única `food_id + nutrient_id`, sem duplicatas e sem converter estados qualitativos para zero

- [X] T015 [US1] Implementar no `NutritionBaseService` a lógica de mapeamento definida no `data-model.md`: valor numérico → `status=EXACT` e `amount_value=<valor>`; `Tr` → `status=TRACE` e ausência de valor numérico; `NA` → `status=NA` e ausência de valor numérico; célula explicitamente existente na estrutura da fonte e sem valor → `status=ABSENT` e ausência de valor numérico; `*` → `status=REEVALUATION` e ausência de valor numérico; nunca converter `REEVALUATION` para zero nem criar registros `ABSENT` via produto cartesiano entre alimentos e nutrientes

- [X] T016 [US1] Implementar o Laravel Artisan Command `nutrition:import-taco`, executado por `php artisan nutrition:import-taco`, para consumir `resources/data/taco/processed/foods.csv`, `resources/data/taco/processed/nutrients.csv` e `resources/data/taco/processed/food_nutrients.csv` e delegar ao `NutritionBaseService` a persistência e as regras de importação, reimportação e upsert já aprovadas, sem conter cálculos nutricionais nem novas regras de domínio

- [X] T017 [US1] Executar `php artisan nutrition:import-taco` em ambiente de desenvolvimento e realizar validação automatizada completa dos registros processados contra `resources/data/taco/processed/foods.csv`, `resources/data/taco/processed/nutrients.csv` e `resources/data/taco/processed/food_nutrients.csv`, verificando todos os componentes esperados, nome/descrição, categoria quando disponível, `source`, `source_code`, unidades, valores e estados, incluindo `REEVALUATION`; uma comparação manual por amostra pode ser feita apenas como verificação adicional

- [X] T018 [US1] Criar teste em `tests/Feature/NutritionBaseStorageTest.php` que verifica:
  - valor numérico armazenado com `status=EXACT` e `amount_value` preservado;
  - valor numérico zero continua sendo zero e é diferente de `TRACE`;
  - `Tr` armazenado como `TRACE` sem conversão para zero;
  - `NA` armazenado como `NA` sem conversão para zero;
  - ausência explícita na fonte armazenada como `ABSENT` somente quando aplicável;
  - `*` armazenado como `REEVALUATION` com `amount_value` nulo;
  - `REEVALUATION` permanece diferente de `EXACT`;
  - `REEVALUATION` permanece diferente de `TRACE`;
  - `REEVALUATION` permanece diferente de `NA`;
  - `REEVALUATION` permanece diferente de `ABSENT`;
  - `REEVALUATION` nunca é convertido para zero;
  - `EXACT`, `TRACE`, `NA`, `ABSENT` e `REEVALUATION` permanecem estados distintos;
  - nenhum registro `ABSENT` é criado apenas pela inexistência de uma relação alimento × nutriente;
  - unidade do componente nutricional é preservada;
  - `source` identifica a TACO;
  - `source_code` preserva o código original da TACO e sua ausência em registro que deveria representar um alimento causa problema de importação sem fallback silencioso pelo nome;
  - reimportação com o mesmo `source + source_code` atualiza o `Food` existente sem duplicata e preserva `foods.id`;
  - reimportação reutiliza o `Nutrient` existente quando `name + unit` coincidem, sem duplicata;
  - a constraint única `(food_id, nutrient_id)` impede duplicatas e a reimportação atualiza ou cria a associação correspondente;
  - todos os componentes e estados esperados da fonte estruturada são preservados em todos os registros processados;
  - os valores nutricionais representam `100 g de parte comestível`

**Checkpoint**: User Story 1 completamente funcional e testável. Base populada com dados da TACO e estados preservados.

---

## Phase 4: User Story 2 — Consulta de Alimentos (Priority: P2)

**Goal**: Usuários podem pesquisar alimentos pelo nome usando busca parcial que ignora maiúsculas/minúsculas e acentuação, recebendo a composição nutricional por 100 g com os estados originais da fonte preservados.

**Independent Test**: Pesquisar por um termo sem acentuação deve localizar alimentos cujo nome possua acentuação correspondente; diferenças entre maiúsculas e minúsculas não devem impedir resultados; a resposta deve preservar os dados nutricionais e seus respectivos estados.

### Contrato — User Story 2

- [ ] T019 [US2] Confirmar que `specs/001-taco-nutrition-base/contracts/search-foods.md` documenta o endpoint `GET /api/foods`, o parâmetro obrigatório `q` e a referência ao formato de resposta definido em `search-response.json`.

- [ ] T020 [US2] Confirmar que o contrato documenta que os espaços externos de `q` são removidos antes da validação e antes de o valor ser entregue ao serviço de busca e que, após essa normalização, um valor com conteúdo executa a busca, enquanto um valor ausente ou vazio falha por validação sem executar pesquisa.

### Implementação — User Story 2

- [ ] T021 [US2] Adicionar método de busca em `app/Services/NutritionBaseService.php` que executa pesquisa parcial em `foods.name`, utilizando recursos do PostgreSQL compatíveis com a decisão documentada no `research.md` para ignorar diferenças entre maiúsculas/minúsculas e acentuação, incluindo no resultado os registros de `food_nutrients` e seus respectivos `nutrients`

- [ ] T022 [US2] Criar `app/Http/Requests/FoodSearchRequest.php` para remover os espaços externos de `q` antes da validação e antes de entregar o valor ao serviço de busca, normalizando por exemplo `" maca "` para `"maca"`, e aplicar somente as regras de validação aprovadas e documentadas em T020; quando o valor normalizado ficar vazio, a requisição deve falhar por validação e nenhuma pesquisa deve ser executada

- [ ] T023 [US2] Criar `app/Http/Controllers/FoodController.php` com método `search(FoodSearchRequest $request)` responsável apenas por receber a requisição validada, delegar a busca ao `NutritionBaseService` e retornar resposta JSON conforme o contrato em `specs/001-taco-nutrition-base/contracts/search-response.json`

- [ ] T024 [US2] Registrar rota em `routes/api.php`: `GET /api/foods` com parâmetro `q`, apontando para o método de busca do `FoodController`

- [ ] T025 [US2] Criar teste em `tests/Feature/FoodSearchTest.php` que verifica:
  - busca por termo parcial retorna alimentos correspondentes;
  - busca ignora diferenças entre maiúsculas e minúsculas;
  - busca ignora diferenças de acentuação;
  - busca sem resultados retorna lista vazia;
  - `" maca "` é normalizado para `"maca"` antes da validação e o serviço de busca recebe o valor normalizado;
  - comportamento de `q` ausente, vazio ou composto por espaços corresponde exatamente ao definido no contrato em T020;
  - resposta JSON segue o contrato;
  - `source` e `source_code` são preservados;
  - componentes nutricionais retornam `name`, `unit`, `amount_value` e `status`;
  - estados `EXACT`, `TRACE`, `NA`, `ABSENT` e `REEVALUATION` não são convertidos silenciosamente para zero;
  - valores nutricionais retornados representam `100 g de parte comestível`

**Checkpoint**: User Story 2 completamente funcional. A pesquisa retorna dados corretos de acordo com o contrato aprovado.

---

## Phase 5: User Story 3 — Disponibilização para Receitas (Priority: P3)

**Goal**: Alimentos retornados pela base nutricional devem possuir identificação não ambígua e estável para que futuras funcionalidades, como receitas, possam referenciá-los mantendo sua rastreabilidade e dados nutricionais.

**Independent Test**: Um alimento retornado pela consulta deve possuir uma identificação estável juntamente com `source`, `source_code` quando disponível e suas informações nutricionais preservadas.

### Validação — User Story 3

- [ ] T026 [US3] Criar teste garantindo que cada alimento retornado pela consulta possua identificação não ambígua e estável, preservando `source`, `source_code` e sua associação com os dados nutricionais, permitindo seu uso posterior como referência por outras funcionalidades sem implementar nesta feature endpoints adicionais, receitas ou relacionamentos de receita

**Checkpoint**: User Story 3 validada sem introdução de funcionalidades adicionais fora da specification.

---

## Phase Final: Polish & Cross-Cutting Concerns

**Purpose**: Validação end-to-end e encerramento da feature.

- [ ] T027 [P] Executar `php artisan test` e confirmar que todos os testes relacionados à feature passam sem falhas

- [ ] T028 Executar o roteiro de validação descrito em `specs/001-taco-nutrition-base/quickstart.md` e confirmar os critérios definidos

- [ ] T029 Revisar que nenhum código desta feature:
  - realiza cálculos nutricionais;
  - interpreta matematicamente `TRACE`, `NA`, `ABSENT` ou `REEVALUATION`;
  - converte esses estados para zero;
  - implementa receitas;
  - implementa porções;
  - implementa %VD;
  - implementa regras de arredondamento;
  - implementa rotulagem;
  - implementa PDF/PNG;
  - implementa autenticação;
  - cria registros `ABSENT` por produto cartesiano;
  - acopla as entidades principais do domínio ao nome da fonte TACO

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
Fonte oficial TACO (PDF)
    ↓
T011/T012
Migration e atualização necessárias para suportar REEVALUATION
    ↓
T013
Preparação e validação da fonte estruturada
    ↓
foods.csv
nutrients.csv
food_nutrients.csv
    ↓
T014/T015
NutritionBaseService + mapeamento dos estados
EXACT / TRACE / NA / ABSENT / REEVALUATION
    ↓
T016
Artisan Command nutrition:import-taco
    ↓
T017
Execução e validação integral da importação
    ↓
T018
Testes automatizados
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

- Partes dos testes de T018 podem ser preparadas enquanto o mecanismo de importação é implementado, desde que utilizem apenas o comportamento já aprovado.

- T027 e a preparação para T028 podem ocorrer em paralelo após todas as User Stories estarem concluídas.

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
   - `EXACT`, `TRACE`, `NA`, `ABSENT` e `REEVALUATION` corretamente distintos;
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

- `EXACT`, `TRACE`, `NA`, `ABSENT` e `REEVALUATION` representam estados distintos.

- Registros `ABSENT` só devem ser criados quando a própria estrutura da fonte apresentar uma posição explícita sem valor — jamais via produto cartesiano entre todos os alimentos e nutrientes.

- A TACO é a fonte desta feature, mas as entidades principais permanecem `Food`, `Nutrient` e `FoodNutrient`.

- O mecanismo concreto de importação não é definido obrigatoriamente como Seeder, Controller ou endpoint HTTP; deve apenas respeitar os artefatos aprovados da feature.
