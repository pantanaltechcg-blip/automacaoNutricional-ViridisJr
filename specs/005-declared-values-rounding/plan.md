# Implementation Plan: Declared Values, Rounding, and Non-Significant Quantities

**Branch**: `005-declared-values-rounding` | **Date**: 2026-11-12 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/005-declared-values-rounding/spec.md`

## Summary

Implementar uma execução histórica imutável (`DeclaredNutritionCalculation`) vinculada a uma única execução derivada da Feature 004 (`ServingDailyValueCalculation`) e à sua origem nutricional da Feature 003 (`NutritionCalculation`), responsável por determinar os valores nutricionais declarados nas bases `100 g/100 ml` e `porção`, aplicar regras versionadas de quantidades não significativas (NSQ) e arredondamento racional exato (`Brick\Math\BigRational` com desempate `HALF_UP`), recalcular na ordem normativa o valor energético regulatório final (em `100 g/100 ml` e `porção`) e o `%VD` regulatório final a partir dos valores declarados apropriados, e avaliar explicitamente a aptidão à rotulagem (`is_label_ready`).

Todas as regras normativas (perfil de declaração, política de recálculo, regras NSQ, regras/faixas de arredondamento e matriz de obrigatoriedade) operam em modo *deny-by-default* por meio de registros versionados e imutáveis no PostgreSQL com tupla de evidência técnica verificável (`DOCUMENTED` ou `TEST_FIXTURE` restrito ao ambiente `testing`), sem promover regras não validadas e sem alterar schemas ou dados das Features 001–004.

## Technical Context

**Language/Version**: PHP 8.4+ (ambiente local verificado em PHP 8.5.7; `composer.json` exige `^8.3`)

**Primary Dependencies**: Laravel Framework 13.34.0; `brick/math` 1.0.0 (`BigRational`, `BigInteger`, `RoundingMode`); Eloquent ORM; Laravel Form Requests e API Resources

**Storage**: PostgreSQL (razões exatas persistidas em pares `NUMERIC` sem precisão/escala fixa; strings decimais exatas para valores declarados; snapshots relacionais imutáveis protegidos por triggers `BEFORE UPDATE OR DELETE`)

**Testing**: PHPUnit 12.5.38 (`php artisan test --compact`); PostgreSQL real; Laravel Pint 1.32.1

**Target Platform**: API web Laravel JSON backend executada em ambiente local (macOS / Laravel Sail Docker) e servidor Linux compatível com PHP 8.4+/PostgreSQL

**Project Type**: Serviço web monolítico Laravel, API JSON backend

**Performance Goals**: Carga agrupada da execução 004, da origem 003 e do pacote de declaração aplicável sem consultas N+1; transação atômica `REPEATABLE READ` por solicitação `POST`; serialização determinística ordenada por `map_code`, `ene_code`, `vdr_code` e `(scope, scope_key, code)`

**Constraints**: Aritmética racional exata (`BigRational`) sem `float`, `double`, `decimal:N` ou truncamento; preservação simultânea da string decimal formatada na escala da faixa e da razão exata arredondada (`FR-003`); preservação de distinção entre zero numérico exato (`0/1`), zero por NSQ, `NOT_APPLICABLE`, `NO_NORMATIVE_VDR` e `null` com causa (`FR-009`); proibição de converter `TRACE`, `NA`, `ABSENT`, `REEVALUATION`, ausência de relação ou subtotal parcial (`is_complete = false`) em zero ou valor declarado (`FR-007`, `FR-008`); energia final sem subtotal parcial (`FR-008`); nenhuma alteração funcional ou de schema nas Features 001–004 (`FR-002`); escopo estrito sem renderização HTML/CSS, rotulagem frontal ou exportação (`FR-014`)

**Scale/Scope**: Todos os componentes `MAP`, componentes energéticos `ENE` e linhas `VDR` presentes no snapshot da Feature 004, abrangendo integralmente as bases `REFERENCE_100` (`100 g/100 ml`) e `PORTION` (`porção` + `%VD`), gerando uma nova execução histórica imutável por solicitação `POST`

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### Gate Inicial (pré-Phase 0) — PASS

| Princípio / Gate (Constitution 2.0.0) | Evidência de Conformidade |
|---|---|
| I. Princípio de Não Invenção | Nenhuma fórmula, threshold NSQ ou faixa de arredondamento é hardcoded ou inventada em PHP. Regras operam em tabelas versionadas *deny-by-default*; pendências e bloqueios permanecem `null` com causas estruturadas. |
| II. Stack Definida | PHP, Laravel 13.34.0, PostgreSQL, Eloquent ORM, Form Requests, Controllers, Services, API Resources e PHPUnit 12.5.38, reutilizando `brick/math` 1.0.0 já instalado sem adicionar novas dependências. |
| III. Arquitetura e Separação de Responsabilidades | Fluxo estrito `Routes -> Controller -> FormRequest -> Services -> Models -> PostgreSQL -> Resource`. Regras de gate e cálculo residem exclusivamente em `DeclaredValuesRegulatoryGateService` e `DeclaredNutritionCalculationService`. |
| IV. Motor de Cálculo | `NutritionCalculationArithmetic` recebe apenas helpers genéricos sobre `BigRational`/`BigInteger` (`roundToStepHalfUp`, comparação e formatação exata `RoundingMode::Unnecessary`), mantendo separação entre valores exatos 003/004 e valores declarados/recalculados 005. |
| V. Dados Nutricionais e Fontes (TACO) | Consome exclusivamente snapshots imutáveis das Features 003 e 004; nunca altera TACO, `foods`, `nutrients`, `recipes`, `nutrition_calculations` ou `serving_daily_value_calculations`, nem converte `TRACE`/`NA`/`ABSENT`/`REEVALUATION` em zero. |
| VI. Regras Regulatórias | Modo *deny-by-default* com tupla completa de evidência técnica (`DOCUMENTED` ou `TEST_FIXTURE` restrito a `testing`), validação de vinculação exata ao perfil/versão normativa/escopo da execução 004 e ordem normativa explícita (`CQ-001`–`CQ-003`). |
| Banco de Dados e Testes | Migrations Laravel para PostgreSQL com `NUMERIC` sem escala fixa, `CHECK` constraints, chaves únicas, triggers de imutabilidade e suíte PHPUnit unitária e de feature cobrindo caminhos autorizados (`TEST_FIXTURE`) e bloqueios seguros. |
| Autoridade Administrativa e Integridade | Constitution 2.0.0 e `spec.md` (clarificada e validada em 16/16 no checklist) governam a passagem para Plan; a infraestrutura não promove regras regulatórias sem validação técnica formal. |

### Rechecagem após Phase 1 — PASS

`research.md`, `data-model.md`, `contracts/declared-values-rounding.md` e `quickstart.md` preservam integralmente os limites definidos:
- As 12 novas tabelas (6 de referências versionadas + 6 de snapshot histórico) isolam completamente a Feature 005 sem alterar nenhuma tabela das Features 001–004;
- `DeclaredNutritionCalculation` preserva lado a lado os valores exatos da 003 (`100 g/100 ml`) e da 004 (`portion`, `source_portion_derived_exact`, `source_derived_exact`), as regras/evidências aplicadas, os valores declarados finais (`display` + `ratio`), a energia final em ambas as bases, os `%VD` finais e `is_label_ready`;
- Nenhuma regra de UI/tabela HTML, rotulagem frontal ou exportação foi introduzida.

## Project Structure

### Documentation (this feature)

```text
specs/005-declared-values-rounding/
├── plan.md                                  # Este arquivo (/speckit-plan)
├── research.md                              # Phase 0 output (/speckit-plan)
├── data-model.md                            # Phase 1 output (/speckit-plan)
├── quickstart.md                            # Phase 1 output (/speckit-plan)
├── contracts/
│   └── declared-values-rounding.md          # Phase 1 output (/speckit-plan)
├── checklists/
│   └── requirements.md                      # Checklist de qualidade da spec (16/16)
└── spec.md                                  # Feature specification (/speckit-specify + /speckit-clarify)
```

### Source Code (repository root)

```text
app/
├── Http/
│   ├── Controllers/
│   │   └── DeclaredNutritionCalculationController.php       # store + show (orquestração fina)
│   ├── Requests/
│   │   └── StoreDeclaredNutritionCalculationRequest.php     # validação de entrada HTTP
│   └── Resources/
│       └── DeclaredNutritionCalculationResource.php         # serialização determinística do snapshot
├── Models/
│   ├── RegulatoryDeclarationProfile.php                     # pacote versionado de declaração/arredondamento
│   ├── RegulatoryRecalculationPolicy.php                    # política de ordem normativa
│   ├── RegulatoryNonSignificantRule.php                     # regras de quantidades não significativas
│   ├── RegulatoryRoundingRule.php                           # regras de arredondamento
│   ├── RegulatoryRoundingRuleRange.php                      # faixas e incrementos por regra
│   ├── RegulatoryDeclarationRequirement.php                 # matriz de obrigatoriedade para is_label_ready
│   ├── DeclaredNutritionCalculation.php                     # raiz histórica imutável
│   ├── DeclaredNutritionComponent.php                       # snapshot por MAP (100 g/100 ml e porção)
│   ├── DeclaredNutritionComponentState.php                  # estados qualitativos copiados
│   ├── DeclaredNutritionEnergyComponent.php                 # snapshot de recálculo ENE por base
│   ├── DeclaredNutritionPercentage.php                      # snapshot de %VD derivado vs. final
│   └── DeclaredNutritionCause.php                           # ledger estruturado de causas
└── Services/
    ├── NutritionCalculationArithmetic.php                   # extensão genérica BigRational (roundToStepHalfUp, formatação exata)
    ├── DeclaredValuesRegulatoryGateService.php              # validação de perfil/escopo, evidência, NSQ, faixas e is_label_ready
    └── DeclaredNutritionCalculationService.php              # execução atômica REPEATABLE READ e snapshot imutável

