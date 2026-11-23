# API Contracts: Added Sugars Formulation Obtaining and Validation (007-added-sugars-formulation)

**Date**: 2026-11-23  
**Base URL**: `/api`  
**Format**: JSON (`Accept: application/json`, `Content-Type: application/json`)  
**Conventions**: Valores decimais de entrada e razões (`numerator`, `denominator`) são sempre trafegados como **string** (nunca `float`). Erros de validação retornam `422` no formato padrão do Laravel; recurso inexistente retorna `404`. Sem autenticação nesta fase (conforme `Assumptions` da Specification).

---

## 1. Registrar ou criar nova versão da formulação de açúcares adicionados

`POST /api/recipes/{recipe}/added-sugars-formulations`

Cria a versão `v1` da formulação de açúcares adicionados da receita ou, se a receita já possuir formulação, cria uma nova versão imutável `v(N+1)` (exigindo `change_reason` quando `N >= 1`).

### Request Body

```json
{
  "normative_source": "RDC nº 429/2020 e IN nº 75/2020",
  "normative_version": "2020-ret-2022",
  "registered_by": "Nutricionista Viridis Jr.",
  "registered_on": "2026-11-23",
  "change_reason": null,
  "ingredients": [
    {
      "position": 1,
      "food_id": 10,
      "ingredient_type": "SIMPLE",
      "classification": "EXCLUDED_NORMATIVE",
      "amount_basis": null,
      "amount_value": null,
      "amount_unit": null,
      "exclusion_justification": "Fruta in natura íntegra excluída conforme RDC nº 429/2020, art. 3º, I",
      "conflict_notes": null,
      "evidences": [
        {
          "source_type": "NORMATIVE_REFERENCE",
          "source_name": "RDC nº 429/2020",
          "document_reference": "Art. 3º, inciso I",
          "reported_amount_basis": null,
          "reported_amount_value": null,
          "reported_amount_unit": null,
          "only_total_sugars_reported": false,
          "recorded_by": "Nutricionista Viridis Jr.",
          "recorded_on": "2026-11-23"
        }
      ]
    },
    {
      "position": 2,
      "food_id": 25,
      "ingredient_type": "COMPOUND_INDUSTRIALIZED",
      "classification": "INCLUDED",
      "amount_basis": "PER_100G",
      "amount_value": "45.5",
      "amount_unit": "g",
      "exclusion_justification": null,
      "conflict_notes": null,
      "evidences": [
        {
          "source_type": "MANUFACTURER_SPEC",
          "source_name": "Fabricante Exemplo Ltda.",
          "document_reference": "FT-2026-089",
          "reported_amount_basis": "PER_100G",
          "reported_amount_value": "45.5",
          "reported_amount_unit": "g",
          "only_total_sugars_reported": false,
          "recorded_by": "Nutricionista Viridis Jr.",
          "recorded_on": "2026-11-23"
        }
      ]
    }
  ]
}
```

### Regras de Validação (`422`)
- `ingredients`: obrigatório, mínimo 1 item, posições únicas e compatíveis com `food_id` existente em `foods`.
- `ingredient_type`: `SIMPLE` ou `COMPOUND_INDUSTRIALIZED`.
- `classification`: `INCLUDED`, `EXCLUDED_NORMATIVE`, `MISSING` ou `CONFLICTING`.
- Se `classification = INCLUDED`: `amount_basis` (`PER_100G` ou `DIRECT_BATCH_GRAMS`), `amount_value` (string decimal `>= 0`, `<= 100` se `PER_100G`), `amount_unit = 'g'` obrigatórios; se `only_total_sugars_reported = true` em todas as evidências, rejeitar (`422`, pois açúcares totais não equivalem a açúcares adicionados).
- Se `classification = EXCLUDED_NORMATIVE`: `exclusion_justification` obrigatório; `amount_*` devem ser `null`.
- Se `classification = MISSING`: `amount_*` devem ser `null`.
- Se `classification = CONFLICTING`: `conflict_notes` obrigatório; `amount_*` devem ser `null`.
- `evidences`: obrigatório, mínimo 1 evidência por ingrediente (exceto `MISSING`, onde pode ser vazia ou conter evidência com `only_total_sugars_reported = true`).
- `change_reason`: obrigatório quando a receita já possuir versão anterior (`version_number > 1`).

