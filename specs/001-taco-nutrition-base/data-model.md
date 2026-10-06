# Data Model: 001-taco-nutrition-base

## Overview

A Base Nutricional será modelada utilizando três entidades principais:

- `Food`
- `Nutrient`
- `FoodNutrient`

A estrutura permite armazenar todos os componentes nutricionais
disponíveis na TACO sem criar uma coluna fixa no banco para cada
componente.

A fonte autoritativa desta feature é o arquivo
`resources/data/taco/source/taco_4_edicao_ampliada_e_revisada.pdf`, correspondente à Tabela
Brasileira de Composição de Alimentos — TACO, 4ª edição revisada e
ampliada, NEPA/UNICAMP, 2011.

Relação conceitual:

Food
  1
  │
  N
FoodNutrient
  N
  │
  1
Nutrient

`FoodNutrient` representa a informação de um componente nutricional
associado a determinado alimento.

---

## 1. `foods`

Representa um alimento proveniente da base nutricional.

### Campos

- `id`
  - chave primária;
  - tipo concreto definido conforme os padrões do projeto Laravel.

- `name`
  - nome/descrição original do alimento conforme a fonte;
  - utilizado também na pesquisa de alimentos.

- `category`
  - categoria ou grupo do alimento quando disponível na fonte;
  - opcional quando essa informação não estiver disponível.

- `source`
  - identifica a origem do registro;
  - nesta feature, o valor corresponde à TACO.

- `source_code`
  - identificação/código original do alimento na fonte, quando
    disponível.

- `created_at`
  - timestamp padrão do Laravel.

- `updated_at`
  - timestamp padrão do Laravel.

### Regras

- O campo `name` deve preservar a descrição original utilizada na fonte.
- `source` deve permitir identificar explicitamente que o dado foi
  proveniente da TACO.
- `source_code` não deve ser inventado quando não existir na fonte.
- Esta feature não adiciona descrição, observação ou metadado criado pelo
  sistema que não tenha sido definido na specification.
- Para alimentos provenientes da TACO, a identidade lógica de importação e
  reimportação é `source + source_code`, sendo `source = TACO` e
  `source_code` o número/código original fornecido pela TACO.
- Quando já existir um `Food` com o mesmo `source + source_code`, a
  reimportação deve atualizar esse registro, sem criar duplicata e
  preservando o mesmo `foods.id`.
- Para um registro da TACO que deveria representar um alimento, a ausência
  de `source_code` é um problema de importação. O nome não deve ser usado
  como fallback silencioso.

---

## 2. `nutrients`

Representa um componente nutricional disponível na fonte.

### Campos

- `id`
  - chave primária.

- `name`
  - nome do componente nutricional conforme mapeamento da fonte.

- `unit`
  - unidade utilizada pelo componente nutricional;
  - exemplos existentes na fonte incluem unidades como g, mg, µg,
    kcal e kJ.

- `created_at`
  - timestamp padrão do Laravel.

- `updated_at`
  - timestamp padrão do Laravel.

### Regras

- Cada componente deve manter sua unidade correspondente.
- O modelo não cria símbolos ou siglas próprias sem origem documentada.
- Todos os componentes nutricionais definidos para importação da TACO
  devem poder ser representados nessa estrutura.
- A identidade lógica de um `Nutrient` nesta feature é `name + unit`.
- Durante a reimportação, o `Nutrient` existente deve ser reutilizado
  quando `name + unit` coincidirem, sem criação de duplicata para o mesmo
  par.

---

## 3. `food_nutrients`

Representa a informação nutricional de determinado componente associada
a determinado alimento.

Esta tabela deve ser tratada como uma entidade relacional com dados
próprios, e não somente como uma pivot table transparente.

### Campos

- `id`
  - chave primária.

- `food_id`
  - chave estrangeira para `foods.id`.

- `nutrient_id`
  - chave estrangeira para `nutrients.id`.

- `amount_value`
  - valor numérico proveniente da fonte quando aplicável;
  - nullable quando não existir valor numérico;
  - deve utilizar representação numérica capaz de preservar a precisão
    original da TACO;
  - a precisão/escala concreta não deve causar arredondamento dos dados
    de origem.

- `status`
  - estado do valor nutricional.

Valores permitidos:

- `EXACT`
- `TRACE`
- `NA`
- `ABSENT`

- `created_at`
  - timestamp padrão do Laravel.

- `updated_at`
  - timestamp padrão do Laravel.

