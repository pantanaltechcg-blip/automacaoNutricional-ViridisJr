# Data Model: Declared Values, Rounding, and Non-Significant Quantities

## Visão geral

```text
ServingDailyValueCalculation (Feature 004, existente, imutável)
  ├── vinculada a NutritionCalculation (Feature 003, existente, imutável)
  └── N DeclaredNutritionCalculation (Raiz de snapshot da Feature 005, imutável)
        ├── N DeclaredNutritionComponent (100 g/100 ml e Porção por MAP)
        │     └── N DeclaredNutritionComponentState (estados qualitativos copiados)
        ├── N DeclaredNutritionEnergyComponent (ENE recalculada em 100 g/100 ml e Porção)
        ├── N DeclaredNutritionPercentage (%VD derivado exato vs. %VD regulatório final)
        └── N DeclaredNutritionCause (ledger estruturado de bloqueios e incompletude)

RegulatoryDeclarationProfile (versionado, imutável, vinculado ao perfil 004)
  ├── 1 RegulatoryRecalculationPolicy (ordem normativa e dependências)
  ├── N RegulatoryNonSignificantRule (regras NSQ por alvo e base)
  ├── N RegulatoryRoundingRule (regras de arredondamento por alvo, base e critério)
  │     └── N RegulatoryRoundingRuleRange (faixas ordenadas e escala decimal exata)
  └── N RegulatoryDeclarationRequirement (matriz de obrigatoriedade/aplicabilidade para is_label_ready)
```

O primeiro conjunto é histórico: copia integralmente entradas exatas, estados qualitativos, regras aplicadas, evidências, valores declarados, energia final, `%VD` finais, causas e avaliação de aptidão à rotulagem (`is_label_ready`). O segundo conjunto é uma fonte técnica versionada e *deny-by-default*. Nenhuma tabela das Features 001–004 sofre alteração funcional ou de schema.

---

## Convenção de razões exatas

Todo valor racional ou threshold/incremento usa pares inteiros sem escala fixa:

- `<prefix>_numerator`: `NUMERIC` sem precisão/escala, inteiro;
- `<prefix>_denominator`: `NUMERIC` sem precisão/escala, inteiro positivo (`> 0`);
- ambos nulos ou ambos presentes (exceto faixas abertas onde `min_*` ou `max_*` pode ser nulo em par);
- zero canônico: `0 / 1`.

Checks PostgreSQL rejeitam `Infinity`, `-Infinity`, `NaN`, `scale(...) <> 0`, denominador `<= 0`, presença parcial do par e zero não canônico (`(num <> 0 OR den = 1)`). A aplicação lê/escreve strings e nunca utiliza casts `float`, `double` ou `decimal:N`.

---

## Convenção de autorização técnica composta

Todas as tabelas versionadas capazes de autorizar regras (`regulatory_declaration_profiles`, `regulatory_recalculation_policies`, `regulatory_non_significant_rules`, `regulatory_rounding_rules`, `regulatory_declaration_requirements`) adotam a tupla de evidência padrão do projeto:

- `evidence_reference` (`TEXT`) — identificador verificável da decisão/norma;
- `evidence_source` (`TEXT`) — origem documental rastreável;
- `evidence_version` (`TEXT`) — versão exata abrangida;
- `evidence_scope` (`TEXT`) — escopo exato da regra/perfil/produto/formulação;
- `evidence_kind` (`TEXT`) — `DOCUMENTED` ou `TEST_FIXTURE`;
- `validated_by` (`TEXT`) — responsável identificável;
- `validated_on` (`DATE`) — data da validação.

Constraints PostgreSQL exigem que a tupla esteja integralmente nula ou integralmente preenchida, rejeitam strings vazias e exigem tupla completa quando `technical_status = 'AUTHORIZED'` (e também quando `NOT_APPLICABLE` em requisitos/NSQ aplicáveis). O `DeclaredValuesRegulatoryGateService` valida compatibilidade semântica de escopo, versão normativa, perfil 004, produto/formulação e ambiente (`TEST_FIXTURE` aceito exclusivamente em `app()->environment('testing')`).

---

