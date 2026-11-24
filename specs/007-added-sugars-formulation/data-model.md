# Data Model: Added Sugars Formulation Obtaining and Validation (007-added-sugars-formulation)

**Date**: 2026-11-23  
**Feature**: `007-added-sugars-formulation`  
**Spec**: [spec.md](./spec.md)

## Overview

A modelagem adiciona 7 tabelas novas no PostgreSQL 17 para governança, versionamento imutável, validação técnica *append-only* e snapshots históricos de avaliações de açúcares adicionados, além de colunas opcionais aditivas e retrocompatíveis em `serving_daily_value_calculations` e `serving_daily_value_regulatory_results` (Feature 004) para consumo explícito em `MAP-004` e propagação para a Feature 005. Nenhuma tabela da TACO (`foods`, `nutrients`, `food_nutrients`), de receitas (`recipes`, `recipe_ingredients`) ou de execuções históricas da Feature 003 (`nutrition_calculations*`) é alterada ou mutada.

Todas as razões exatas utilizam `TEXT` para `numerator`/`denominator` (padrão `BigRational` das Features 003–005) e entradas decimais utilizam `NUMERIC` / string decimal validada, sem `float`/`double`.

---

## New Entities

### 1. `added_sugars_formulations` (`AddedSugarsFormulation`)

Cabeçalho de governança da formulação de açúcares adicionados por receita (`1:1` com `recipes`).

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | `BIGSERIAL` | PK | |
| `recipe_id` | `BIGINT` | `NOT NULL UNIQUE`, FK → `recipes(id)` `ON DELETE RESTRICT` | Uma linha de governança por receita |
| `current_version_number` | `INTEGER` | `NOT NULL`, `CHECK (current_version_number >= 1)` | Ponteiro para a versão vigente mais recente |
| `created_at` / `updated_at` | `TIMESTAMP` | `NULL` | Laravel timestamps |

---

### 2. `added_sugars_formulation_versions` (`AddedSugarsFormulationVersion`)

Versão **imutável** da formulação de açúcares adicionados de uma receita. Protegida contra `UPDATE` e `DELETE` por trigger PostgreSQL.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | `BIGSERIAL` | PK | |
| `formulation_id` | `BIGINT` | `NOT NULL`, FK → `added_sugars_formulations(id)` `ON DELETE RESTRICT` | |
| `recipe_id` | `BIGINT` | `NOT NULL`, FK → `recipes(id)` `ON DELETE RESTRICT` | Desnormalizado para integridade de escopo |
| `version_number` | `INTEGER` | `NOT NULL`, `CHECK (version_number >= 1)` | Sequencial por formulação |
| `normative_source` | `TEXT` | `NOT NULL` | Ex.: `RDC nº 429/2020 e IN nº 75/2020` |
| `normative_version` | `TEXT` | `NOT NULL` | Versão/vigência normativa adotada |
| `registered_by` | `TEXT` | `NOT NULL` | Responsável pelo registro da versão |
| `registered_on` | `DATE` | `NOT NULL` | Data do registro |
| `change_reason` | `TEXT` | `NULL` | Obrigatório quando `version_number > 1` (validado na camada de aplicação) |
| `created_at` / `updated_at` | `TIMESTAMP` | `NULL` | |

- **Unique constraints**: `UNIQUE (formulation_id, version_number)`.

---

### 3. `added_sugars_formulation_ingredients` (`AddedSugarsFormulationIngredient`)

