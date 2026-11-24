# Implementation Plan: Added Sugars Formulation Obtaining and Validation

**Branch**: `007-added-sugars-formulation` | **Date**: 2026-11-23 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/007-added-sugars-formulation/spec.md`

## Summary

Implementar a obtenção, governança, validação técnica *append-only* e avaliação imutável de açúcares adicionados por formulação (`007-added-sugars-formulation`), suprindo de forma rastreável a lacuna `MAP-004` da Base Nutricional TACO sem criar nutrientes artificiais na TACO nem alterar execuções históricas das Features 003–005.

A arquitetura adiciona formulações versionadas e imutáveis por receita (`AddedSugarsFormulation`, `AddedSugarsFormulationVersion`, `AddedSugarsFormulationIngredient`, `AddedSugarsFormulationEvidence`), trilha *append-only* de validações técnicas (`AddedSugarsFormulationValidation`) em modo *deny-by-default* (`TEST_FIXTURE` restrito a `testing`), e snapshots históricos imutáveis de avaliação vinculados a uma única `NutritionCalculation` da Feature 003 (`AddedSugarsEvaluation`, `AddedSugarsEvaluationItem`, `AddedSugarsEvaluationCause`). Além disso, estende de forma 100% aditiva e retrocompatível as Features 004 e 005 para consumir opcionalmente uma `AddedSugarsEvaluation` em `MAP-004` (porção, `100 g/100 ml` e `%VD` sob `VDR-004` autorizado), sem adicionar fator energético extra.

## Technical Context

**Language/Version**: PHP 8.4 (Docker/Sail) / PHP 8.5.6 local CLI

**Primary Dependencies**: Laravel Framework 13.43.0; Eloquent ORM; `brick/math` 1.0.0 (`BigRational`); Laravel Form Requests e API Resources; sem novas dependências externas

**Storage**: PostgreSQL 17; 8 novas tabelas de governança/versionamento/avaliação protegidas por `CHECK`, `UNIQUE`, `FOREIGN KEY ... ON DELETE RESTRICT` e triggers PostgreSQL bloqueando `UPDATE`/`DELETE` em dados imutáveis; extensões aditivas nullable em `serving_daily_value_calculations` e `serving_daily_value_regulatory_results`

**Testing**: PHPUnit 12.5.44 via `php artisan test --compact`; PostgreSQL real; regressão integral das Features 001–006; Laravel Pint; `git diff --check`

**Target Platform**: API web Laravel executada em ambiente Windows/Docker compatível com PostgreSQL 17

**Project Type**: Serviço web monolítico Laravel, API JSON backend

**Performance Goals**: Registro de versão, validação *append-only* e avaliação imutável executados em transações atômicas PostgreSQL `REPEATABLE READ`, com *eager loading* sem consultas N+1 e ordenação determinística

**Constraints**:
- Proibido criar `nutrient_id` artificial ou modificar qualquer registro da Base Nutricional TACO (`foods`, `nutrients`, `food_nutrients`);
- Proibido usar `float`/`double` para teores, quantidades ou razões (apenas strings decimais validadas e `BigRational` via `NutritionCalculationArithmetic`);
- Proibido converter dados ausentes, formulários não validados, conflitos de evidência ou relatos de "somente açúcares totais" em zero numérico;
- Agregados (`total` e `reference 100 g/100 ml`) permanecem estritamente `null` (sem subtotal parcial) sempre que qualquer ingrediente estiver ausente, descoberto, inconsistente, conflitante ou sem validação técnica autorizada (`CQ-005`);
- `TEST_FIXTURE` somente pode liberar validação técnica em `APP_ENV=testing`;
- Nenhum fator energético adicional é criado para açúcares adicionados (`FR-010`);
- Execuções históricas das Features 003, 004 e 005 permanecem integralmente intactas.

**Scale/Scope**: Qualquer receita existente na Feature 002 e qualquer execução nutricional da Feature 003; ingredientes simples e compostos/industrializados; série histórica ilimitada de versões de formulação, decisões de validação e avaliações imutáveis.

## Constitution Check

*GATE inicial e rechecagem pós-design (Phase 1): PASS.*

| Princípio / Gate | Evidência do desenho |
|---|---|
| **I. Princípio de Não Invenção** | Nenhuma inferência por nome do ingrediente, carboidratos ou açúcares totais; exclusões normativas exigem justificativa explícita; dados ausentes/conflitantes permanecem `null` com causa registrada. |
| **II. Stack Definida** | PHP 8.4+, Laravel 13, Eloquent, Form Requests, Resources, Services, `brick/math`, PostgreSQL 17 e PHPUnit; nenhuma dependência adicional introduzida. |
| **III. Arquitetura e Separação de Responsabilidades** | Fluxo estrito `Routes -> Controllers -> Services -> Models -> PostgreSQL`; Controllers finos delegam para `AddedSugarsFormulationService`, `AddedSugarsEvaluationService` e `AddedSugarsRegulatoryGateService`. |
| **IV. Motor de Cálculo** | Reutiliza `NutritionCalculationArithmetic` (`BigRational`) para `PER_100G`, `DIRECT_BATCH_GRAMS`, consolidação do lote, normalização para `100 g/100 ml` e proporcionalização por porção na Feature 004, sem `float` nem arredondamento intermediário. |
| **V. Dados Nutricionais e Fontes (TACO)** | Base TACO permanece intocada; todas as evidências (fabricante, laudo, formulário, norma) preservam origem, referência documental, responsável e data. |
| **VI. Regras Regulatórias** | Modo *deny-by-default* com trilha *append-only* de validações técnicas; `OPERATIONAL_FORM` nunca autoaprova; `TEST_FIXTURE` restrito a `testing`; `MAP-004`/`VDR-004` respeitam integralmente os gates das Features 004 e 005. |
| **Banco de Dados e Testes** | Migrations PostgreSQL com constraints literais e triggers contra `UPDATE`/`DELETE` em tabelas imutáveis; suítes dedicadas para persistência/triggers, regras/gates, integração 004/005 e regressão completa. |
| **Spec-Driven Development** | Desenho derivado integralmente de `spec.md` (`FR-001`–`FR-012`, `SC-001`–`SC-005`, `CQ-001`–`CQ-007`), sem antecipar código ou `tasks.md`. |

### Rechecagem após Phase 1

PASS. Os artefatos [research.md](./research.md), [data-model.md](./data-model.md), [contracts/added-sugars-formulation.md](./contracts/added-sugars-formulation.md) e [quickstart.md](./quickstart.md) atendem integralmente à Constitution 2.0.0 e à Specification clarificada, preservam 100% de retrocompatibilidade das Features 001–006 e mantêm todos os bloqueios regulatórios e de imutabilidade testáveis.

## Architecture

### Fluxo principal

```text
POST /api/recipes/{recipe}/added-sugars-formulations
  -> StoreAddedSugarsFormulationRequest (tipos, classificações, strings decimais exatas, limites físicos, evidências)
  -> AddedSugarsFormulationService::createVersion (transação REPEATABLE READ)
       -> cria/atualiza ponteiro AddedSugarsFormulation (current_version_number)
       -> cria AddedSugarsFormulationVersion imutável + ingredientes + evidências
  -> AddedSugarsFormulationResource (201 Created)