## Referências técnicas versionadas (Feature 005)

### 1. `regulatory_declaration_profiles` (`RegulatoryDeclarationProfile`)

Pacote versionado de regras de declaração, quantidades não significativas, arredondamento e ordem de recálculo vinculado a um perfil da Feature 004.

| Campo | Tipo | Regra |
|---|---|---|
| `id` | `BIGINT` | PK |
| `code` | `TEXT` | Identificador do pacote de declaração |
| `version` | `TEXT` | Versão imutável do pacote |
| `title` | `TEXT` | Nome rastreável |
| `regulatory_profile_code` | `TEXT` | Código do perfil 004 vinculado |
| `regulatory_profile_version` | `TEXT` | Versão do perfil 004 vinculado |
| `normative_source` | `TEXT` | Atos oficiais (ex.: `RDC 429/2020 + IN 75/2020`) |
| `normative_version` | `TEXT` | Versão normativa exata vinculada |
| `consulted_on` | `DATE` | Data da consulta documental |
| `scope_recipe_id` | `BIGINT` nullable | Opcional: restringe a um produto (`source_recipe_id`) |
| `scope_nutrition_calculation_id` | `BIGINT` nullable | Opcional: restringe a uma formulação (`nutrition_calculation_id`) |
| `technical_status` | `TEXT` | `AUTHORIZED` ou `BLOCKED` |
| `evidence_*` + `validated_by/on` | condicionais | Tupla de evidência padrão |
| `created_at` | `TIMESTAMP` | Momento do registro (sem `updated_at`) |

Constraints:
- `UNIQUE (code, version)`;
- `technical_status IN ('AUTHORIZED', 'BLOCKED')`;
- tupla de evidência completa obrigatória quando `technical_status = 'AUTHORIZED'`.

---

### 2. `regulatory_recalculation_policies` (`RegulatoryRecalculationPolicy`)

Define a ordem normativa de recálculo final de energia e `%VD` para o pacote de declaração.

| Campo | Tipo | Regra |
|---|---|---|
| `id` | `BIGINT` | PK |
| `declaration_profile_id` | `BIGINT` | FK -> `regulatory_declaration_profiles` (`restrictOnDelete`) |
| `policy_code` | `TEXT` | Código da política (ex.: `REC-001`) |
| `normative_order_mode` | `TEXT` | `DECLARE_COMPONENTS_THEN_ENERGY_AND_VD` |
| `energy_reference_100_input_source` | `TEXT` | `DECLARED_REFERENCE_100` |
| `energy_portion_input_source` | `TEXT` | `DECLARED_PORTION` |
| `daily_value_input_source` | `TEXT` | `DECLARED_PORTION_AND_DECLARED_ENERGY` |
| `requires_complete_energy_inputs` | `BOOLEAN` | Obrigatório `true` quando `AUTHORIZED` (`FR-008`) |
| `technical_status` | `TEXT` | `AUTHORIZED`, `BLOCKED` ou `UNAVAILABLE` |
| `source_reference` | `TEXT` nullable | Dispositivo normativo (ex.: `RDC 429/2020, art. 32, § 1º`) |
| `evidence_*` + `validated_by/on` | condicionais | Tupla de evidência padrão |

Constraints:
- `UNIQUE (declaration_profile_id)` e `UNIQUE (declaration_profile_id, policy_code)`;
- `technical_status IN ('AUTHORIZED', 'BLOCKED', 'UNAVAILABLE')`;
- `normative_order_mode IN ('DECLARE_COMPONENTS_THEN_ENERGY_AND_VD')`;
- quando `technical_status = 'AUTHORIZED'`, exige `requires_complete_energy_inputs = true`, `source_reference` não vazio e tupla de evidência completa.

---

### 3. `regulatory_non_significant_rules` (`RegulatoryNonSignificantRule`)

Regras de quantidades não significativas por alvo (`COMPONENT` ou `ENERGY`) e base (`REFERENCE_100` ou `PORTION`).