Declaração **imutável** de açúcares adicionados para cada ingrediente de uma versão da formulação. Protegida contra `UPDATE` e `DELETE` por trigger PostgreSQL.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | `BIGSERIAL` | PK | |
| `formulation_version_id` | `BIGINT` | `NOT NULL`, FK → `added_sugars_formulation_versions(id)` `ON DELETE RESTRICT` | |
| `food_id` | `BIGINT` | `NOT NULL`, FK → `foods(id)` `ON DELETE RESTRICT` | Referência estável ao alimento do ingrediente |
| `position` | `INTEGER` | `NOT NULL`, `CHECK (position >= 1)` | Posição correspondente na receita/execução |
| `ingredient_type` | `TEXT` | `NOT NULL`, `CHECK (ingredient_type IN ('SIMPLE', 'COMPOUND_INDUSTRIALIZED'))` | Distingue simples de composto/industrializado |
| `classification` | `TEXT` | `NOT NULL`, `CHECK (classification IN ('INCLUDED', 'EXCLUDED_NORMATIVE', 'MISSING', 'CONFLICTING'))` | Classificação regulatória explícita |
| `amount_basis` | `TEXT` | `NULL`, `CHECK (amount_basis IS NULL OR amount_basis IN ('PER_100G', 'DIRECT_BATCH_GRAMS'))` | Obrigatório se e somente se `classification = 'INCLUDED'` |
| `amount_value` | `NUMERIC(14, 6)` | `NULL`, `CHECK (amount_value IS NULL OR amount_value >= 0)` | Obrigatório se e somente se `classification = 'INCLUDED'`; se `PER_100G`, `<= 100` |
| `amount_unit` | `TEXT` | `NULL`, `CHECK (amount_unit IS NULL OR amount_unit = 'g')` | Obrigatório (`g`) se e somente se `classification = 'INCLUDED'` |
| `exclusion_justification` | `TEXT` | `NULL` | Obrigatório quando `classification = 'EXCLUDED_NORMATIVE'` |
| `conflict_notes` | `TEXT` | `NULL` | Obrigatório quando `classification = 'CONFLICTING'` |
| `created_at` / `updated_at` | `TIMESTAMP` | `NULL` | |

- **Unique constraints**: `UNIQUE (formulation_version_id, position)`.
- **Check constraints**:
  - `classification = 'INCLUDED'` ⇔ `amount_basis IS NOT NULL AND amount_value IS NOT NULL AND amount_unit = 'g'`
  - `amount_basis = 'PER_100G'` ⇒ `amount_value <= 100`
  - `classification = 'EXCLUDED_NORMATIVE'` ⇒ `exclusion_justification IS NOT NULL`
  - `classification = 'CONFLICTING'` ⇒ `conflict_notes IS NOT NULL`

---

### 4. `added_sugars_formulation_evidences` (`AddedSugarsFormulationEvidence`)

Evidências documentais **imutáveis** vinculadas a cada ingrediente da versão da formulação (permite múltiplas fontes por ingrediente, inclusive para registrar conflitos entre formulário operacional e ficha técnica de fabricante). Protegida contra `UPDATE` e `DELETE` por trigger PostgreSQL.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | `BIGSERIAL` | PK | |
| `formulation_ingredient_id` | `BIGINT` | `NOT NULL`, FK → `added_sugars_formulation_ingredients(id)` `ON DELETE RESTRICT` | |
| `source_type` | `TEXT` | `NOT NULL`, `CHECK (source_type IN ('MANUFACTURER_SPEC', 'LAB_REPORT', 'OPERATIONAL_FORM', 'NORMATIVE_REFERENCE', 'TECHNICAL_TABLE', 'TEST_FIXTURE'))` | `OPERATIONAL_FORM` é evidência de entrada, nunca aprovação automática |
| `source_name` | `TEXT` | `NOT NULL` | Nome do fabricante, laboratório, formulário ou tabela |
| `document_reference` | `TEXT` | `NOT NULL` | Identificação da ficha técnica, laudo, lote ou protocolo |
| `reported_amount_basis` | `TEXT` | `NULL`, `CHECK (reported_amount_basis IS NULL OR reported_amount_basis IN ('PER_100G', 'DIRECT_BATCH_GRAMS'))` | Base informada pela fonte, se houver valor |
| `reported_amount_value` | `NUMERIC(14, 6)` | `NULL`, `CHECK (reported_amount_value IS NULL OR reported_amount_value >= 0)` | Valor declarado nesta fonte específica |
| `reported_amount_unit` | `TEXT` | `NULL`, `CHECK (reported_amount_unit IS NULL OR reported_amount_unit = 'g')` | Unidade declarada (`g`) |
| `only_total_sugars_reported` | `BOOLEAN` | `NOT NULL DEFAULT false` | Sinaliza ficha/rótulo que informa apenas açúcares totais sem discriminar adicionados |
| `recorded_by` | `TEXT` | `NOT NULL` | Responsável pelo registro da evidência |
| `recorded_on` | `DATE` | `NOT NULL` | Data da coleta/registro |
| `created_at` / `updated_at` | `TIMESTAMP` | `NULL` | |

---

### 5. `added_sugars_formulation_validations` (`AddedSugarsFormulationValidation`)