GET /api/recipes/{recipe}/added-sugars-formulations
  -> AddedSugarsFormulationService::retrieve (eager loading sem N+1)
  -> AddedSugarsFormulationResource (versão vigente + histórico + trilha de validações)

POST /api/added-sugars-formulation-versions/{version}/validations
  -> StoreAddedSugarsFormulationValidationRequest
  -> AddedSugarsFormulationService::recordValidation (append-only imutável)
  -> AddedSugarsFormulationValidationResource (201 Created)

POST /api/nutrition-calculations/{nutritionCalculation}/added-sugars-evaluations
  -> StoreAddedSugarsEvaluationRequest
  -> AddedSugarsEvaluationService::create (transação REPEATABLE READ)
       -> valida mesmo recipe_id entre NutritionCalculation e AddedSugarsFormulationVersion
       -> AddedSugarsRegulatoryGateService::evaluateVersionValidation (deny-by-default + escopo + env testing)
       -> avalia item a item via NutritionCalculationArithmetic (PER_100G / DIRECT_BATCH_GRAMS / EXCLUDED / MISSING / CONFLICTING / UNCOVERED)
       -> agrega total e 100 g/100 ml SOMENTE se completo e tecnicamente autorizado; caso contrário null + causes
       -> persiste snapshot imutável AddedSugarsEvaluation + items + causes
  -> AddedSugarsEvaluationResource (201 Created)

