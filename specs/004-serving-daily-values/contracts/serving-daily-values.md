# Contract: Serving and Daily Values

## Escopo

Este contrato cria e consulta uma execução derivada imutável de uma
`NutritionCalculation`. Ele não altera Recipe, Base Nutricional, execução 003,
referências regulatórias ou dados TACO. Não inclui listagem, edição, exclusão,
arredondamento, valores declarados finais, rotulagem, PDF/PNG ou administração de
perfis regulatórios.

Códigos HTTP específicos não são fixados sem decisão funcional aprovada. Falhas usam
o tratamento padrão aplicável do Laravel e nunca persistem execução parcial.

## Representações comuns

### Razão exata

```json
{"numerator": "1", "denominator": "3"}
```

Ambos são strings inteiras e o denominador é positivo. Zero é
`{"numerator":"0","denominator":"1"}`. Ausência de número é `null`. O contrato
não autoriza conversão para número JSON de ponto flutuante.

### Dimensões independentes

- `complete`: completude nutricional do valor ou subtotal;
- `qualitative_states`: `TRACE`, `NA`, `ABSENT`, `REEVALUATION`;
- `has_missing_relation`: ausência de `FoodNutrient` na origem;
- `availability`: disponibilidade regulatória;
- `causes`: motivos regulatórios/técnicos, distintos dos estados nutricionais;
- `NOT_APPLICABLE`: condição comprovada, distinta de ausência, bloqueio e zero.

Nenhuma dessas dimensões é inferida pela truthiness do valor.

### Evidência técnica snapshotada

Quando existir decisão técnica registrada, a resposta usa:

```json
{
  "reference": "identificador-verificavel",
  "source": "origem-rastreavel",
  "version": "versao-exata",
  "scope": "escopo-da-regra-produto-formulacao",
  "kind": "DOCUMENTED",
  "validated_by": "responsavel-identificavel",
  "validated_on": "2026-11-11"
}
```

`kind` é `DOCUMENTED` ou `TEST_FIXTURE`. `TEST_FIXTURE` somente pode satisfazer gates
no ambiente `testing`; fora dele permanece bloqueada. Status `AUTHORIZED` sem essa
tupla, tupla sem status autorizado ou escopo incompatível nunca liberam cálculo. O
objeto identifica a decisão utilizada, mas sua presença textual isolada não constitui
aprovação técnica automática.

## Criar execução derivada

### Request

`POST /api/nutrition-calculations/{nutritionCalculation}/serving-daily-value-calculations`

```json
{
  "portion": {
    "value": "20",
    "unit": "g",
    "household_measure": "1 colher de sopa"
  },
  "portion_authorization_id": 12,
  "regulatory_profile_id": 3
}
```

Regras:

- `portion.value` é string decimal de base 10, positiva e sem limite arbitrário de
  casas; não aceita notação de ponto flutuante;
- `portion.unit` é `g` ou `ml` e deve coincidir com o rendimento da origem;
- `portion.household_measure` é string ou `null`; obrigatoriedade, dispensa ou
  inaplicabilidade vêm da autorização, não de flag autodeclarada;
- `portion_authorization_id` deve identificar autorização `AUTHORIZED`, vinculada à
  mesma `NutritionCalculation` e exatamente compatível com a porção;
- `regulatory_profile_id` identifica a versão MAP/ENE/VDR a snapshotar. Perfil ou
  linhas bloqueados não impedem valores por porção, mas impedem cada resultado
  dependente;
- o cliente não envia status, evidência, aplicabilidade, fatores, VDR ou autorização de
  conversão; esses dados vêm exclusivamente das referências controladas pelo sistema;
- `TrimStrings` e `ConvertEmptyStringsToNull` atuam antes do Form Request. O valor
  preservado é o resultado dessa normalização de transporte; não há correção de caixa
  ou conteúdo.

Referência de porção ausente, bloqueada, de outra formulação ou divergente rejeita o
request. Perfil bloqueado é aceito apenas para registrar os bloqueios seguros.

### Resposta de sucesso

Exemplo reduzido de forma; os números não aprovam valores regulatórios reais:

