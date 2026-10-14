# Implementation Plan: Nutrition Calculation

**Branch**: `003-nutrition-calculation` | **Date**: 2026-10-13 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/003-nutrition-calculation/spec.md`

## Summary

Implementar, em Laravel 13 e PostgreSQL, execuções independentes e imutáveis do
cálculo nutricional de uma `Recipe` persistida. O cálculo processará todos os
`Nutrients`, obterá cada contribuição `EXACT` por
`quantidade × valor por 100 g ÷ 100`, preservará estados qualitativos e ausência de
relação, consolidará por nutriente e normalizará o valor numérico disponível pelo
rendimento para 100 g ou 100 ml. Cada solicitação criará um resultado preservado,
com snapshot das entradas e identificador `nutrition-calculation-v1` das regras.

Valores calculados serão mantidos como razões exatas (numerador/denominador), sem
`float`, escala arbitrária ou arredondamento. A execução inteira — leitura coerente,
cálculo e gravação do agregado — ocorrerá em transação PostgreSQL. Controllers
somente orquestrarão Form Request/Service/Resource; as regras permanecerão no
Service.

## Technical Context

**Language/Version**: PHP 8.4.25

**Primary Dependencies**: Laravel Framework 13.34.0; Eloquent ORM; Validation/Form
Requests; API Resources; `brick/math` 1.0.0, atualmente transitivo via
`laravel/framework` e `ramsey/uuid`, aprovado para `BigRational` e obrigatoriamente
declarado como dependência direta antes de qualquer importação `Brick\Math`

**Storage**: PostgreSQL 17; migrations Laravel; `NUMERIC` sem precisão/escala para
decimais de entrada e inteiros arbitrariamente grandes das razões

**Testing**: PHPUnit 12.5.38 via `php artisan test`, PostgreSQL e
`DatabaseTransactions`

**Target Platform**: aplicação web Laravel no ambiente PHP 8.4 do projeto

**Project Type**: API web em aplicação Laravel única

**Performance Goals**: evitar N+1; carregar os nutrientes uma vez e os
`FoodNutrients` dos alimentos participantes em consulta agrupada; processar cada
ocorrência de ingrediente contra todos os nutrientes porque essa matriz é requisito
funcional

**Constraints**: nenhuma aritmética binária de ponto flutuante; nenhum arredondamento;
nenhuma conversão g↔ml; resultados e snapshots imutáveis; todos os estados e
ausências explícitos; uma nova execução nunca substitui outra

**Scale/Scope**: uma receita persistida por solicitação, todas as ocorrências de seus
ingredientes e todos os nutrientes existentes na Base Nutricional; sem cálculo por
porção, %VD, energia por fatores, regras regulatórias ou rotulagem

## Constitution Check

*GATE: aprovado antes da pesquisa e revalidado após o design.*

| Princípio | Resultado | Evidência do plano |
|---|---|---|
| Não invenção | PASS | Estados, ausências, subtotal incompleto e normalização seguem decisões aprovadas; o caso da geleia permanece bloqueado onde faltam dados identificáveis. |
| Stack definida | PASS | PHP/Laravel/PostgreSQL; o uso de `brick/math` foi aprovado. Como hoje é transitivo, sua declaração direta com versão compatível ao lock será uma task explícita e validada antes do código que importe `Brick\Math`. |
| Arquitetura | PASS | Routes → Controller → Service → Models → PostgreSQL; cálculo somente no Service. |
| Motor de cálculo | PASS | Contribuição, consolidação e rendimento são etapas separadas; razões exatas evitam perda e não há arredondamento. |
| Dados nutricionais | PASS | `EXACT`, `TRACE`, `NA`, `ABSENT`, `REEVALUATION` e ausência de relação continuam distintos; nenhum caso qualitativo vira zero. |
| Regras regulatórias | PASS | Porção, %VD, energia por fatores, arredondamento e rótulo permanecem fora do escopo. |
| Banco e testes | PASS | Alterações estruturais por migrations; PostgreSQL real; cobertura determinística das fórmulas, estados, transação, snapshot e imutabilidade. |

### Revalidação pós-design

O modelo relacional preserva rastreabilidade sem depender dos registros mutáveis
atuais; o contrato não cria funcionalidade de edição/exclusão; a transação impede
resultado parcial; e a representação racional preserva exatamente resultados
terminantes ou periódicos. Não há violação a justificar.

## Project Structure

### Documentation (this feature)

```text
specs/003-nutrition-calculation/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── nutrition-calculations.md
└── tasks.md                         # futuro; não criado por este Plan
```

### Source Code (repository root)

```text
app/
├── Http/
│   ├── Controllers/
│   │   └── NutritionCalculationController.php
│   └── Resources/
│       └── NutritionCalculationResource.php
├── Models/
│   ├── NutritionCalculation.php
│   ├── NutritionCalculationIngredient.php
│   ├── NutritionCalculationNutrient.php
│   ├── NutritionCalculationContribution.php
│   └── NutritionCalculationNutrientState.php
└── Services/
    ├── NutritionCalculationArithmetic.php
    └── NutritionCalculationService.php

database/
├── factories/                       # somente factories dos novos Models usadas por testes
└── migrations/                      # tabelas e proteções de imutabilidade do agregado

routes/
└── api.php

composer.json                         # declarar brick/math diretamente antes do código

tests/
├── Feature/
│   ├── NutritionCalculationTest.php
│   └── NutritionCalculationPersistenceTest.php
└── Unit/
    └── NutritionCalculationArithmeticTest.php
```

`Recipe`, `RecipeIngredient`, `Food`, `Nutrient` e `FoodNutrient` são dependências
existentes e não serão alterados para incorporar resultados. A estrutura segue o
padrão Laravel já adotado; não cria nova pasta-base nem camada de repositório.

`NutritionCalculationArithmetic` é somente o helper técnico de aritmética racional
exata com `Brick\Math\BigRational`. Ele apoia o `NutritionCalculationService` e não
constitui novo domínio; regras funcionais permanecem no Service e não são movidas
para Controller, Resource ou Model.

Antes da implementação do motor, uma task de Setup deverá declarar `brick/math` como
dependência direta usando uma constraint compatível com a versão 1.0.0 já resolvida
no lock e validar a alteração de `composer.json`/`composer.lock`. Nenhum arquivo de
dependências é alterado neste Plan.

## Design Flow

```text
POST execução
  → carregar Recipe e ocorrências ordenadas
  → carregar todos os Nutrients e FoodNutrients pertinentes
  → capturar snapshot coerente
  → calcular contribuição por ocorrência × nutriente
  → consolidar valor EXACT + estados + ausência de relação
  → normalizar o valor numérico disponível pelo rendimento
  → persistir execução e filhos de modo atômico e imutável
  → retornar a execução preservada

GET execução
  → ler somente o agregado preservado
  → não consultar valores atuais para reconstruir o resultado histórico
```

## Complexity Tracking

Não há violações da Constitution. A decomposição em tabelas filhas é necessária
para representar o snapshot, as contribuições e o conjunto de estados com
constraints verificáveis; não constitui novo domínio nem histórico avançado.