GET /api/added-sugars-evaluations/{addedSugarsEvaluation}
  -> AddedSugarsEvaluationService::retrieve
  -> AddedSugarsEvaluationResource

POST /api/nutrition-calculations/{nutritionCalculation}/serving-daily-value-calculations (existente — extensão opcional)
  -> StoreServingDailyValueCalculationRequest (+ added_sugars_evaluation_id opcional)
  -> ServingDailyValueCalculationService::create
       -> valida evaluation.nutrition_calculation_id === source.id (senão 422)
       -> se elegível + perfil autorizado: popula MAP-004 (porção + source_reference 100 g/100 ml) e libera VDR-004 se autorizado
       -> se bloqueada/incompleta: mantém MAP-004 e VDR-004 null com causas herdadas

POST /api/serving-daily-value-calculations/{servingDailyValueCalculation}/declared-nutrition-calculations (existente)
  -> DeclaredNutritionCalculationService / DeclaredValuesRegulatoryGateService
       -> consome MAP-004 respaldado por ADDED_SUGARS_FORMULATION (100 g/100 ml e porção) sob todos os gates da Feature 005
```

### Serviços e responsabilidades

- **`AddedSugarsFormulationService`** *(novo)*: Gerencia criação de versões imutáveis da formulação por receita, consulta com histórico e registro *append-only* de decisões de validação técnica.
- **`AddedSugarsRegulatoryGateService`** *(novo)*: Aplica as regras *deny-by-default* de autorização técnica (última decisão `AUTHORIZED`, conferência de escopo `recipe:{id}/added-sugars-formulation:v{n}`, restrição de `TEST_FIXTURE` a `testing`, rejeição de autoaprovação por `OPERATIONAL_FORM`, conferência de cobertura e conflitos).
- **`AddedSugarsEvaluationService`** *(novo)*: Orquestra em transação `REPEATABLE READ` a avaliação exata de uma `AddedSugarsFormulationVersion` contra uma `NutritionCalculation` da Feature 003 utilizando `NutritionCalculationArithmetic`, persistindo snapshot imutável e causas.
- **`ServingDailyValueCalculationService` / `StoreServingDailyValueCalculationRequest` / `ServingDailyValueCalculationResource`** *(existentes — extensão aditiva retrocompatível)*: Aceitam opcionalmente `added_sugars_evaluation_id`, validam compatibilidade com a `NutritionCalculation` e alimentam `MAP-004` (e `VDR-004` quando autorizado) preservando snapshot da fonte e causas herdadas.
- **`DeclaredNutritionCalculationService` / `DeclaredValuesRegulatoryGateService`** *(existentes — extensão aditiva retrocompatível)*: Reconhecem `MAP-004` com `value_source_type = 'ADDED_SUGARS_FORMULATION'` e snapshot elegível da Feature 004 para avaliar `REFERENCE_100` e `PORTION` sem depender de `FoodNutrient`.

## Project Structure

### Documentation (this feature)

```text
specs/007-added-sugars-formulation/
├── plan.md                                     # Este documento (/speckit-plan)
├── research.md                                 # Phase 0 output
├── data-model.md                               # Phase 1 output
├── quickstart.md                               # Phase 1 output
├── contracts/
│   └── added-sugars-formulation.md             # Phase 1 output
├── checklists/
│   └── requirements.md                         # Quality checklist (16/16 passing)
└── spec.md                                     # Feature specification
```

### Source Code (repository root)

```text
app/
├── Http/
│   ├── Controllers/
│   │   ├── AddedSugarsFormulationController.php              # Novo (fino)
│   │   └── AddedSugarsEvaluationController.php               # Novo (fino)
│   ├── Requests/
│   │   ├── StoreAddedSugarsFormulationRequest.php            # Novo
│   │   ├── StoreAddedSugarsFormulationValidationRequest.php  # Novo
│   │   ├── StoreAddedSugarsEvaluationRequest.php             # Novo
│   │   └── StoreServingDailyValueCalculationRequest.php      # Extensão aditiva opcional
│   └── Resources/
│       ├── AddedSugarsFormulationResource.php                # Novo
│       ├── AddedSugarsFormulationValidationResource.php      # Novo
│       ├── AddedSugarsEvaluationResource.php                 # Novo
│       └── ServingDailyValueCalculationResource.php          # Extensão aditiva retrocompatível
├── Models/
│   ├── AddedSugarsFormulation.php                            # Novo
│   ├── AddedSugarsFormulationVersion.php                     # Novo
│   ├── AddedSugarsFormulationIngredient.php                  # Novo
│   ├── AddedSugarsFormulationEvidence.php                    # Novo
│   ├── AddedSugarsFormulationValidation.php                  # Novo
│   ├── AddedSugarsEvaluation.php                             # Novo
│   ├── AddedSugarsEvaluationItem.php                         # Novo
│   ├── AddedSugarsEvaluationCause.php                        # Novo
│   ├── ServingDailyValueCalculation.php                      # Extensão aditiva (relação + fillable)
│   └── ServingDailyValueRegulatoryResult.php                 # Extensão aditiva (fillable)
└── Services/
    ├── AddedSugarsFormulationService.php                     # Novo
    ├── AddedSugarsRegulatoryGateService.php                  # Novo
    ├── AddedSugarsEvaluationService.php                      # Novo
    ├── ServingDailyValueCalculationService.php               # Extensão aditiva para MAP-004
    ├── DeclaredNutritionCalculationService.php               # Extensão aditiva para MAP-004 via formulação
    └── DeclaredValuesRegulatoryGateService.php               # Extensão aditiva para MAP-004 via formulação

