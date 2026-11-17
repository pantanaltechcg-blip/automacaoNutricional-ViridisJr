# Contract: Governed Manual Food Registration (`006-manual-food-registration`)

## Scope

Este contrato define a API JSON para cadastro, consulta detalhada (com histórico de versões), atualização versionada imutável e ativação/inativação lógica de alimentos simples ausentes da base TACO, bem como o impacto controlado nos contratos existentes de busca de alimentos (`GET /api/foods`) e receitas (`POST/PUT /api/recipes`).

Permanece explicitamente fora deste contrato:
- criação ou edição de alimentos com origem `TACO`;
- alteração ou exclusão de nutrientes oficiais da tabela `nutrients`;
- cadastro de ingredientes compostos ou industrializados com lista de ingredientes ou sub-receitas;
- alimentos com base de referência `100 ml` ou conversão automática por densidade;
- autenticação/autorização de usuários (segue a política operacional vigente das Features 001–005).

---

## Normalização de transporte HTTP

Mantém-se o comportamento padrão do Laravel com `TrimStrings` e `ConvertEmptyStringsToNull` ativos antes dos Form Requests:
- espaços externos de strings (inclusive em itens aninhados de `nutrients`) são removidos antes da validação;
- strings vazias ou compostas apenas por espaços são convertidas para `null` antes das regras de validação;
- valores numéricos em `amount_value` quando `status = 'EXACT'` devem ser enviados como **string decimal** em base 10 com no máximo 5 dígitos inteiros e até 2 casas decimais (`^\d{1,5}(?:\.\d{1,2})?$`), sem notação científica, sinal negativo, vírgula ou tipo `float`/`number` JSON.

---

## 1. Cadastrar alimento manual (`POST /api/manual-foods`)

### Request

`POST /api/manual-foods`

```json
{
  "name": "Amora preta, polpa integral pasteurizada",
  "category": "Frutas e derivados",
  "source": "FABRICANTE_X",
  "source_code": "AMORA-POLPA-01",
  "reference_unit": "g",
  "evidence_reference": "LAUDO-LAB-2026-041",
  "evidence_source": "Laboratório Analítico Exemplo",
  "registered_by": "Nutricionista Responsável",
  "registered_on": "2026-11-17",
  "nutrients": [
    {
      "nutrient_id": 4,
      "status": "EXACT",
      "amount_value": "1.45"
    },
    {
      "nutrient_id": 7,
      "status": "EXACT",
      "amount_value": "10.20"
    },
    {
      "nutrient_id": 15,
      "status": "TRACE",
      "amount_value": null
    }
  ]
}
```

### Regras de validação

| Campo | Regra |
|---|---|
| `name` | Obrigatório, string não vazia após trim |
| `category` | Opcional (`null` ou string não vazia após trim) |
| `source` | Obrigatório, string não vazia após trim; proibido `'TACO'` (case-insensitive) |
| `source_code` | Obrigatório, string não vazia após trim |
| `reference_unit` | Obrigatório; exatamente `'g'` (representando composição por `100 g`) |
| `evidence_reference` | Obrigatório, string não vazia após trim |
| `evidence_source` | Obrigatório, string não vazia após trim |
| `registered_by` | Obrigatório, string não vazia após trim |
| `registered_on` | Obrigatório, data válida no formato `YYYY-MM-DD` |
| `nutrients` | Obrigatório, array com ao menos 1 item |
| `nutrients.*.nutrient_id` | Obrigatório, inteiro existente em `nutrients.id`, sem duplicidade no payload |
| `nutrients.*.status` | Obrigatório; um de `EXACT`, `TRACE`, `NA`, `ABSENT`, `REEVALUATION` |
| `nutrients.*.amount_value` | Obrigatório como string decimal não negativa `^\d{1,5}(?:\.\d{1,2})?$` quando `status = 'EXACT'`; obrigatoriamente `null` nos demais estados |

### Regras de negócio e rejeições (`422 Unprocessable Entity`)

- Rejeita `source = 'TACO'` (qualquer capitalização/espaços).
- Rejeita duplicidade exata contra qualquer alimento existente (TACO ou manual) para `(source, source_code)` normalizados ou `(source, name)` normalizados sem distinção de caixa/acentuação.
- Rejeita `amount_value` como número JSON (`float`/`int`), negativo, com mais de 2 casas decimais ou acima de `99999.99`.
- Rejeita `amount_value` não nulo quando `status != 'EXACT'`.
- Qualquer falha mantém o banco integralmente inalterado (transação atômica).

### Resposta de sucesso (`201 Created`)

