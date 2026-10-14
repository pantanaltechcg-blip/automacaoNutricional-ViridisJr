# Contract: Nutrition Calculations

## Escopo

Este contrato cria uma execução nutricional independente para uma Recipe persistida
e consulta uma execução preservada. Não cria nem altera Recipe, ingredientes ou Base
Nutricional. Não inclui listagem, edição, exclusão, porção, %VD, energia por fatores,
arredondamento, rótulo ou exportação.

Códigos HTTP específicos não são fixados porque não há decisão aprovada. Falhas usam
o tratamento padrão aplicável do Laravel sem inventar novo contrato de status.

## Representações comuns

### Razão exata

```json
{
  "numerator": "501",
  "denominator": "20"
}
```

Numerador e denominador são strings inteiras; o denominador é positivo. O objeto
representa exatamente o valor e não autoriza conversão para `float` nem
arredondamento. Um valor numérico ausente é `null`; zero numérico é `0/1`.

### Estado e completude

- `qualitative_states` é o conjunto sem duplicatas de `TRACE`, `NA`, `ABSENT` e
  `REEVALUATION`, em ordem determinística.
- `has_missing_relation` representa ausência de `FoodNutrient` e não é um estado.
- `complete` é `true` somente quando todas as contribuições do nutriente são
  `EXACT`.
- Um valor numérico pode coexistir com `complete: false`; nesse caso é subtotal ou
  referência parcial e não composição completa.

## Criar execução

### Request

`POST /api/recipes/{recipe}/nutrition-calculations`

Sem payload funcional. `{recipe}` deve identificar uma Recipe persistida válida.

### Resposta de sucesso

```json
{
  "data": {
    "id": 81,
    "recipe_id": 10,
    "rules_version": "nutrition-calculation-v1",
    "yield": {
      "value": "1680",
      "unit": "g"
    },
    "ingredients": [
      {
        "recipe_ingredient_id": 30,
        "position": 1,
        "quantity": "1000",
        "unit": "g",
        "food": {
          "id": 25,
          "source": "TACO",
          "source_code": "492"
        }
      }
    ],
    "nutrients": [
      {
        "nutrient": {
          "id": 4,
          "name": "Carboidrato",
          "unit": "g"
        },
        "contributions": [
          {
            "recipe_ingredient_id": 30,
            "food_nutrient_id": 400,
            "status": "EXACT",
            "source_amount_per_100g": "99.60",
            "missing_relation": false,
            "value": {
              "numerator": "996",
              "denominator": "1"
            }
          }
        ],
        "total": {
          "value": {
            "numerator": "996",
            "denominator": "1"
          },
          "complete": true,
          "qualitative_states": [],
          "has_missing_relation": false
        },
        "reference": {
          "basis": "100",
          "unit": "g",
          "value": {
            "numerator": "415",
            "denominator": "7"
          },
          "complete": true,
          "qualitative_states": [],
          "has_missing_relation": false
        }
      }
    ]
  }
}
```

Os números são somente exemplos de formato. A coleção `ingredients` segue posição
crescente; `nutrients` usa ordem determinística por identificação; contribuições
seguem a ordem das ocorrências.

Cada request cria novo `data.id`, mesmo com a mesma Recipe e as mesmas entradas.

## Consultar execução

### Request

`GET /api/nutrition-calculations/{calculation}`

### Resposta de sucesso

Retorna a mesma representação preservada pela criação. A resposta é construída
somente do snapshot e resultado da execução; alterações atuais em Recipe,
RecipeIngredient, Food, Nutrient ou FoodNutrient não modificam o conteúdo.

## Formas de contribuição

### Estado qualitativo

```json
{
  "recipe_ingredient_id": 31,
  "food_nutrient_id": 401,
  "status": "TRACE",
  "source_amount_per_100g": null,
  "missing_relation": false,
  "value": null
}
```

### Ausência de relação

```json
{
  "recipe_ingredient_id": 32,
  "food_nutrient_id": null,
  "status": null,
  "source_amount_per_100g": null,
  "missing_relation": true,
  "value": null
}
```

### Consolidado incompleto com subtotal

```json
{
  "total": {
    "value": {"numerator": "25", "denominator": "2"},
    "complete": false,
    "qualitative_states": ["TRACE", "NA"],
    "has_missing_relation": true
  },
  "reference": {
    "basis": "100",
    "unit": "ml",
    "value": {"numerator": "1250", "denominator": "333"},
    "complete": false,
    "qualitative_states": ["TRACE", "NA"],
    "has_missing_relation": true
  }
}
```

O subtotal é normalizado por `subtotal × 100 ÷ yield_value`; estados, ausência e
incompletude são preservados na referência.

### Consolidado sem contribuição EXACT

```json
{
  "total": {
    "value": null,
    "complete": false,
    "qualitative_states": ["ABSENT", "REEVALUATION"],
    "has_missing_relation": true
  },
  "reference": {
    "basis": "100",
    "unit": "g",
    "value": null,
    "complete": false,
    "qualitative_states": ["ABSENT", "REEVALUATION"],
    "has_missing_relation": true
  }
}
```

## Falhas

- Recipe inexistente: falha sem criar execução.
- Recipe ou associação necessária inválida/inconsistente: falha sem corrigir dados.
- Erro de cálculo ou persistência: rollback integral; nenhum resultado parcial.
- Execução inexistente na consulta: falha sem criar ou reconstruir resultado.

Não existe operação de alteração ou exclusão de uma execução neste contrato.