database/migrations/
├── 2026_11_23_000001_create_added_sugars_formulations_table.php
├── 2026_11_23_000002_create_added_sugars_formulation_versions_table.php
├── 2026_11_23_000003_create_added_sugars_formulation_ingredients_table.php
├── 2026_11_23_000004_create_added_sugars_formulation_evidences_table.php
├── 2026_11_23_000005_create_added_sugars_formulation_validations_table.php
├── 2026_11_23_000006_create_added_sugars_evaluations_table.php
├── 2026_11_23_000007_create_added_sugars_evaluation_items_table.php
├── 2026_11_23_000008_create_added_sugars_evaluation_causes_table.php
├── 2026_11_23_000009_protect_added_sugars_history_from_mutation.php
└── 2026_11_23_000010_add_added_sugars_evaluation_to_serving_daily_values.php

routes/api.php                                                # Novas rotas da Feature 007

tests/Feature/
├── AddedSugarsFormulationPersistenceTest.php                 # Novo (schema, CHECKs, FKs, triggers PostgreSQL)
├── AddedSugarsFormulationTest.php                            # Novo (versões, evidências, validações, gates, aritmética)
└── AddedSugarsEvaluationIntegrationTest.php                  # Novo (integração 004/005, regressão, imutabilidade)
```

## Complexity Tracking

Nenhuma violação à Constitution identificada. Toda a arquitetura reutiliza os padrões, serviços (`NutritionCalculationArithmetic`), isolamento `REPEATABLE READ` e triggers de imutabilidade PostgreSQL já estabelecidos nas Features 003–006.
