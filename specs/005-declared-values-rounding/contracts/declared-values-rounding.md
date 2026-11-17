# Contract: Declared Values, Rounding, and Non-Significant Quantities

## Escopo

Este contrato cria e consulta uma execução histórica imutável de valores nutricionais declarados, regras de quantidades não significativas, arredondamento e recálculo regulatório final a partir de uma única `ServingDailyValueCalculation` (Feature 004).

Não altera Recipe, Base Nutricional, execução 003, execução 004, referências regulatórias ou dados TACO. Não inclui renderização HTML/CSS da tabela nutricional, preview gráfico, rotulagem frontal (lupa), exportação PDF/PNG, autenticação ou CRUD administrativo de regras regulatórias (`FR-014`).

Falhas de validação ou autorização de perfil usam o tratamento padrão do Laravel (`422` / `404`) e nunca persistem execução parcial.

---

## Representações comuns

### Valor declarado (string decimal exata + razão exata)

Quando determinado, todo valor declarado (nutriente, energia final ou `%VD` final) é retornado com:

```json
{
  "display": "2.5",
  "ratio": {"numerator": "5", "denominator": "2"}
}
```

- `display`: string decimal formatada exatamente na escala/incremento da faixa normativa aplicada (ou `"0"` quando declarado por regra de quantidade não significativa);
- `ratio`: par de strings inteiras representando a razão exata **já arredondada** (ou `{"numerator": "0", "denominator": "1"}` quando zero);
- Quando indisponível/bloqueado/parcial, ambos (`display` e `ratio`, ou o bloco de valor declarado) permanecem `null`, sem uso de ponto flutuante.

### Razão exata (valores de origem / fatores)

```json
{"numerator": "10957", "denominator": "840"}
```

Strings inteiras, denominador positivo. Zero é `{"numerator": "0", "denominator": "1"}`. Ausência é `null`.

### Evidência técnica snapshotada

```json
{
  "reference": "identificador-verificavel",
  "source": "origem-rastreavel",
  "version": "versao-exata",
  "scope": "escopo-da-regra-produto-formulacao",
  "kind": "DOCUMENTED",
  "validated_by": "responsavel-identificavel",
  "validated_on": "2026-11-12"
}
```

`kind` é `DOCUMENTED` ou `TEST_FIXTURE`. `TEST_FIXTURE` somente satisfaz gates no ambiente `testing`.

---

## Criar execução de valores declarados

### Request

`POST /api/serving-daily-value-calculations/{servingDailyValueCalculation}/declared-nutrition-calculations`

```json
{
  "regulatory_declaration_profile_id": 4
}
```

Regras de validação e gate:

- `regulatory_declaration_profile_id`: obrigatório, inteiro positivo existente em `regulatory_declaration_profiles`.
- O `RegulatoryDeclarationProfile` referenciado **deve** coincidir exatamente com a execução 004 em:
  - `regulatory_profile_code`;
  - `regulatory_profile_version`;
  - `normative_source` / `normative_version`;
  - escopo opcional `scope_recipe_id` (= `source_recipe_id`) e `scope_nutrition_calculation_id` (= `nutrition_calculation_id`).
  Qualquer divergência rejeita a requisição (`422 ValidationException`) sem persistir registro (`FR-001`).
- O cliente não envia regras de arredondamento, thresholds de NSQ, fatores, status ou evidências; todos são resolvidos exclusivamente no servidor a partir dos registros versionados.
- Um `RegulatoryDeclarationProfile` compatível com `technical_status = BLOCKED` (ou com regras individuais bloqueadas) é aceito para persistir um snapshot seguro com `is_label_ready = false` e causas explícitas (`FR-006`).

### Resposta de sucesso (`201 Created`)

Exemplo ilustrativo de forma (valores numéricos não aprovam regras reais):