database/
└── migrations/
    ├── 2026_11_12_000001_create_regulatory_declaration_profiles_table.php
    ├── 2026_11_12_000002_create_regulatory_recalculation_policies_table.php
    ├── 2026_11_12_000003_create_regulatory_non_significant_rules_table.php
    ├── 2026_11_12_000004_create_regulatory_rounding_rules_table.php
    ├── 2026_11_12_000005_create_regulatory_rounding_rule_ranges_table.php
    ├── 2026_11_12_000006_create_regulatory_declaration_requirements_table.php
    ├── 2026_11_12_000007_create_declared_nutrition_calculations_table.php
    ├── 2026_11_12_000008_create_declared_nutrition_components_table.php
    ├── 2026_11_12_000009_create_declared_nutrition_component_states_table.php
    ├── 2026_11_12_000010_create_declared_nutrition_energy_components_table.php
    ├── 2026_11_12_000011_create_declared_nutrition_percentages_table.php
    ├── 2026_11_12_000012_create_declared_nutrition_causes_table.php
    └── 2026_11_12_000013_protect_declared_nutrition_data_from_mutation.php

routes/
└── api.php                                                  # rotas POST e GET da Feature 005

tests/
├── Unit/
│   └── DeclaredValuesArithmeticTest.php                     # testes unitários de arredondamento HALF_UP, comparações e formatação exata
└── Feature/
    ├── DeclaredNutritionRegulatoryGateTest.php              # testes de gates, perfil/versão/escopo, TEST_FIXTURE e faixas inválidas
    ├── DeclaredNutritionCalculationTest.php                 # testes end-to-end: NSQ por base, ordem normativa, parciais e is_label_ready
    └── DeclaredNutritionPersistenceTest.php                 # testes de imutabilidade PostgreSQL, histórico e integridade 001–004
```

**Structure Decision**: Adota integralmente o monólito Laravel existente (`app/Http/{Controllers,Requests,Resources}`, `app/Models`, `app/Services`, `database/migrations`, `routes/api.php`, `tests/{Unit,Feature}`), mantendo simetria direta com as Features 003 (`NutritionCalculation*`) e 004 (`ServingDailyValue*`) e preservando intactos todos os artefatos das Features 001–004.

## Complexity Tracking

Nenhuma violação à Constitution 2.0.0 identificada.