### Response (`201 Created`)
Retorna a formulação da receita com `current_version` (incluindo ingredientes, evidências e estado de validação vigente `PENDING`) e lista ordenada `versions`.

---

## 2. Consultar formulação de açúcares adicionados e histórico de versões

`GET /api/recipes/{recipe}/added-sugars-formulations`

Retorna `200 OK` com a formulação da receita, sua versão vigente, histórico completo de versões imutáveis, ingredientes, evidências e trilha cronológica *append-only* de decisões de validação técnica por versão (`404` se a receita ou a formulação não existir).

---

## 3. Registrar decisão de validação técnica (*append-only*) sobre uma versão

`POST /api/added-sugars-formulation-versions/{version}/validations`

Registra uma decisão imutável (`AUTHORIZED`, `REJECTED` ou `REVOKED`) vinculada à versão específica da formulação, sem alterar a versão nem avaliações históricas anteriores.

### Request Body

```json
{
  "decision_status": "AUTHORIZED",
  "normative_source": "RDC nº 429/2020 e IN nº 75/2020",
  "normative_version": "2020-ret-2022",
  "evidence_type": "TECHNICAL_REVIEW",
  "evidence_reference": "PARECER-AA-2026-001",
  "evidence_source": "Responsável Técnico Viridis Jr.",
  "evidence_version": "1.0",
  "evidence_scope": "recipe:1/added-sugars-formulation:v1",
  "validated_by": "CRN-12345",
  "validated_on": "2026-11-23",
  "notes": "Classificações e fichas de fabricante conferidas."
}
```

### Regras de Validação (`422`)
- `decision_status`: `AUTHORIZED`, `REJECTED` ou `REVOKED`.
- `evidence_type`: `DOCUMENT`, `TECHNICAL_REVIEW` ou `TEST_FIXTURE`.
- Todos os campos de proveniência (`normative_source`, `normative_version`, `evidence_reference`, `evidence_source`, `evidence_version`, `evidence_scope`, `validated_by`, `validated_on`) obrigatórios e não vazios.
- `notes` obrigatório quando `decision_status` for `REJECTED` ou `REVOKED`.

### Response (`201 Created`)
Retorna o registro de validação criado (imutável).

---

## 4. Executar avaliação imutável de açúcares adicionados para uma `NutritionCalculation`

`POST /api/nutrition-calculations/{nutritionCalculation}/added-sugars-evaluations`

Cria e persiste um snapshot histórico imutável aplicando uma versão específica da formulação de açúcares adicionados (por padrão `formulation_version_id` explícito) aos ingredientes e rendimento preservados em `nutritionCalculation`.

### Request Body

```json
{
  "formulation_version_id": 1
}
```

### Regras de Validação e Execução
- `formulation_version_id`: obrigatório, deve existir em `added_sugars_formulation_versions` e pertencer ao mesmo `recipe_id` de `nutritionCalculation` (caso contrário → `422`).
- Cada ingrediente preservado em `nutritionCalculation` é pareado por `(position, food_id)` com a versão da formulação:
  - Se faltar cobertura ou houver `food_id` divergente → item marcado como `UNCOVERED` + causa `INGREDIENT_NOT_COVERED` / `INGREDIENT_MISMATCH`.
  - Se `DIRECT_BATCH_GRAMS` exceder `ingredient_quantity` da `NutritionCalculation` → `contribution = null` + causa `DIRECT_AMOUNT_EXCEEDS_INGREDIENT_QUANTITY`.
  - Se `INCLUDED` válido → contribuição exata (`PER_100G`: `quantity × amount_value ÷ 100`; `DIRECT_BATCH_GRAMS`: `amount_value`).
  - Se `EXCLUDED_NORMATIVE` → `contribution = 0/1`, `is_excluded_normative = true`.
  - Se `MISSING` ou `CONFLICTING` → `contribution = null` + causa explícita (`INGREDIENT_ADDED_SUGARS_MISSING`, `ONLY_TOTAL_SUGARS_REPORTED` ou `CONFLICTING_EVIDENCES`).