```json
{
  "data": {
    "id": 12,
    "serving_daily_value_calculation_id": 25,
    "nutrition_calculation_id": 81,
    "recipe_id": 10,
    "source_003_rules_version": "nutrition-calculation-v1",
    "source_004_rules_version": "serving-daily-values-v1",
    "rules_version": "declared-values-rounding-v1",
    "is_label_ready": false,
    "label_readiness_causes": [
      {
        "scope": "LABEL_READINESS",
        "scope_key": "LABEL_READINESS",
        "category": "REGULATORY_BLOCK",
        "code": "MANDATORY_COMPONENT_UNAVAILABLE",
        "detail": "MAP-004 não possui valor declarado completo em ambas as bases.",
        "source_reference": null
      }
    ],
    "yield": {"value": "1680", "unit": "g"},
    "portion": {
      "value": "20",
      "unit": "g",
      "household_measure_mode": "PROVIDED",
      "household_measure": "1 colher de sopa",
      "reference_technical_status": "AUTHORIZED"
    },
    "regulatory_profile": {
      "code": "004-CQ002",
      "version": "004-CQ002-2026-10-30.1",
      "technical_status": "AUTHORIZED",
      "normative_source": "RDC 429/2020 + IN 75/2020",
      "normative_version": "2020-consolidated"
    },
    "declaration_profile": {
      "code": "005-DECL-001",
      "version": "2026-11-12.1",
      "title": "Declared Values and Rounding",
      "technical_status": "AUTHORIZED",
      "normative_source": "RDC 429/2020 + IN 75/2020",
      "normative_version": "2020-consolidated",
      "consulted_on": "2026-11-12",
      "evidence": {
        "reference": "DEC-005-001",
        "source": "controlled-source",
        "version": "2026-11-12.1",
        "scope": "declaration-profile:005-DECL-001@2026-11-12.1",
        "kind": "TEST_FIXTURE",
        "validated_by": "identified-reviewer",
        "validated_on": "2026-11-12"
      }
    },
    "recalculation_policy": {
      "policy_code": "REC-001",
      "normative_order_mode": "DECLARE_COMPONENTS_THEN_ENERGY_AND_VD",
      "technical_status": "AUTHORIZED",
      "requires_complete_energy_inputs": true,
      "source_reference": "RDC 429/2020, art. 32, § 1º",
      "evidence": {
        "reference": "REC-001-EV",
        "source": "controlled-source",
        "version": "2026-11-12.1",
        "scope": "declaration-profile:005-DECL-001@2026-11-12.1/policy:REC-001",
        "kind": "TEST_FIXTURE",
        "validated_by": "identified-reviewer",
        "validated_on": "2026-11-12"
      }
    },
    "components": [
      {
        "map_code": "MAP-002",
        "component": "Carboidratos",
        "map_technical_status": "AUTHORIZED",
        "nutrient": {"id": 7, "name": "Carboidrato", "unit": "g"},
        "regulatory_unit": "g",
        "source_complete": true,
        "qualitative_states": [],
        "has_missing_relation": false,
        "reference_100": {
          "source_exact": {"numerator": "652", "denominator": "10"},
          "regulatory_exact": {"numerator": "326", "denominator": "5"},
          "is_exact_zero": false,
          "nsq_applied": false,
          "nsq_rule": {
            "rule_code": "NSQ-MAP-002-REF100",
            "technical_status": "AUTHORIZED"
          },
          "rounding_rule": {
            "rule_code": "RND-MAP-002-REF100",
            "technical_status": "AUTHORIZED",
            "tie_breaker": "HALF_UP",
            "step": {"numerator": "1", "denominator": "1"},
            "decimal_places": 0
          },
          "declared": {
            "display": "65",
            "ratio": {"numerator": "65", "denominator": "1"}
          },
          "complete": true,
          "availability": "AVAILABLE",
          "causes": []
        },
        "portion": {
          "source_exact": {"numerator": "10957", "denominator": "840"},
          "regulatory_exact": {"numerator": "10957", "denominator": "840"},
          "is_exact_zero": false,
          "nsq_applied": false,
          "nsq_rule": {
            "rule_code": "NSQ-MAP-002-PORTION",
            "technical_status": "AUTHORIZED"
          },
          "rounding_rule": {
            "rule_code": "RND-MAP-002-PORTION",
            "technical_status": "AUTHORIZED",
            "tie_breaker": "HALF_UP",
            "step": {"numerator": "1", "denominator": "1"},
            "decimal_places": 0
          },
          "declared": {
            "display": "13",
            "ratio": {"numerator": "13", "denominator": "1"}
          },
          "complete": true,
          "availability": "AVAILABLE",
          "causes": []
        }
      }
    ],
    "energy": {
      "source_portion_derived_exact": {
        "value": {"numerator": "1205", "denominator": "10"},
        "unit": "kcal",
        "complete": true,
        "availability": "AVAILABLE"
      },
      "reference_100": {
        "recalculated_exact": {"numerator": "300", "denominator": "1"},
        "nsq_applied": false,
        "declared": {
          "display": "300",
          "ratio": {"numerator": "300", "denominator": "1"}
        },
        "unit": "kcal",
        "complete": true,
        "availability": "AVAILABLE",
        "causes": [],
        "components": []
      },
      "portion": {
        "recalculated_exact": {"numerator": "120", "denominator": "1"},
        "nsq_applied": false,
        "declared": {
          "display": "120",
          "ratio": {"numerator": "120", "denominator": "1"}
        },
        "unit": "kcal",
        "complete": true,
        "availability": "AVAILABLE",
        "causes": [],
        "components": []
      }
    },
    "daily_values": [
      {
        "vdr_code": "VDR-002",
        "component": "Carboidratos",
        "map_code": "MAP-002",
        "vdr": {"numerator": "300", "denominator": "1"},
        "vdr_unit": "g",
        "technical_status": "AUTHORIZED",
        "source_derived_exact": {
          "value": {"numerator": "10957", "denominator": "2520"},
          "availability": "AVAILABLE"
        },
        "numerator_identity": "DECLARED_COMPONENT:MAP-002",
        "input_declared": {
          "display": "13",
          "ratio": {"numerator": "13", "denominator": "1"}
        },
        "unrounded_percentage": {"numerator": "13", "denominator": "3"},
        "rounding_rule": {
          "rule_code": "RND-VDR-002",
          "technical_status": "AUTHORIZED",
          "tie_breaker": "HALF_UP",
          "step": {"numerator": "1", "denominator": "1"},
          "decimal_places": 0
        },
        "declared_percentage": {
          "display": "4",
          "ratio": {"numerator": "4", "denominator": "1"}
        },
        "complete": true,
        "availability": "AVAILABLE",
        "causes": []
      }
    ]
  }
}
```

---

## Consultar execução de valores declarados

### Request

`GET /api/declared-nutrition-calculations/{declaredNutritionCalculation}`

### Resposta de sucesso (`200 OK`)

Retorna exatamente a mesma estrutura `data` do snapshot persistido, sem consultar tabelas de alimentos, receitas ou regras atuais.

---

## Ordenação determinística

- `components`: `map_code` crescente;
- `qualitative_states`: ordem fixa `['TRACE', 'NA', 'ABSENT', 'REEVALUATION']`;
- `energy.reference_100.components` e `energy.portion.components`: `ene_code` crescente;
- `daily_values`: `vdr_code` crescente;
- `causes` e `label_readiness_causes`: `(scope, scope_key, code)` crescente.

Cada `POST` gera um novo `data.id` imutável.
