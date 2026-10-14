# Data Model: Nutrition Calculation

## Visão geral

```text
Recipe (existente) ──origina──> NutritionCalculation
                                  │
                                  ├── N NutritionCalculationIngredient
                                  │       └── snapshot da ocorrência e do Food
                                  │
                                  └── N NutritionCalculationNutrient
                                          ├── snapshot do Nutrient
                                          ├── total e referência
                                          ├── N NutritionCalculationContribution
                                          └── N NutritionCalculationNutrientState
```

As tabelas novas formam um agregado histórico imutável. IDs das entidades de origem
são preservados para rastreabilidade; os valores necessários são copiados para o
snapshot, portanto consultas históricas não reconstruirão resultados a partir do
estado atual.

## Convenção para valores calculados

Todo valor aritmético é uma razão reduzida:

- `<prefix>_numerator`: `NUMERIC` sem precisão/escala, inteiro;
- `<prefix>_denominator`: `NUMERIC` sem precisão/escala, inteiro positivo;
- ambos nulos ou ambos presentes;
- zero canônico: `0 / 1`.

Checks PostgreSQL exigem escala zero, denominador positivo e presença conjunta. A
aplicação lê e escreve os componentes como strings e nunca usa `float`/`double`.

## NutritionCalculation

Representa uma solicitação concluída e preservada.

### Tabela `nutrition_calculations`

| Campo | Tipo PostgreSQL | Obrigatório | Regra |
|---|---|---:|---|
| `id` | `BIGINT` | sim | Chave primária |
| `recipe_id` | `BIGINT` | sim | Identificação da Recipe que originou a execução |
| `yield_value` | `NUMERIC` | sim | Snapshot positivo e finito do rendimento |
| `yield_unit` | `VARCHAR(2)` | sim | `g` ou `ml` |
| `rules_version` | `TEXT` | sim | `nutrition-calculation-v1` |
| `created_at` | timestamp Laravel | sim | Momento técnico da execução |

### Constraints

- FK `recipe_id → recipes.id`, sem cascade destrutivo.
- `yield_value > 0`, finito; `yield_unit IN ('g', 'ml')`.
- Sem `updated_at`: a execução não possui estado editável.

## NutritionCalculationIngredient

Snapshot de uma ocorrência de ingrediente; ocorrências repetidas do mesmo Food não
são deduplicadas.

### Tabela `nutrition_calculation_ingredients`

| Campo | Tipo PostgreSQL | Obrigatório | Regra |
|---|---|---:|---|
| `id` | `BIGINT` | sim | Chave primária interna |
| `nutrition_calculation_id` | `BIGINT` | sim | FK interna da execução |
| `recipe_ingredient_id` | `BIGINT` | sim | Identificador histórico da ocorrência |
| `position` | `BIGINT` | sim | Ordem capturada |
| `quantity` | `NUMERIC` | sim | Quantidade positiva em gramas |
| `unit` | `VARCHAR(1)` | sim | `g` |
| `food_id` | `BIGINT` | sim | Identificador histórico de `foods.id` |
| `food_source` | `TEXT` | sim | Rastreabilidade; para TACO, `TACO` |
| `food_source_code` | `TEXT` | não | Código da fonte capturado |

### Constraints

- FK interna com cascade para a raiz.
- `UNIQUE (nutrition_calculation_id, recipe_ingredient_id)` e
  `UNIQUE (nutrition_calculation_id, position)`.
- Quantidade positiva/finita; unidade `g`; posição positiva.
- `food_id` e `recipe_ingredient_id` não são FKs vivas: são dados do snapshot.

## NutritionCalculationNutrient

Snapshot de um Nutrient e resultado consolidado/referenciado dessa execução.

### Tabela `nutrition_calculation_nutrients`

| Campo | Tipo PostgreSQL | Obrigatório | Regra |
|---|---|---:|---|
| `id` | `BIGINT` | sim | Chave primária interna |
| `nutrition_calculation_id` | `BIGINT` | sim | FK interna |
| `nutrient_id` | `BIGINT` | sim | Identificador histórico do Nutrient |
| `nutrient_name` | `TEXT` | sim | Nome capturado |
| `nutrient_unit` | `TEXT` | sim | Unidade capturada e usada em contribuição/total/referência |
| `total_numerator` | `NUMERIC` | condicional | Soma/subtotal `EXACT` |
| `total_denominator` | `NUMERIC` | condicional | Denominador da soma/subtotal |
| `is_complete` | `BOOLEAN` | sim | Verdadeiro somente se todas as contribuições forem `EXACT` |
| `has_missing_relation` | `BOOLEAN` | sim | Ausência de ao menos um `FoodNutrient` |
| `reference_basis` | `NUMERIC` | sim | Sempre `100` |
| `reference_unit` | `VARCHAR(2)` | sim | `g` ou `ml`, conforme rendimento |
| `reference_numerator` | `NUMERIC` | condicional | Normalização do total/subtotal existente |
| `reference_denominator` | `NUMERIC` | condicional | Denominador da referência |