| Campo | Tipo | Regra |
|---|---|---|
| `id` | `BIGINT` | PK |
| `declaration_profile_id` | `BIGINT` | FK -> `regulatory_declaration_profiles` |
| `rule_code` | `TEXT` | Código único no perfil (ex.: `NSQ-MAP-002-PORTION`) |
| `target_kind` | `TEXT` | `COMPONENT` ou `ENERGY` |
| `map_code` | `TEXT` nullable | Obrigatório se `COMPONENT`; nulo se `ENERGY` |
| `basis` | `TEXT` | `REFERENCE_100` ou `PORTION` |
| `technical_status` | `TEXT` | `AUTHORIZED`, `BLOCKED`, `NOT_APPLICABLE` ou `UNAVAILABLE` |
| `comparison_operator` | `TEXT` nullable | `LTE` (`<=`) ou `LT` (`<`) |
| `threshold_numerator` / `threshold_denominator` | `NUMERIC` nullable | Limite exato (> 0 ou >= 0) |
| `threshold_unit` | `TEXT` nullable | Unidade esperada (`g`, `mg`, `µg`, `kcal`) |
| `declared_string` | `TEXT` nullable | Texto declarado quando NSQ (ex.: `"0"`) |
| `declared_numerator` / `declared_denominator` | `NUMERIC` nullable | Razão declarada quando NSQ (ex.: `0 / 1`) |
| `justification` | `TEXT` nullable | Obrigatória quando `technical_status = 'NOT_APPLICABLE'` |
| `source_reference` | `TEXT` nullable | Referência ao Anexo IV da IN nº 75/2020 |
| `evidence_*` + `validated_by/on` | condicionais | Tupla de evidência padrão |

Constraints:
- `UNIQUE (declaration_profile_id, rule_code)`;
- índices únicos parciais garantindo no máximo uma regra por `(declaration_profile_id, map_code, basis)` para `COMPONENT` e por `(declaration_profile_id, basis)` para `ENERGY`;
- `technical_status = 'AUTHORIZED'` exige `comparison_operator`, `threshold_*`, `threshold_unit`, `declared_string`, `declared_numerator/denominator`, `source_reference` e tupla de evidência completa;
- `technical_status = 'NOT_APPLICABLE'` exige `justification` não vazia e tupla de evidência completa.

---

### 4. `regulatory_rounding_rules` (`RegulatoryRoundingRule`)

Regras de arredondamento por alvo, base e critério de desempate.

| Campo | Tipo | Regra |
|---|---|---|
| `id` | `BIGINT` | PK |
| `declaration_profile_id` | `BIGINT` | FK -> `regulatory_declaration_profiles` |
| `rule_code` | `TEXT` | Código único no perfil (ex.: `RND-MAP-002-REF100`) |
| `target_kind` | `TEXT` | `COMPONENT`, `ENERGY` ou `DAILY_VALUE` |
| `map_code` | `TEXT` nullable | Obrigatório se `COMPONENT`; nulo nos demais |
| `vdr_code` | `TEXT` nullable | Obrigatório se `DAILY_VALUE`; nulo nos demais |
| `vdr_numerator_source` | `TEXT` nullable | Para `DAILY_VALUE`: `DECLARED_COMPONENT` ou `DECLARED_PORTION_ENERGY` |
| `basis` | `TEXT` | `REFERENCE_100`, `PORTION` ou `PERCENT_VD` |
| `unit` | `TEXT` | Unidade regulatória esperada (`g`, `mg`, `µg`, `kcal`, `%`) |
| `tie_breaker` | `TEXT` | `HALF_UP` |
| `technical_status` | `TEXT` | `AUTHORIZED`, `BLOCKED` ou `UNAVAILABLE` |
| `source_reference` | `TEXT` nullable | Referência ao Anexo III da IN nº 75/2020 |
| `evidence_*` + `validated_by/on` | condicionais | Tupla de evidência padrão |

Constraints:
- `UNIQUE (declaration_profile_id, rule_code)`;
- índices únicos parciais por `(declaration_profile_id, basis, map_code)` em `COMPONENT`, `(declaration_profile_id, basis)` em `ENERGY` e `(declaration_profile_id, basis, vdr_code)` em `DAILY_VALUE`;
- `basis` compatível com `target_kind` (`COMPONENT`/`ENERGY` -> `REFERENCE_100`/`PORTION`; `DAILY_VALUE` -> `PERCENT_VD`);
- `technical_status = 'AUTHORIZED'` exige `source_reference` não vazio e tupla de evidência completa.