```json
{
  "data": {
    "id": 601,
    "is_active": true,
    "current_version_number": 1,
    "reference_unit": "g",
    "name": "Amora preta, polpa integral pasteurizada",
    "category": "Frutas e derivados",
    "source": "FABRICANTE_X",
    "source_code": "AMORA-POLPA-01",
    "current_version": {
      "version_number": 1,
      "name": "Amora preta, polpa integral pasteurizada",
      "category": "Frutas e derivados",
      "source": "FABRICANTE_X",
      "source_code": "AMORA-POLPA-01",
      "reference_unit": "g",
      "evidence_reference": "LAUDO-LAB-2026-041",
      "evidence_source": "Laboratório Analítico Exemplo",
      "registered_by": "Nutricionista Responsável",
      "registered_on": "2026-11-17",
      "change_reason": null,
      "created_at": "2026-11-17T11:30:00Z",
      "nutrients": [
        {
          "id": 4,
          "name": "Proteína",
          "unit": "g",
          "amount_value": "1.45",
          "status": "EXACT"
        },
        {
          "id": 7,
          "name": "Carboidrato",
          "unit": "g",
          "amount_value": "10.20",
          "status": "EXACT"
        },
        {
          "id": 15,
          "name": "Sódio",
          "unit": "mg",
          "amount_value": null,
          "status": "TRACE"
        }
      ]
    },
    "versions": [
      {
        "version_number": 1,
        "name": "Amora preta, polpa integral pasteurizada",
        "category": "Frutas e derivados",
        "source": "FABRICANTE_X",
        "source_code": "AMORA-POLPA-01",
        "reference_unit": "g",
        "evidence_reference": "LAUDO-LAB-2026-041",
        "evidence_source": "Laboratório Analítico Exemplo",
        "registered_by": "Nutricionista Responsável",
        "registered_on": "2026-11-17",
        "change_reason": null,
        "created_at": "2026-11-17T11:30:00Z",
        "nutrients": [
          {
            "id": 4,
            "name": "Proteína",
            "unit": "g",
            "amount_value": "1.45",
            "status": "EXACT"
          },
          {
            "id": 7,
            "name": "Carboidrato",
            "unit": "g",
            "amount_value": "10.20",
            "status": "EXACT"
          },
          {
            "id": 15,
            "name": "Sódio",
            "unit": "mg",
            "amount_value": null,
            "status": "TRACE"
          }
        ]
      }
    ]
  }
}
```

*(Nota: `nutrients` dentro de cada versão é ordenado deterministicamente por `nutrient_id` crescente; `versions` é ordenado deterministicamente por `version_number` crescente.)*

---

## 2. Consultar alimento manual e histórico de versões (`GET /api/manual-foods/{food}`)

### Request

`GET /api/manual-foods/{food}`

### Comportamento

- Retorna `200 OK` com a mesma estrutura de `data` acima, contendo a versão vigente (`current_version`) e todas as versões históricas imutáveis (`versions`).
- Retorna `404 Not Found` quando `{food}` não existir ou não for um alimento manual (ex.: quando `{food}` referenciar um alimento oficial da base TACO — alimentos TACO são consultados via `GET /api/foods`, não via `/api/manual-foods`).

---

## 3. Atualizar alimento manual criando nova versão imutável (`PUT /api/manual-foods/{food}`)

### Request

`PUT /api/manual-foods/{food}`

Payload possui os mesmos campos de `POST /api/manual-foods` acrescido de:

| Campo adicional | Regra |
|---|---|
| `change_reason` | Obrigatório; string não vazia após trim justificando a nova versão |

### Comportamento e garantias

- Se `{food}` não existir ou pertencer à base TACO (não possuir `ManualFoodRegistration`), retorna `404 Not Found` (ou `422` sem jamais alterar o registro TACO).
- Cria a versão `current_version_number + 1` em `manual_food_versions` e `manual_food_version_nutrients` (preservando integralmente todas as versões `1..N` anteriores).
- Atualiza a projeção vigente em `foods` e `food_nutrients` com os dados da nova versão, aplicando as mesmas regras estritas de unicidade (excluindo o próprio `food_id` da verificação de colisão consigo mesmo).
- Cálculos históricos das Features 003, 004 e 005 anteriormente gerados permanecem integralmente inalterados.
- Retorna `200 OK` com o recurso completo atualizado (`current_version` apontando para a nova versão e `versions` contendo toda a série `1..N+1`).

---

## 4. Ativar ou inativar logicamente alimento manual (`PATCH /api/manual-foods/{food}/status`)

### Request

`PATCH /api/manual-foods/{food}/status`

```json
{
  "is_active": false
}
```

### Regras

- `is_active` obrigatório, booleano estrito (`boolean`).
- Se `{food}` não existir ou for um alimento da base TACO, retorna `404 Not Found` sem modificar nada.
- Atualiza `manual_food_registrations.is_active` e retorna `200 OK` com o recurso completo do alimento manual.

---

## 5. Impacto nos contratos existentes (Features 001, 002 e 003)

1. **`GET /api/foods?q=...` (Feature 001)**:
   - Mantém exatamente o formato de resposta (`data[].{id, name, category?, source, source_code?, nutrients}`).
   - Alimentos manuais com `is_active = true` aparecem na busca integrada com sua `source` e `source_code` explícitas (distintas de `TACO`).
   - Alimentos manuais com `is_active = false` são omitidos dos resultados de busca.
   - Alimentos da base TACO nunca são filtrados ou alterados.
2. **`POST /api/recipes` e `PUT /api/recipes/{recipe}` (Feature 002)**:
   - `POST /api/recipes` rejeita com erro de validação (`ingredients.*.food_id`) qualquer `food_id` que corresponda a um alimento manual com `is_active = false`.
   - `PUT /api/recipes/{recipe}` rejeita a adição de um novo ingrediente cujo `food_id` corresponda a um alimento manual com `is_active = false`, mas permite manter um `food_id` já pertencente à receita antes de sua inativação.
3. **`POST /api/recipes/{recipe}/nutrition-calculations` (Feature 003)**:
   - Consome normalmente os nutrientes vigentes do alimento manual em `foods` e `food_nutrients`, preservando `EXACT`, estados qualitativos (`TRACE`, `NA`, `ABSENT`, `REEVALUATION`) e ausência de relação (`is_missing_relation = true`), sem alterar nenhum cálculo histórico já gravado.