Trilha **append-only e imutável** de decisões técnicas de validação vinculadas a uma versão específica da formulação (`CQ-006`). Protegida contra `UPDATE` e `DELETE` por trigger PostgreSQL.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | `BIGSERIAL` | PK | |
| `formulation_version_id` | `BIGINT` | `NOT NULL`, FK → `added_sugars_formulation_versions(id)` `ON DELETE RESTRICT` | |
| `decision_status` | `TEXT` | `NOT NULL`, `CHECK (decision_status IN ('AUTHORIZED', 'REJECTED', 'REVOKED'))` | Estado da decisão registrada |
| `normative_source` | `TEXT` | `NOT NULL` | Ato normativo conferido |
| `normative_version` | `TEXT` | `NOT NULL` | Versão normativa conferida |
| `evidence_type` | `TEXT` | `NOT NULL`, `CHECK (evidence_type IN ('DOCUMENT', 'TECHNICAL_REVIEW', 'TEST_FIXTURE'))` | `TEST_FIXTURE` válido somente em `APP_ENV=testing` |
| `evidence_reference` | `TEXT` | `NOT NULL` | Identificador da evidência de validação |
| `evidence_source` | `TEXT` | `NOT NULL` | Origem da validação (ex.: responsável técnico Viridis) |
| `evidence_version` | `TEXT` | `NOT NULL` | Versão da evidência/parecer |
| `evidence_scope` | `TEXT` | `NOT NULL` | Deve corresponder a `recipe:{recipe_id}/added-sugars-formulation:v{version_number}` |
| `validated_by` | `TEXT` | `NOT NULL` | Responsável técnico pela decisão |
| `validated_on` | `DATE` | `NOT NULL` | Data da decisão |
| `notes` | `TEXT` | `NULL` | Observações ou motivo de rejeição/revogação |
| `created_at` / `updated_at` | `TIMESTAMP` | `NULL` | |

---

### 6. `added_sugars_evaluations` (`AddedSugarsEvaluation`)

Snapshot histórico **imutável** da avaliação de açúcares adicionados para uma versão da formulação aplicada a uma execução imutável da Feature 003 (`NutritionCalculation`). Protegida contra `UPDATE` e `DELETE` por trigger PostgreSQL.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | `BIGSERIAL` | PK | |
| `nutrition_calculation_id` | `BIGINT` | `NOT NULL`, FK → `nutrition_calculations(id)` `ON DELETE RESTRICT` | Execução nutricional da Feature 003 |
| `formulation_id` | `BIGINT` | `NOT NULL`, FK → `added_sugars_formulations(id)` `ON DELETE RESTRICT` | |
| `formulation_version_id` | `BIGINT` | `NOT NULL`, FK → `added_sugars_formulation_versions(id)` `ON DELETE RESTRICT` | |
| `validation_id` | `BIGINT` | `NULL`, FK → `added_sugars_formulation_validations(id)` `ON DELETE RESTRICT` | Última decisão vigente no momento da avaliação (ou `null` se pendente) |
| `source_recipe_id` | `BIGINT` | `NOT NULL` | Snapshot de `nutrition_calculations.recipe_id` |
| `source_rules_version` | `TEXT` | `NOT NULL` | Snapshot de `nutrition_calculations.rules_version` |
| `source_yield_value` | `NUMERIC(14, 6)` | `NOT NULL` | Snapshot do rendimento |
| `source_yield_unit` | `TEXT` | `NOT NULL`, `CHECK (source_yield_unit IN ('g', 'ml'))` | Snapshot da unidade do rendimento |
| `formulation_version_number` | `INTEGER` | `NOT NULL` | Snapshot do número da versão |
| `normative_source` | `TEXT` | `NOT NULL` | Snapshot da norma da formulação |
| `normative_version` | `TEXT` | `NOT NULL` | Snapshot da versão normativa |
| `validation_status` | `TEXT` | `NOT NULL`, `CHECK (validation_status IN ('PENDING', 'AUTHORIZED', 'REJECTED', 'REVOKED'))` | Snapshot do estado de validação vigente |
| `validation_evidence_type` | `TEXT` | `NULL` | Snapshot da evidência de validação |
| `validation_evidence_reference` | `TEXT` | `NULL` | |
| `validation_evidence_source` | `TEXT` | `NULL` | |
| `validation_evidence_version` | `TEXT` | `NULL` | |
| `validation_evidence_scope` | `TEXT` | `NULL` | |
| `validated_by` | `TEXT` | `NULL` | |
| `validated_on` | `DATE` | `NULL` | |
| `total_numerator` | `TEXT` | `NULL` | Razão exata (em `g`) do total do lote; `NULL` se qualquer ingrediente bloqueado/pendente/conflitante |
| `total_denominator` | `TEXT` | `NULL` | |
| `total_unit` | `TEXT` | `NULL`, `CHECK (total_unit IS NULL OR total_unit = 'g')` | |
| `reference_basis` | `TEXT` | `NOT NULL DEFAULT '100'` | Base `100` |
| `reference_unit` | `TEXT` | `NOT NULL`, `CHECK (reference_unit IN ('g', 'ml'))` | Igual a `source_yield_unit` |
| `reference_numerator` | `TEXT` | `NULL` | Razão exata (em `g` por `100 g/ml`); `NULL` se não elegível/incompleto |
| `reference_denominator` | `TEXT` | `NULL` | |
| `is_complete` | `BOOLEAN` | `NOT NULL` | `true` somente quando todos os ingredientes estão cobertos e completos sem conflito |
| `is_regulatory_eligible` | `BOOLEAN` | `NOT NULL` | `true` somente quando `is_complete = true` e validação técnica autorizada (e sem `TEST_FIXTURE` fora de `testing`) |
| `is_confirmed_zero` | `BOOLEAN` | `NOT NULL` | `true` somente quando `is_regulatory_eligible = true` e `total_numerator = '0'` (`FR-011`) |
| `rules_version` | `TEXT` | `NOT NULL` | `added-sugars-formulation-v1` |
| `created_at` / `updated_at` | `TIMESTAMP` | `NULL` | |