---

### 5. `regulatory_rounding_rule_ranges` (`RegulatoryRoundingRuleRange`)

Faixas numéricas ordenadas pertencentes a uma `RegulatoryRoundingRule`.

| Campo | Tipo | Regra |
|---|---|---|
| `id` | `BIGINT` | PK |
| `rounding_rule_id` | `BIGINT` | FK -> `regulatory_rounding_rules` (`restrictOnDelete`) |
| `range_order` | `INTEGER` | Ordem estritamente positiva (`>= 1`) |
| `min_numerator` / `min_denominator` | `NUMERIC` nullable | Limite inferior exato (`null` = sem limite inferior) |
| `min_inclusive` | `BOOLEAN` | Inclusivo (`>=`) ou exclusivo (`>`) quando `min_*` presente |
| `max_numerator` / `max_denominator` | `NUMERIC` nullable | Limite superior exato (`null` = sem limite superior) |
| `max_inclusive` | `BOOLEAN` | Inclusivo (`<=`) ou exclusivo (`<`) quando `max_*` presente |
| `step_numerator` / `step_denominator` | `NUMERIC` | Incremento racional estritamente positivo (`> 0`) |
| `decimal_places` | `INTEGER` | Casas decimais de saída (`>= 0`) |

Constraints:
- `UNIQUE (rounding_rule_id, range_order)`;
- `range_order >= 1`, `decimal_places >= 0`;
- `step_numerator > 0`, `step_denominator > 0`, `scale(...) = 0`;
- validações de pares racionais para `min_*` e `max_*`;
- o `DeclaredValuesRegulatoryGateService` valida que regras `AUTHORIZED` possuem ao menos 1 faixa, sem sobreposição entre faixas e com exatamente uma faixa aplicável para o valor avaliado.

---

### 6. `regulatory_declaration_requirements` (`RegulatoryDeclarationRequirement`)

Define quais componentes, energia e `%VD` são obrigatórios, condicionais ou voluntários na avaliação de `is_label_ready` (`FR-011`).

| Campo | Tipo | Regra |
|---|---|---|
| `id` | `BIGINT` | PK |
| `declaration_profile_id` | `BIGINT` | FK -> `regulatory_declaration_profiles` |
| `requirement_code` | `TEXT` | Código único no perfil |
| `target_kind` | `TEXT` | `COMPONENT`, `ENERGY` ou `DAILY_VALUE` |
| `map_code` | `TEXT` nullable | Obrigatório se `COMPONENT` |
| `vdr_code` | `TEXT` nullable | Obrigatório se `DAILY_VALUE` |
| `requirement_level` | `TEXT` | `MANDATORY`, `CONDITIONAL` ou `VOLUNTARY` |
| `applicability_status` | `TEXT` | `APPLICABLE`, `NOT_APPLICABLE` ou `UNKNOWN` |
| `justification` | `TEXT` nullable | Obrigatória se `NOT_APPLICABLE` |
| `technical_status` | `TEXT` | `AUTHORIZED`, `BLOCKED` ou `UNAVAILABLE` |
| `source_reference` | `TEXT` nullable | Referência normativa |
| `evidence_*` + `validated_by/on` | condicionais | Tupla de evidência padrão |

Constraints:
- `UNIQUE (declaration_profile_id, requirement_code)`;
- `NOT_APPLICABLE` exige `justification` não vazia e evidência completa;
- `AUTHORIZED` exige `source_reference` e evidência completa.

---

## Agregado histórico de snapshot (Feature 005)

### 7. `declared_nutrition_calculations` (`DeclaredNutritionCalculation`)

Raiz imutável de uma apuração de valores declarados vinculada a uma única execução `ServingDailyValueCalculation`.

