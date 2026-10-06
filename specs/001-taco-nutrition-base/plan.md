# Implementation Plan: TACO Nutrition Base

**Branch**: `001-taco-nutrition-base`  
**Date**: 2026-10-05  
**Spec**: `specs/001-taco-nutrition-base/spec.md`

**Input**: Feature specification from
`/specs/001-taco-nutrition-base/spec.md`

**Note**: This plan is derived from the approved feature specification
and the NutriLabel Constitution. Decisions not defined by those
artifacts must be documented and justified before implementation.

## Summary

A feature Base Nutricional TACO será responsável por armazenar,
preservar e disponibilizar para consulta os alimentos e componentes
nutricionais provenientes da TACO.

A modelagem deve permitir preservar todos os componentes disponíveis
na fonte sem exigir uma coluna fixa no schema para cada nutriente.

Cada informação nutricional deve preservar separadamente o valor
numérico, quando disponível, e seu estado, distinguindo:

- `EXATO`
- `TRACO`
- `NA`
- `AUSENTE`

Esta feature não define a interpretação matemática desses estados e
não implementa cálculos nutricionais.

Todos os componentes descritos e disponíveis na TACO serão preservados
na base nutricional. Isso não implica que todos serão utilizados pelo
MVP. O conjunto de nutrientes efetivamente utilizado nos cálculos e na
rotulagem será definido em specifications posteriores.

A consulta de alimentos deverá permitir busca parcial pelo
nome/descrição, ignorando diferenças entre letras maiúsculas e
minúsculas e acentuação.

## Technical Context

**Language/Version**: PHP conforme versão utilizada pelo projeto Laravel existente

**Primary Dependencies**: Laravel conforme versão instalada no projeto

**Storage**: PostgreSQL

**Search**: `ILIKE` para ignorar diferenças entre maiúsculas/minúsculas e extensão PostgreSQL `unaccent` para ignorar diferenças de acentuação

**Search Input**: `q` terá espaços externos removidos antes da validação e antes de ser entregue ao `NutritionBaseService`

**Import**: Laravel Artisan Command `php artisan nutrition:import-taco`, consumindo os CSVs em `resources/data/taco/processed/` e delegando a persistência ao `NutritionBaseService`

**Testing**: PHPUnit / Laravel Testing

**Target Platform**: Aplicação Web

**Project Type**: Web Application

**Performance Goals**: Não há meta quantitativa de performance definida nesta specification.

**Constraints**:

- Esta feature não implementa cálculos nutricionais.
- Esta feature não interpreta matematicamente `TRACO`, `NA` ou `AUSENTE`.
- Esta feature não implementa receitas.
- Esta feature não implementa cálculo por porção.
- Esta feature não implementa cálculo de %VD.
- Esta feature não implementa regras de arredondamento de rotulagem.
- Esta feature não implementa rotulagem frontal.
- Esta feature não implementa geração de PDF.
- Esta feature não implementa autenticação.
- Nenhuma biblioteca ou framework externo deve ser introduzido sem aprovação explícita da equipe.
- Valores provenientes da TACO não podem ser convertidos silenciosamente para zero.
- A origem e o estado dos dados nutricionais devem permanecer rastreáveis.

**Scale/Scope**:

A feature contempla os alimentos e todos os componentes nutricionais
descritos e disponíveis na TACO fornecida ao projeto.

A fonte autoritativa é
`resources/data/taco/source/taco_4_edicao_ampliada_e_revisada.pdf`, Tabela
Brasileira de Composição de Alimentos — TACO, 4ª edição revisada e
ampliada, NEPA/UNICAMP, 2011.

Todos esses componentes serão preservados na base, mesmo que parte deles
não seja utilizada pelo MVP.

## Constitution Check

**GATE: Deve estar em conformidade antes das próximas etapas de design
e implementação.**

- [x] **Princípio de Não Invenção**  
  Valores nutricionais ausentes, regras ou comportamentos não
  documentados não serão inferidos ou criados automaticamente.

- [x] **Stack Definida**  
  A implementação utilizará PHP, Laravel e PostgreSQL conforme definido
  na Constitution.

- [x] **Separação de Responsabilidades**  
  Controllers serão responsáveis pela orquestração das requisições.
  Regras da feature permanecerão separadas em Services e demais
  componentes apropriados da arquitetura Laravel.

- [x] **Preservação dos Dados Nutricionais**  
  Valores numéricos e estados `EXATO`, `TRACO`, `NA` e `AUSENTE`
  permanecerão distintos.

- [x] **Preservação da Fonte**  
  Os dados armazenados devem manter rastreabilidade da origem TACO.

- [x] **Flexibilidade da Base Nutricional**  
  A modelagem deve ser capaz de representar todos os componentes
  nutricionais disponíveis na TACO sem exigir uma coluna específica no
  schema para cada nutriente.

- [x] **Escopo da Feature Respeitado**  
  Cálculos nutricionais, receitas, rotulagem, exportação e autenticação
  permanecem fora desta feature.

- [x] **Testabilidade**  
  A preservação dos dados e a consulta de alimentos devem poder ser
  verificadas por testes automatizados e comparação com a fonte.

## Project Structure

### Documentation

```text
specs/001-taco-nutrition-base/
├── spec.md
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
└── tasks.md
```

### Application Code

```text
app/
├── Console/
│   └── Commands/
├── Http/
│   ├── Controllers/
│   │   └── FoodController.php
│   └── Requests/
│       └── FoodSearchRequest.php
├── Models/
│   ├── Food.php
│   ├── Nutrient.php
│   └── FoodNutrient.php
└── Services/
    └── NutritionBaseService.php

database/
└── migrations/

resources/
└── data/
    └── taco/
        ├── source/
        │   └── taco_4_edicao_ampliada_e_revisada.pdf
        └── processed/
            ├── foods.csv
            ├── nutrients.csv
            └── food_nutrients.csv

tests/
└── Feature/
```