- **Check constraints**:
  - `(total_numerator IS NULL) = (total_denominator IS NULL)`
  - `(reference_numerator IS NULL) = (reference_denominator IS NULL)`
  - `is_regulatory_eligible = true` ⇒ `is_complete = true AND total_numerator IS NOT NULL AND reference_numerator IS NOT NULL`
  - `is_regulatory_eligible = false` ⇒ `total_numerator IS NULL AND reference_numerator IS NULL AND is_confirmed_zero = false`

---

### 7. `added_sugars_evaluation_items` (`AddedSugarsEvaluationItem`) & `added_sugars_evaluation_causes` (`AddedSugarsEvaluationCause`)

Snapshots **imutáveis** por ingrediente avaliado e causas explícitas de bloqueio/incompletude/conflito. Protegidos contra `UPDATE` e `DELETE` por trigger PostgreSQL.

**`added_sugars_evaluation_items`:**

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | `BIGSERIAL` | PK | |
| `evaluation_id` | `BIGINT` | `NOT NULL`, FK → `added_sugars_evaluations(id)` `ON DELETE RESTRICT` | |
| `nutrition_calculation_ingredient_id` | `BIGINT` | `NOT NULL`, FK → `nutrition_calculation_ingredients(id)` `ON DELETE RESTRICT` | Ingrediente exato da Feature 003 |
| `formulation_ingredient_id` | `BIGINT` | `NULL`, FK → `added_sugars_formulation_ingredients(id)` `ON DELETE RESTRICT` | `NULL` se ingrediente da Feature 003 não estiver coberto na versão |
| `position` | `INTEGER` | `NOT NULL` | Posição herdada da Feature 003 |
| `food_id` | `BIGINT` | `NOT NULL` | Snapshot do `food_id` |
| `food_source` | `TEXT` | `NOT NULL` | Snapshot da origem (`TACO` ou fonte manual) |
| `ingredient_quantity` | `NUMERIC(14, 6)` | `NOT NULL` | Quantidade (em `g`) herdada da Feature 003 |
| `ingredient_unit` | `TEXT` | `NOT NULL` | `g` |
| `ingredient_type` | `TEXT` | `NULL` | `SIMPLE` ou `COMPOUND_INDUSTRIALIZED` |
| `classification` | `TEXT` | `NOT NULL` | `INCLUDED`, `EXCLUDED_NORMATIVE`, `MISSING`, `CONFLICTING`, ou `UNCOVERED` |
| `amount_basis` | `TEXT` | `NULL` | `PER_100G` ou `DIRECT_BATCH_GRAMS` |
| `amount_value` | `NUMERIC(14, 6)` | `NULL` | Valor de entrada da formulação |
| `contribution_numerator` | `TEXT` | `NULL` | Contribuição exata (em `g`) do ingrediente individual, se válida; `0/1` se `EXCLUDED_NORMATIVE` |
| `contribution_denominator` | `TEXT` | `NULL` | |
| `is_excluded_normative` | `BOOLEAN` | `NOT NULL DEFAULT false` | Distingue exclusão normativa de zero numérico ou ausência |
| `is_complete` | `BOOLEAN` | `NOT NULL` | `true` se o item tem classificação válida e contribuição determinada sem inconsistência |
| `evidence_count` | `INTEGER` | `NOT NULL DEFAULT 0` | Quantidade de evidências registradas para o item |
| `created_at` / `updated_at` | `TIMESTAMP` | `NULL` | |