```json
{
  "data": {
    "id": 25,
    "nutrition_calculation_id": 81,
    "recipe_id": 10,
    "source_rules_version": "nutrition-calculation-v1",
    "rules_version": "serving-daily-values-v1",
    "yield": {"value": "1680", "unit": "g"},
    "portion": {
      "value": "20",
      "unit": "g",
      "household_measure_mode": "PROVIDED",
      "household_measure": "1 colher de sopa",
      "reference": {
        "code": "portion-reference-code",
        "version": "version-id",
        "source": "identified-source",
        "technical_status": "AUTHORIZED",
        "validated_on": "2026-11-11",
        "justification": null,
        "evidence": {
          "reference": "portion-evidence-id",
          "source": "controlled-source",
          "version": "evidence-version",
          "scope": "nutrition-calculation:81/portion:portion-reference-code@version-id",
          "kind": "DOCUMENTED",
          "validated_by": "identified-reviewer",
          "validated_on": "2026-11-11"
        }
      }
    },
    "regulatory_profile": {
      "code": "004-CQ002",
      "version": "004-CQ002-2026-10-30.1",
      "title": "Serving and Daily Values",
      "normative_source": "identified-official-source",
      "normative_version": "identified-version",
      "consulted_on": "2026-10-30",
      "technical_status": "BLOCKED",
      "evidence": null
    },
    "nutrients": [
      {
        "nutrient": {"id": 7, "name": "Carboidrato", "unit": "g"},
        "source_total": {
          "value": {"numerator": "10957", "denominator": "10"},
          "complete": true,
          "qualitative_states": [],
          "has_missing_relation": false
        },
        "portion": {
          "value": {"numerator": "10957", "denominator": "840"},
          "complete": true,
          "qualitative_states": [],
          "has_missing_relation": false
        }
      }
    ],
    "regulatory_results": [
      {
        "map_code": "MAP-002",
        "component": "Carboidratos",
        "technical_status": "BLOCKED",
        "source_reference": "MAP-002-source-reference",
        "evidence": null,
        "conversion": {
          "status": "BLOCKED",
          "source_unit": "g",
          "target_unit": "g",
          "factor": null,
          "evidence": null
        },
        "value": null,
        "unit": "g",
        "complete": false,
        "availability": "UNAVAILABLE",
        "causes": [
          {"code": "MAP_NOT_TECHNICALLY_AUTHORIZED", "category": "REGULATORY_BLOCK"}
        ]
      }
    ],
    "energy": {
      "value": null,
      "unit": null,
      "complete": false,
      "availability": "UNAVAILABLE",
      "causes": [
        {"code": "ENE_MATRIX_NOT_AUTHORIZED", "category": "REGULATORY_BLOCK"}
      ],
      "components": []
    },
    "daily_values": [
      {
        "vdr_code": "VDR-002",
        "map_code": "MAP-002",
        "vdr": null,
        "value": null,
        "complete": false,
        "availability": "UNAVAILABLE",
        "causes": [
          {"code": "REGULATORY_NUMERATOR_UNAVAILABLE", "category": "MISSING_NUMERATOR"}
        ]
      }
    ]
  }
}
```

Ordenação:

- `nutrients`: `nutrient.id` crescente;
- `regulatory_results`: `map_code`;
- `energy.components`: `ene_code`;
- `daily_values`: `vdr_code`;
- `qualitative_states` e `causes`: ordem técnica determinística documentada.

Cada POST cria novo `data.id`, mesmo com os mesmos parâmetros.

Os elementos de `energy.components` preservam evidência ENE, evidência de
aplicabilidade, fonte/versão do fator, regra de sobreposição e eventual justificativa de
não aplicabilidade. Os elementos de `daily_values` preservam fonte, versão, unidade,
estado e evidência VDR, além da identificação e razão do numerador regulatório
efetivamente utilizado. Esses dados são snapshots, não leituras das referências atuais.

## Formas relevantes

### Zero completo

```json
{
  "value": {"numerator": "0", "denominator": "1"},
  "complete": true,
  "qualitative_states": [],
  "has_missing_relation": false
}
```

### Subtotal nutricional por porção

```json
{
  "value": {"numerator": "5", "denominator": "2"},
  "complete": false,
  "qualitative_states": ["TRACE", "REEVALUATION"],
  "has_missing_relation": true
}
```

### Sem valor numérico

```json
{
  "value": null,
  "complete": false,
  "qualitative_states": ["NA"],
  "has_missing_relation": false
}
```

### Não aplicabilidade comprovada

Um componente ENE `NOT_APPLICABLE` não possui contribuição. Seu snapshot inclui
produto (`recipe_id`), formulação (`nutrition_calculation_id`), componente,
justificativa, fonte, validação, data e versão. A condição não é representada como
zero, ausência de dado ou `UNKNOWN`.

### Energia

- matriz incompleta/bloqueada/desconhecida/sobreposta sem regra: `value = null`, sem
  subtotal;
- matriz autorizada + dados completos: razão exata e `complete = true`;
- matriz autorizada + dados nutricionais incompletos: subtotal racional disponível e
  `complete = false`, ou `null` se nenhum componente tiver número.

A energia TACO nunca substitui essa energia derivada.

### `%VD`

- exige MAP, numerador, VDR e unidade/conversão autorizados;
- `NOT_REQUIRED` exige evidência de compatibilidade nutricional, física e semântica;
- `AUTHORIZED` para conversão exige fator racional e evidência próprios;
- subtotal numérico produz percentual parcial;
- numerador `null` produz `%VD = null`;
- zero completo produz `0/1` completo;
- `NO_NORMATIVE_VDR` é diferente de `UNAVAILABLE`.

## Consultar execução derivada

`GET /api/serving-daily-value-calculations/{servingDailyValueCalculation}`

Retorna a mesma representação histórica da criação, exclusivamente do snapshot. Não
consulta referências atuais para recompor ou promover resultados. Porção/dispensa,
perfil, MAP, conversão, ENE, aplicabilidade e VDR são retornados com as referências,
versões, escopos e evidências preservados na criação.

## Falhas

- origem inexistente: falha sem persistência;
- porção ausente, não positiva, unidade incompatível ou referência não autorizada:
  falha sem persistência;
- medida exigida ausente ou medida presente onde a autorização registra dispensa:
  falha sem correção silenciosa;
- perfil inexistente: falha sem inventar configuração;
- status autorizado sem evidência, evidência de escopo incompatível ou `TEST_FIXTURE`
  fora de `testing`: gate bloqueado, sem promoção por texto isolado;
- erro de cálculo/persistência: rollback integral;
- execução derivada inexistente no GET: falha sem reconstrução.

MAP/ENE/VDR bloqueados não são falhas do POST quando a porção está autorizada. Eles
produzem resultados dependentes indisponíveis com causas explícitas.

## Fora do contrato

Não existem rotas de update/delete, catálogo de categorias, aprovação administrativa,
cadastro de alimentos, fontes complementares, rounding, rótulo final, preview,
autenticação ou exportação.