| Campo | Obrigatório | Regra |
|---|---:|---|
| `id` | sim | PK |
| `serving_daily_value_calculation_id` | sim | FK -> `serving_daily_value_calculations` (`restrictOnDelete`, sem `unique`) |
| `source_nutrition_calculation_id` | sim | FK -> `nutrition_calculations` (`restrictOnDelete`) |
| `source_recipe_id` | sim | Cópia histórica do produto |
| `source_003_rules_version` / `source_004_rules_version` | sim | Versões das execuções de origem |
| `source_yield_value` / `source_yield_unit` | sim | Rendimento copiado da origem |
| `portion_value` / `portion_unit` | sim | Porção copiada da Feature 004 |
| `household_measure_mode` / `household_measure` / `portion_justification` | condicional | Medida caseira copiada da Feature 004 |
| `portion_reference_technical_status` + `portion_evidence_*` + `portion_validated_by/on` | sim | Snapshot da autorização da porção 004 |
| `regulatory_profile_code/version/status/normative_source/normative_version` | sim | Snapshot do perfil da Feature 004 |
| `declaration_profile_*` (código, versão, título, status, fonte/versão normativa, consulta, escopo, evidência) | sim | Snapshot completo do `RegulatoryDeclarationProfile` |
| `recalculation_policy_*` (código, status, ordem normativa, source_reference, evidência) | condicional | Snapshot da `RegulatoryRecalculationPolicy` quando presente |
| `source_portion_energy_numerator/denominator/unit/is_complete/availability` | condicional | Energia derivada exata copiada da Feature 004 (`FR-003`) |
| `reference_100_exact_energy_numerator/denominator` | não | Energia exata recalculada sobre declarados em `100 g/100 ml` |
| `reference_100_energy_nsq_applied` | sim | Boolean |
| `reference_100_declared_energy_string` | não | String decimal final (ex.: `"245"`) |
| `reference_100_declared_energy_numerator/denominator` | não | Razão exata da energia já arredondada (`100 g/100 ml`) |
| `reference_100_energy_unit` | não | `'kcal'` quando disponível |
| `reference_100_energy_is_complete` | sim | Boolean (`false` se qualquer entrada incompleta/bloqueada) |
| `reference_100_energy_availability` | sim | `'AVAILABLE'` ou `'UNAVAILABLE'` (`FR-008`: sem energia parcial) |
| `reference_100_energy_nsq_*` / `reference_100_energy_rounding_*` | condicional | Snapshot das regras NSQ/arredondamento e evidências aplicadas em `100 g/100 ml` |
| `portion_exact_energy_numerator/denominator` | não | Energia exata recalculada sobre declarados na porção |
| `portion_energy_nsq_applied` | sim | Boolean |
| `portion_declared_energy_string` | não | String decimal final na porção |
| `portion_declared_energy_numerator/denominator` | não | Razão exata da energia já arredondada na porção |
| `portion_energy_unit` | não | `'kcal'` quando disponível |
| `portion_energy_is_complete` | sim | Boolean |
| `portion_energy_availability` | sim | `'AVAILABLE'` ou `'UNAVAILABLE'` |
| `portion_energy_nsq_*` / `portion_energy_rounding_*` | condicional | Snapshot das regras NSQ/arredondamento e evidências aplicadas na porção |
| `is_label_ready` | sim | Boolean global (`FR-011`) |
| `rules_version` | sim | `'declared-values-rounding-v1'` |
| `created_at` | sim | Timestamp de criação (sem `updated_at`) |

Cada `POST` cria um novo registro raiz (`id` próprio), sem sobrescrever execuções anteriores (`FR-013`).

---

### 8. `declared_nutrition_components` (`DeclaredNutritionComponent`)

Uma linha por `MAP` considerada, preservando lado a lado os valores exatos de entrada, regras aplicadas e valores declarados para `100 g/100 ml` e para `porção`.

