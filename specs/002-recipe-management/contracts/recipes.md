# Contract: Recipe Management

## Scope

Este contrato cobre somente criação, consulta e substituição integral de receitas.
Adição, edição, remoção e reordenação de ingredientes são representadas pelo estado
completo enviado na criação ou edição.

Não há neste contrato listagem, busca ou exclusão de receitas, endpoints isolados de
ingredientes, autenticação ou resultados nutricionais.

Códigos HTTP específicos não são fixados nesta feature porque não existe decisão
aprovada para eles. O contrato define rotas, entradas, resultados e condições de
falha sem acrescentar essa decisão.

## Representação da receita

```json
{
  "id": 10,
  "name": "Receita de exemplo",
  "yield_value": "850.125",
  "yield_unit": "g",
  "ingredients": [
    {
      "food_id": 25,
      "quantity": "125.5",
      "unit": "g",
      "position": 1
    },
    {
      "food_id": 31,
      "quantity": "0.375",
      "unit": "g",
      "position": 2
    }
  ]
}
```

Os valores são exemplos de formato e não representam receitas ou dados reais.

### Regras dos campos

| Campo | Regra |
|---|---|
| `id` | Identificação interna da receita; somente leitura |
| `name` | Obrigatório e textual; nenhum outro campo de identificação de produto é aceito |
| `yield_value` | String decimal em notação de base 10, maior que zero; frações permitidas |
| `yield_unit` | Obrigatório; `g` ou `ml` |
| `ingredients` | Array obrigatório com ao menos um item |
| `ingredients[].food_id` | Obrigatório; deve corresponder a `foods.id` existente |
| `ingredients[].quantity` | String decimal em notação de base 10, maior que zero; frações permitidas |
| `ingredients[].unit` | Obrigatório e exatamente `g` |
| `ingredients[].position` | Inteiro maior que zero e distinto dentro da receita |

Strings decimais aceitam somente algarismos e parte fracionária opcional separada por
ponto, sem sinal ou expoente. A validação de formato pode ser expressa por
`^[0-9]+(?:\.[0-9]+)?$`, seguida da validação de que o valor é maior que zero.
Nenhum limite funcional de casas decimais é imposto.

### Normalização de transporte HTTP

Antes do Form Request, permanecem ativos os middlewares globais do Laravel
`TrimStrings` e `ConvertEmptyStringsToNull`. Eles percorrem também arrays aninhados.
Consequentemente:

- `" Receita "` é validado como `"Receita"`;
- `" 1.250 "` é validado como a string decimal `"1.250"`;
- `" g "` é validado como `"g"`;
- `""` e `"   "` são convertidos para `null` e submetidos normalmente às regras de
  obrigatoriedade e validação.

Essa normalização não altera caixa, não converte unidades, não arredonda e não
converte `quantity` ou `yield_value` para tipo numérico. Ambos continuam sendo
strings decimais de base 10 após o trim.

## Criar receita

### Request

`POST /api/recipes`

```json
{
  "name": "Receita de exemplo",
  "yield_value": "850.125",
  "yield_unit": "g",
  "ingredients": [
    {
      "food_id": 25,
      "quantity": "125.5",
      "unit": "g",
      "position": 1
    }
  ]
}
```

### Resposta de sucesso

```json
{
  "data": {
    "id": 10,
    "name": "Receita de exemplo",
    "yield_value": "850.125",
    "yield_unit": "g",
    "ingredients": [
      {
        "food_id": 25,
        "quantity": "125.5",
        "unit": "g",
        "position": 1
      }
    ]
  }
}
```

A receita e todos os ingredientes são gravados em uma única transação. Qualquer
falha impede a persistência de todo o agregado.

## Consultar receita

### Request

`GET /api/recipes/{recipe}`

### Resposta de sucesso

```json
{
  "data": {
    "id": 10,
    "name": "Receita de exemplo",
    "yield_value": "850.125",
    "yield_unit": "g",
    "ingredients": [
      {
        "food_id": 25,
        "quantity": "125.5",
        "unit": "g",
        "position": 1
      }
    ]
  }
}
```

`ingredients` deve ser retornado em ordem crescente de `position`. Os decimais são
retornados como strings e não como números de ponto flutuante.

## Editar receita e ingredientes

### Request

`PUT /api/recipes/{recipe}`

O payload possui a mesma estrutura obrigatória da criação e representa o estado
completo desejado.

```json
{
  "name": "Receita atualizada",
  "yield_value": "900.5",
  "yield_unit": "ml",
  "ingredients": [
    {
      "food_id": 31,
      "quantity": "50.25",
      "unit": "g",
      "position": 2
    },
    {
      "food_id": 25,
      "quantity": "200.125",
      "unit": "g",
      "position": 1
    }
  ]
}
```

Semântica da substituição:

- ingredientes enviados formam a coleção final;
- ingrediente anteriormente persistido e omitido é removido da receita;
- novo `food_id` somente substitui o anterior quando enviado explicitamente;
- `position` define a ordem final sem alterar os demais campos;
- array vazio ou ausente é inválido;
- a atualização inteira é transacional.

### Resposta de sucesso

O corpo segue a representação da receita e retorna os ingredientes ordenados pela
posição persistida.

## Falhas

### Validação

Usar a estrutura JSON padrão de erros de validação do Laravel. São falhas de
validação, entre outras:

- campo obrigatório ausente;
- receita sem ingredientes;
- `food_id` inexistente;
- quantidade ou rendimento igual a zero ou negativo;
- decimal fora da notação contratada;
- unidade de ingrediente diferente de `g`;
- unidade de rendimento diferente de `g` e `ml`;
- posição ausente, não positiva ou repetida.

Nenhuma falha de validação cria ou modifica parcialmente uma receita.

### Receita inexistente

A consulta ou edição de `{recipe}` inexistente deve falhar sem criar ou modificar
uma receita.

## Dependência da pesquisa de alimentos

A seleção do `food_id` reutiliza `GET /api/foods?q=...`, definido em
`specs/001-taco-nutrition-base/contracts/search-foods.md`. Este contrato não altera
a busca nem retorna nutrientes calculados.