- Gate técnico (*deny-by-default*):
  - Exige última validação `AUTHORIZED` com `evidence_scope = "recipe:{recipe_id}/added-sugars-formulation:v{version_number}"` e `evidence_type != 'TEST_FIXTURE'` fora de `APP_ENV=testing`.
  - Exige que ingredientes validados não dependam exclusivamente de `OPERATIONAL_FORM` sem validação técnica autorizada.
  - Se todos os itens estiverem completos **e** o gate técnico passar → calcula `total` (`numerator`/`denominator`, `g`) e `reference` (`total × 100 ÷ yield_value`, `100 g` ou `100 ml`), `is_complete = true`, `is_regulatory_eligible = true`, `is_confirmed_zero = (total == 0)`.
  - Se houver qualquer pendência, bloqueio ou conflito → `total = null`, `reference = null`, `is_regulatory_eligible = false`, `is_confirmed_zero = false`, preservando contribuições individuais válidas e todas as `causes`.

### Response (`201 Created`) e Consulta (`GET /api/added-sugars-evaluations/{addedSugarsEvaluation}` → `200 OK`)

```json
{
  "data": {
    "id": 1,
    "nutrition_calculation_id": 10,
    "formulation_id": 1,
    "formulation_version_id": 1,
    "formulation_version_number": 1,
    "source_recipe_id": 1,
    "source_rules_version": "nutrition-calculation-v1",
    "rules_version": "added-sugars-formulation-v1",
    "yield": { "value": "500.000000", "unit": "g" },
    "validation": {
      "status": "AUTHORIZED",
      "evidence_type": "TECHNICAL_REVIEW",
      "evidence_reference": "PARECER-AA-2026-001",
      "evidence_source": "Responsável Técnico Viridis Jr.",
      "evidence_version": "1.0",
      "evidence_scope": "recipe:1/added-sugars-formulation:v1",
      "validated_by": "CRN-12345",
      "validated_on": "2026-11-23"
    },
    "total": {
      "value": { "numerator": "91", "denominator": "2" },
      "unit": "g"
    },
    "reference": {
      "basis": "100",
      "unit": "g",
      "value": { "numerator": "91", "denominator": "10" }
    },
    "is_complete": true,
    "is_regulatory_eligible": true,
    "is_confirmed_zero": false,
    "items": [],
    "causes": []
  }
}
```

---

## 5. Extensão retrocompatível na Feature 004 (e propagação para Feature 005)

`POST /api/nutrition-calculations/{nutritionCalculation}/serving-daily-value-calculations`

Adiciona campo **opcional**:

```json
{
  "portion": { "value": "20", "unit": "g", "household_measure": "1 colher de sopa" },
  "portion_authorization_id": 1,
  "regulatory_profile_id": 1,
  "added_sugars_evaluation_id": 1
}
```

- Quando `added_sugars_evaluation_id` é omitido ou `null`: comportamento 100% idêntico à Feature 004.
- Quando informado:
  - Se `added_sugars_evaluation.nutrition_calculation_id !== nutritionCalculation.id` → `422 ValidationException`.
  - Se pertencer à mesma `nutritionCalculation`:
    - Avaliação elegível (`is_regulatory_eligible = true`) + perfil autorizado + `MAP-004` presente com `regulatory_unit = 'g'` → popula `MAP-004` em `regulatory_results` com valor exato por porção (`total × portion ÷ yield`), `value_source_type = 'ADDED_SUGARS_FORMULATION'`, `source_reference` (`100 g/100 ml`), `is_complete = true`, `availability = 'AVAILABLE'`; libera `%VD` em `VDR-004` somente se `VDR-004` estiver tecnicamente autorizado; não cria fator energético adicional.
    - Avaliação não elegível → mantém `MAP-004` e `VDR-004` como `UNAVAILABLE` (`null`) e propaga as causas herdadas em `causes`.
- Na Feature 005 (`POST /api/serving-daily-value-calculations/{servingDailyValueCalculation}/declared-nutrition-calculations`), `MAP-004` respaldado por `ADDED_SUGARS_FORMULATION` elegível tem seus valores exatos de `100 g/100 ml` e porção avaliados pelas mesmas regras de NSQ, arredondamento, recálculo de `%VD` e `is_label_ready`.