Principais grupos de campos:
- **Vínculo e metadados `MAP`**: `declared_nutrition_calculation_id`, `serving_daily_value_regulatory_result_id` (FK opcional -> 004), `map_code`, `component_name`, `map_technical_status`, `nutrient_id`, `nutrient_name`, `source_unit`, `regulatory_unit`, `map_source_reference`, `map_evidence_*`, `conversion_status`, `conversion_numerator/denominator`, `conversion_evidence_*`, `source_is_complete`, `source_has_missing_relation`.
- **Base `REFERENCE_100` (`reference_100_*`)**:
  - `source_numerator/denominator` (de `NutritionCalculationNutrient->reference_*`);
  - `regulatory_numerator/denominator` (convertido em `regulatory_unit`, antes de NSQ/arredondamento);
  - `is_exact_zero` (boolean — true somente quando completo e `0/1` antes de NSQ);
  - `nsq_rule_code`, `nsq_rule_status`, `nsq_applied`, `nsq_evidence_*`;
  - `rounding_rule_code`, `rounding_rule_status`, `rounding_tie_breaker`, `rounding_step_numerator/denominator`, `rounding_decimal_places`, `rounding_evidence_*`;
  - `declared_string`, `declared_numerator/denominator`, `is_complete`, `availability` (`AVAILABLE` | `UNAVAILABLE`).
- **Base `PORTION` (`portion_*`)**:
  - mesmos campos simétricos alimentados pelos dados por porção da Feature 004 (`ServingDailyValueNutrient` / `ServingDailyValueRegulatoryResult`).

Constraint: `UNIQUE (declared_nutrition_calculation_id, map_code)`.

---

### 9. `declared_nutrition_component_states` (`DeclaredNutritionComponentState`)

Cópia imutável dos estados qualitativos (`TRACE`, `NA`, `ABSENT`, `REEVALUATION`) da origem nutricional vinculada a cada `DeclaredNutritionComponent`.

Constraint: `UNIQUE (declared_nutrition_component_id, state)`.

---

### 10. `declared_nutrition_energy_components` (`DeclaredNutritionEnergyComponent`)

Snapshot de cada `ENE` avaliado no recálculo da energia final em ambas as bases (`REFERENCE_100` e `PORTION`).

Campos: `declared_nutrition_calculation_id`, `basis` (`REFERENCE_100` | `PORTION`), `ene_code`, `component_name`, `map_code`, `applicability_status`, `participation` (`INCLUDED`, `NON_APPLICABLE`, `BLOCKED`), `factor_numerator/denominator`, `factor_input_unit`, `factor_output_unit`, `input_declared_string`, `input_declared_numerator/denominator` (o valor **declarado** do componente usado no recálculo), `contribution_numerator/denominator` (`input_declared * factor`), `ene_technical_status`, `ene_source_reference`, `ene_evidence_*`, `applicability_evidence_*`, `non_applicability_justification`, `overlap_group`, `overlap_rule_code`, `overlap_rule_status`.

Constraint: `UNIQUE (declared_nutrition_calculation_id, basis, ene_code)`.

---

### 11. `declared_nutrition_percentages` (`DeclaredNutritionPercentage`)

Snapshot de cada linha `VDR` considerada, preservando lado a lado o `%VD` derivado exato da Feature 004 e o `%VD` regulatório final recalculado a partir dos valores declarados.

Campos: `declared_nutrition_calculation_id`, `serving_daily_value_percentage_id` (FK opcional -> 004), `vdr_code`, `component_name`, `map_code`, `vdr_technical_status`, `vdr_numerator/denominator`, `vdr_unit`, `vdr_source_reference`, `vdr_source_version`, `vdr_evidence_*`, `source_derived_percentage_numerator/denominator`, `source_derived_availability`, `numerator_identity`, `input_declared_string`, `input_declared_numerator/denominator`, `unrounded_percentage_numerator/denominator`, `rounding_rule_code`, `rounding_rule_status`, `rounding_tie_breaker`, `rounding_step_numerator/denominator`, `rounding_decimal_places`, `rounding_evidence_*`, `declared_percentage_string`, `declared_percentage_numerator/denominator`, `is_complete`, `availability` (`AVAILABLE` | `UNAVAILABLE` | `NO_NORMATIVE_VDR`).

Constraint: `UNIQUE (declared_nutrition_calculation_id, vdr_code)`.

---

### 12. `declared_nutrition_causes` (`DeclaredNutritionCause`)

Ledger estruturado de causas regulatórias, técnicas e de incompletude, separado de `qualitative_states`.