### Constraints

- FK interna com cascade para a raiz.
- `UNIQUE (nutrition_calculation_id, nutrient_id)`.
- Pares de razão obedecem à convenção comum.
- Total e referência são ambos ausentes quando não existe `EXACT`; se existe total,
  existe referência, inclusive para subtotal incompleto.
- `reference_unit = yield_unit` da raiz e base `100`, garantidos na criação atômica e
  validados pelo Service/testes.

## NutritionCalculationContribution

Entrada nutricional e resultado proporcional para uma ocorrência × nutriente.

### Tabela `nutrition_calculation_contributions`

| Campo | Tipo PostgreSQL | Obrigatório | Regra |
|---|---|---:|---|
| `id` | `BIGINT` | sim | Chave primária interna |
| `nutrition_calculation_ingredient_id` | `BIGINT` | sim | FK para ocorrência snapshot |
| `nutrition_calculation_nutrient_id` | `BIGINT` | sim | FK para nutriente snapshot |
| `food_nutrient_id` | `BIGINT` | não | Identificador histórico quando a relação existia |
| `input_status` | `TEXT` | não | `EXACT`, `TRACE`, `NA`, `ABSENT` ou `REEVALUATION` |
| `input_amount_value` | `NUMERIC(7,2)` | não | Valor por 100 g somente para `EXACT` |
| `is_missing_relation` | `BOOLEAN` | sim | Verdadeiro somente quando não havia relação |
| `value_numerator` | `NUMERIC` | condicional | Contribuição somente para `EXACT` |
| `value_denominator` | `NUMERIC` | condicional | Denominador da contribuição |

### Constraints

- FKs internas com cascade.
- `UNIQUE (nutrition_calculation_ingredient_id, nutrition_calculation_nutrient_id)`.
- Relação ausente: `food_nutrient_id`, status, valor de entrada e razão são nulos.
- `EXACT`: relação, status, valor de entrada e razão estão presentes.
- Estado qualitativo: relação e status presentes; valor de entrada e razão nulos.
- Check restringe status aos cinco estados aprovados.

## NutritionCalculationNutrientState

Conjunto dos estados qualitativos presentes no consolidado.

### Tabela `nutrition_calculation_nutrient_states`

| Campo | Tipo PostgreSQL | Obrigatório | Regra |
|---|---|---:|---|
| `id` | `BIGINT` | sim | Chave primária interna |
| `nutrition_calculation_nutrient_id` | `BIGINT` | sim | FK para consolidado |
| `state` | `TEXT` | sim | `TRACE`, `NA`, `ABSENT` ou `REEVALUATION` |

### Constraints

- FK interna com cascade.
- `UNIQUE (nutrition_calculation_nutrient_id, state)`.
- `EXACT` não integra o conjunto qualitativo; sua presença é representada pela razão.
- Ausência de relação não integra o conjunto; usa `has_missing_relation`.

## Regras do agregado

1. Existe exatamente uma contribuição para cada ocorrência snapshot × Nutrient.
2. Todos os nutrientes existentes no início coerente da transação são capturados.
3. `is_complete = true` implica razão numérica e nenhum estado/ausência.
4. Razão numérica com `is_complete = false` é subtotal parcial e sua referência
   normalizada também é parcial.
5. Sem `EXACT`, ambas as razões são nulas e o conjunto de estados e/ou ausência
   explica a indisponibilidade.
6. Zero `EXACT` é `0/1`, nunca ausência ou estado qualitativo.
7. O agregado é inserido em uma transação e depois não aceita update/delete.
8. Nova solicitação cria outra raiz e não modifica execuções existentes.

## Fluxo de estado

```text
solicitação válida
  → snapshot + cálculo em transação
  → execução preservada e consultável
  → estado terminal imutável
```

Não existe draft, recalculado-em-lugar, resultado atual substituível ou exclusão
funcional nesta feature.

