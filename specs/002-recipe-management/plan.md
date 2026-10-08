# Implementation Plan: Recipe Management

**Branch**: `002-recipe-management` | **Date**: 2026-10-07 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/002-recipe-management/spec.md`

## Summary

Implementar o gerenciamento persistente de receitas como um agregado formado por
`Recipe` e sua coleção ordenada de `RecipeIngredient`. Cada ingrediente referencia
um `Food` existente por `foods.id`, informa quantidade positiva exclusivamente em
gramas e não altera a Base Nutricional. A receita preserva nome e rendimento
positivo informado em `g` ou `ml`, sem conversão, inferência ou cálculo
nutricional.

A API terá somente operações para criar, consultar e substituir uma receita
completa. A substituição integral da coleção, executada em transação pelo
`RecipeService`, cobre adição, edição, remoção e reordenação sem endpoints ou
estados adicionais. Form Requests validam a estrutura HTTP; Models representam os
relacionamentos; migrations PostgreSQL aplicam integridade referencial e
constraints de domínio.

## Technical Context

**Language/Version**: PHP 8.4.25

**Primary Dependencies**: Laravel Framework 13.34.0, Eloquent ORM, Laravel
Validation/Form Requests e Laravel Migrations; nenhuma dependência nova

**Storage**: PostgreSQL 17.11, usando o banco configurado pela aplicação

**Testing**: PHPUnit 12.5.38 por `php artisan test`, com PostgreSQL selecionado em
`phpunit.xml` e credenciais somente no ambiente local

**Target Platform**: Aplicação HTTP Laravel; ambiente de desenvolvimento atual em
Windows x64, sem decisão adicional de plataforma de implantação

**Project Type**: Aplicação web/API Laravel monolítica

**Performance Goals**: Nenhuma meta quantitativa foi aprovada; priorizar
persistência atômica e reprodução exata das entradas

**Constraints**: Controllers somente orquestram; escrita de receita e ingredientes
é atômica; decimais não sofrem arredondamento silencioso; somente PostgreSQL;
nenhum cálculo nutricional; nenhuma biblioteca adicional

**Scale/Scope**: Uma receita possui um ou mais ingredientes; não existe limite
funcional aprovado para quantidade de receitas, ingredientes, tamanho do nome ou
casas decimais

## Constitution Check

*GATE inicial e pós-design: aprovado.*

| Princípio | Verificação do plano | Resultado |
|---|---|---|
| Não invenção | Somente nome, ingredientes, ordem e rendimento aprovados são modelados; ausências continuam explícitas e não geram funcionalidades adicionais. | PASS |
| Stack definida | PHP/Laravel, Eloquent, Form Requests, Services, Migrations e PostgreSQL já instalados; nenhuma biblioteca nova. | PASS |
| Separação de responsabilidades | Routes → Controller → RecipeService → Models → PostgreSQL; Controllers não contêm regras de persistência. | PASS |
| Dados nutricionais e TACO | `Food` é apenas referenciado por `foods.id`; importação, busca e estados nutricionais da feature 001 não são reimplementados. | PASS |
| Receitas e rendimento | Quantidade dos ingredientes e rendimento permanecem distintos; o rendimento nunca é inferido da soma dos ingredientes. | PASS |
| Motor de cálculo | Nenhum cálculo, conversão, %VD, porção, arredondamento ou resultado nutricional é produzido ou armazenado. | PASS |
| Banco e testes | Schema versionado por migrations e testes executados em PostgreSQL sem credenciais versionadas. | PASS |
| Escopo e governança | Não há autenticação, rascunho, busca/listagem de receitas, histórico, rotulagem ou exportação. | PASS |

### Reavaliação pós-design

O data model contém somente `Recipe` e `RecipeIngredient` e referencia o `Food`
existente. Os contratos expõem apenas criação, consulta e substituição integral da
receita. O quickstart valida exclusivamente os cenários da specification. Nenhuma
violação constitucional foi introduzida após o design.

## Project Structure

### Documentation (this feature)

```text
specs/002-recipe-management/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── recipes.md
└── tasks.md                 # somente na etapa posterior $speckit-tasks
```

### Source Code (repository root)

```text
app/
├── Http/
│   ├── Controllers/
│   │   └── RecipeController.php
│   └── Requests/
│       ├── StoreRecipeRequest.php
│       └── UpdateRecipeRequest.php
├── Models/
│   ├── Food.php                         # existente; não alterado nesta feature
│   ├── Recipe.php
│   └── RecipeIngredient.php
└── Services/
    └── RecipeService.php

database/
├── factories/
│   ├── RecipeFactory.php
│   └── RecipeIngredientFactory.php
└── migrations/
    ├── *_create_recipes_table.php
    └── *_create_recipe_ingredients_table.php

routes/
└── api.php

tests/
└── Feature/
    ├── RecipeManagementTest.php
    └── RecipePersistenceTest.php
```

**Structure Decision**: Manter a estrutura Laravel já existente. Os Form Requests
validam o contrato de entrada; `RecipeController` apenas encaminha dados validados
ao `RecipeService`; o Service aplica criação e substituição transacional do
agregado; os Models expressam os relacionamentos; as migrations PostgreSQL são a
fonte do schema. A resposta JSON segue a convenção atual de envelope `data`, sem
introduzir camada ou biblioteca externa.

## Design Decisions

- Criar as tabelas `recipes` e `recipe_ingredients`, sem entidades adicionais.
- Usar `NUMERIC` PostgreSQL sem precisão/escala declaradas para `quantity` e
  `yield_value`, evitando coerção ou arredondamento por uma escala arbitrária.
- Transportar e serializar os decimais como strings de base 10 para não convertê-los
  em ponto flutuante no PHP ou no JSON.
- Manter `TrimStrings` e `ConvertEmptyStringsToNull` na fronteira HTTP: strings,
  inclusive em arrays aninhados, recebem trim e valores vazios resultantes tornam-se
  `null` antes do Form Request. Essa normalização de transporte não converte
  unidades, números ou caixa e não arredonda valores.
- Aplicar constraints no banco para positividade, unidades permitidas, referência a
  `foods.id` e unicidade de posição dentro da receita.
- Representar a ordem por `position` positiva e única por receita; lacunas são
  permitidas e nenhuma normalização automática é executada.
- Criar e editar o agregado dentro de `DB::transaction`, impedindo persistência
  parcial.
- Usar `PUT /api/recipes/{recipe}` como substituição integral da coleção: os itens
  enviados formam o estado final, itens omitidos são removidos e nenhuma identidade
  estável de `RecipeIngredient` é prometida pelo contrato.
- Manter a ação referencial padrão `NO ACTION` de `recipe_ingredients.food_id`; a
  feature não cria operação de exclusão de `Food` nem define política funcional
  futura para esse caso.

## Complexity Tracking

Nenhuma violação da Constitution requer justificativa.