Campos: `declared_nutrition_calculation_id`, `scope` (`COMPONENT_REFERENCE_100`, `COMPONENT_PORTION`, `ENERGY_REFERENCE_100`, `ENERGY_PORTION`, `DAILY_VALUE`, `LABEL_READINESS`), `scope_key` (`MAP-*`, `ENERGY`, `VDR-*`, `LABEL_READINESS`), `category` (`REGULATORY_BLOCK`, `MISSING_SOURCE`, `INCOMPLETE_NUTRITION`, `QUALITATIVE_STATE_BLOCK`, `MISSING_RELATION_BLOCK`, `NO_NORMATIVE_VDR`, `UNKNOWN_APPLICABILITY`, `DOUBLE_COUNTING_RISK`, `ROUNDING_RANGE_MISMATCH`, `POLICY_BLOCK`, `PROFILE_MISMATCH`), `code`, `detail`, `source_reference`.

Constraint: `UNIQUE (declared_nutrition_calculation_id, scope, scope_key, code)`.

---

## Timestamps, Configuração Eloquent e Imutabilidade

| Model | `created_at` | `updated_at` | Configuração Eloquent |
|---|---:|---:|---|
| `RegulatoryDeclarationProfile` | sim | não | `public const UPDATED_AT = null;` |
| `RegulatoryRecalculationPolicy` | não | não | `public $timestamps = false;` |
| `RegulatoryNonSignificantRule` | não | não | `public $timestamps = false;` |
| `RegulatoryRoundingRule` | não | não | `public $timestamps = false;` |
| `RegulatoryRoundingRuleRange` | não | não | `public $timestamps = false;` |
| `RegulatoryDeclarationRequirement` | não | não | `public $timestamps = false;` |
| `DeclaredNutritionCalculation` | sim | não | `public const UPDATED_AT = null;` |
| `DeclaredNutritionComponent` | não | não | `public $timestamps = false;` |
| `DeclaredNutritionComponentState` | não | não | `public $timestamps = false;` |
| `DeclaredNutritionEnergyComponent` | não | não | `public $timestamps = false;` |
| `DeclaredNutritionPercentage` | não | não | `public $timestamps = false;` |
| `DeclaredNutritionCause` | não | não | `public $timestamps = false;` |

Uma migration dedicada cria a função PostgreSQL `prevent_declared_nutrition_mutation()` e triggers `BEFORE UPDATE OR DELETE` em **todas as 12 tabelas** acima, garantindo imutabilidade integral no banco de dados.

---

## Regras do agregado

1. Toda criação ocorre em transação PostgreSQL `REPEATABLE READ`.
2. Divergência de perfil regulatório, versão normativa ou escopo produto/formulação entre `RegulatoryDeclarationProfile` e a `ServingDailyValueCalculation` de origem rejeita a requisição antes da persistência.
3. Bloqueios em regras individuais (`NSQ`, `ROUNDING`, `POLICY`, `REQUIREMENT`) ou incompletude/bloqueio na origem não impedem criar a execução declarada; componentes independentes completos e autorizados recebem seus valores declarados.
4. Zero exato (`0/1` com `is_exact_zero = true`) é mantido distinto de zero por NSQ (`nsq_applied = true`, `is_exact_zero = false`), e ambos permanecem distintos de `null` e de `NOT_APPLICABLE`.
5. `TRACE`, `NA`, `ABSENT`, `REEVALUATION`, `has_missing_relation = true` e `is_complete = false` impedem declaração e NSQ na base afetada (`declared_* = null`).
6. Energia regulatória final exige valores declarados completos de todos os componentes aplicáveis (`FR-008`); nunca gera subtotal parcial.
7. `%VD` final exige política autorizada, VDR autorizado, regra de arredondamento autorizada e valor declarado completo na porção (ou energia final declarada na porção). `NO_NORMATIVE_VDR` permanece explícito e distinto de erro/bloqueio.
8. `is_label_ready` só é `true` quando todos os requisitos obrigatórios e aplicáveis em ambas as bases, porção, medida caseira, energia final e `%VD` aplicáveis estão completos e tecnicamente autorizados.