### Regras de status

#### `EXACT`

Utilizado quando a fonte fornece um valor numérico.

Nesse estado:

- `amount_value` contém o valor numérico preservado da fonte.

#### `TRACE`

Utilizado quando a fonte apresenta `Tr`.

Nesta feature:

- o marcador deve permanecer identificado como `TRACE`;
- nenhuma conversão matemática para zero ou outro valor será realizada.

#### `NA`

Utilizado quando a fonte apresenta `NA`.

Nesta feature:

- o marcador deve permanecer identificado como `NA`;
- nenhuma interpretação matemática será realizada.

#### `ABSENT`

Utilizado somente quando a estrutura da fonte possui explicitamente uma
posição correspondente ao componente para aquele alimento e a posição
não possui valor.

O sistema não deve criar registros `ABSENT` por meio de um produto
cartesiano entre todos os alimentos e todos os nutrientes.

A inexistência de um registro `food_nutrients` não deve ser
automaticamente interpretada como `ABSENT`.

---

## Relacionamentos Eloquent

### `Food`

Relacionamento principal:

`hasMany(FoodNutrient)`

Conceitualmente:

Food
  1
  │
  N
FoodNutrient

### `Nutrient`

Relacionamento principal:

`hasMany(FoodNutrient)`

Conceitualmente:

Nutrient
  1
  │
  N
FoodNutrient

### `FoodNutrient`

Relacionamentos:

`belongsTo(Food)`

`belongsTo(Nutrient)`

### Relacionamentos de conveniência

Relacionamentos adicionais como `belongsToMany` poderão ser utilizados
como conveniência do Eloquent caso sejam necessários posteriormente,
mas `FoodNutrient` continua sendo uma entidade explícita devido aos
campos:

- `amount_value`;
- `status`.

---

## Constraints

### Unicidade

A combinação:

`food_id + nutrient_id`

deve ser única.

Isso impede múltiplos valores para o mesmo componente do mesmo alimento
dentro desta base.

Durante a reimportação, a associação correspondente deve ser atualizada ou
criada com base em `food_id + nutrient_id`, sem criação de duplicatas para
a mesma combinação.

### Integridade referencial

`food_nutrients.food_id`

deve referenciar um registro válido de:

`foods.id`

`food_nutrients.nutrient_id`

deve referenciar um registro válido de:

`nutrients.id`

### Preservação dos estados

A modelagem não deve permitir que:

- `TRACE`;
- `NA`;
- `ABSENT`;

sejam convertidos automaticamente para zero.

---

## Search Model

A pesquisa definida nesta feature será realizada sobre:

`foods.name`

Comportamento obrigatório:

- busca parcial;
- sem diferenciação entre maiúsculas/minúsculas;
- sem diferenciação de acentuação.

Exemplo conceitual:

Pesquisa:

`acucar`

deve conseguir localizar um alimento cujo nome contenha:

`Açúcar`

A implementação concreta deve utilizar recursos compatíveis com
PostgreSQL e Laravel conforme documentado no `research.md`.

---

## Data Import Rules

Durante a importação:

- `resources/data/taco/processed/foods.csv` fornece os alimentos e os dados
  aprovados para `Food`;
- `resources/data/taco/processed/nutrients.csv` fornece os componentes
  nutricionais e suas unidades;
- `resources/data/taco/processed/food_nutrients.csv` fornece a relação entre
  alimento e nutriente, preservando `amount_value` e `status`.

Os três arquivos devem ser derivados exclusivamente de
`resources/data/taco/source/taco_4_edicao_ampliada_e_revisada.pdf`.

### Valor numérico

Quando a fonte contiver um número:

```text
status = EXACT
amount_value = valor da fonte
```

Zero numérico permanece um valor numérico com `status = EXACT` e é
diferente de `TRACE`.

### Traço

Quando a fonte contiver `Tr`:

```text
status = TRACE
amount_value = null
```

### Não aplicável

Quando a fonte contiver `NA`:

```text
status = NA
amount_value = null
```

### Ausente

Quando existir explicitamente uma célula na estrutura da fonte para o
componente do alimento e essa célula estiver sem valor:

```text
status = ABSENT
amount_value = null
```

`TRACE`, `NA` e `ABSENT` são estados distintos. A importação não deve criar
registros `ABSENT` por produto cartesiano entre alimentos e nutrientes, e a
ausência de uma relação `FoodNutrient` não significa automaticamente
`ABSENT`.