**`added_sugars_evaluation_causes`:**

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | `BIGSERIAL` | PK | |
| `evaluation_id` | `BIGINT` | `NOT NULL`, FK → `added_sugars_evaluations(id)` `ON DELETE RESTRICT` | |
| `evaluation_item_id` | `BIGINT` | `NULL`, FK → `added_sugars_evaluation_items(id)` `ON DELETE RESTRICT` | `NULL` para causas no nível da avaliação/validação |
| `cause_type` | `TEXT` | `NOT NULL` | Ex.: `VALIDATION_BLOCK`, `MISSING_DATA`, `CONFLICTING_EVIDENCE`, `INGREDIENT_MISMATCH`, `PHYSICAL_INCONSISTENCY` |
| `cause_code` | `TEXT` | `NOT NULL` | Ex.: `FORMULATION_VALIDATION_PENDING`, `FORMULATION_VALIDATION_REJECTED`, `FORMULATION_VALIDATION_REVOKED`, `TEST_FIXTURE_NOT_ALLOWED`, `INGREDIENT_NOT_COVERED`, `INGREDIENT_ADDED_SUGARS_MISSING`, `ONLY_TOTAL_SUGARS_REPORTED`, `CONFLICTING_EVIDENCES`, `DIRECT_AMOUNT_EXCEEDS_INGREDIENT_QUANTITY` |
| `detail` | `TEXT` | `NULL` | Contexto descritivo auditável |
| `created_at` / `updated_at` | `TIMESTAMP` | `NULL` | |

---

## Additive Retrocompatible Extensions (Features 004 & 005)

### `serving_daily_value_calculations` (Feature 004 — aditivo)
- `added_sugars_evaluation_id` (`BIGINT NULL`, FK → `added_sugars_evaluations(id)` `ON DELETE RESTRICT`)
- `added_sugars_formulation_version_number` (`INTEGER NULL`)
- `added_sugars_validation_status` (`TEXT NULL`)
- `added_sugars_is_regulatory_eligible` (`BOOLEAN NULL`)
- `added_sugars_is_confirmed_zero` (`BOOLEAN NULL`)
- `added_sugars_reference_numerator` (`TEXT NULL`)
- `added_sugars_reference_denominator` (`TEXT NULL`)
- `added_sugars_reference_unit` (`TEXT NULL`)

### `serving_daily_value_regulatory_results` (Feature 004 — aditivo)
- `value_source_type` (`TEXT NOT NULL DEFAULT 'NUTRITION_CALCULATION'`, `CHECK (value_source_type IN ('NUTRITION_CALCULATION', 'ADDED_SUGARS_FORMULATION'))`)
- `source_reference_numerator` (`TEXT NULL`) — snapshot da razão `100 g/100 ml` quando `value_source_type = 'ADDED_SUGARS_FORMULATION'` (consumido diretamente pela Feature 005 sem exigir `FoodNutrient`).
- `source_reference_denominator` (`TEXT NULL`)

---

## State Transitions

```text
[Formulation Version vN] (immutable)
   └── Validation status (derived from latest append-only AddedSugarsFormulationValidation):
         (none)      -> PENDING     (blocks regulatory eligibility)
         AUTHORIZED  -> AUTHORIZED  (allows eligibility if scope/evidence/env valid & all ingredients complete)
         REJECTED    -> REJECTED    (blocks regulatory eligibility)
         REVOKED     -> REVOKED     (blocks regulatory eligibility for future evaluations; past snapshots intact)
```
