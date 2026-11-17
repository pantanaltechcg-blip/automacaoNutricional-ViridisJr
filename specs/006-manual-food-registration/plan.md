# Implementation Plan: Governed Manual Food Registration

**Branch**: `006-manual-food-registration` | **Date**: 2026-11-17 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/006-manual-food-registration/spec.md`

## Summary

Implementar o cadastro manual governado de alimentos simples ausentes da base oficial TACO (`006-manual-food-registration`), garantindo rastreabilidade de fonte e evidência, bloqueio estrito de duplicidades por identidade exata normalizada, disponibilidade imediata após cadastro completo, versionamento imutável a cada atualização, inativação lógica para novos usos e preservação integral da base TACO e de todos os cálculos históricos das Features 003–005.

A arquitetura preserva integralmente os contratos e schemas de `foods` e `food_nutrients` para busca (`GET /api/foods`) e consumo por receitas/cálculos (`RecipeService`, `NutritionCalculationService`), adicionando as entidades governadas `ManualFoodRegistration`, `ManualFoodVersion` e `ManualFoodVersionNutrient` com proteção contra mutação (`UPDATE`/`DELETE`) via triggers PostgreSQL em transações `REPEATABLE READ`.

## Technical Context

**Language/Version**: PHP 8.4 (Docker/Sail) / PHP 8.5.6 local CLI

**Primary Dependencies**: Laravel Framework 13.34.0; Eloquent ORM; Laravel Form Requests e API Resources; sem novas dependências externas

**Storage**: PostgreSQL 17 (com extensão `unaccent` já habilitada); projeção vigente em `foods` e `food_nutrients`; versionamento histórico imutável em `manual_food_versions` e `manual_food_version_nutrients` protegidos por triggers contra `UPDATE`/`DELETE`

**Testing**: PHPUnit 12.5+ via `php artisan test --compact`; PostgreSQL real; Laravel Pint; `git diff --check`

**Target Platform**: API web Laravel executada em ambiente Windows/Docker compatível com PostgreSQL 17

**Project Type**: Serviço web monolítico Laravel, API JSON backend

**Performance Goals**: Cadastro, consulta e atualização versionada executados em transação atômica única (`REPEATABLE READ`), com carregamento antecipado (*eager loading*) de versões e nutrientes sem consultas N+1 e ordenação determinística

**Constraints**:
- Proibido alterar, sobrescrever ou inativar qualquer registro da base oficial TACO (`source = 'TACO'`);
- Proibido criar novos registros em `nutrients` ou inventar valores sintéticos/aproximados;
- Proibido converter valores ausentes ou estados qualitativos (`TRACE`, `NA`, `ABSENT`, `REEVALUATION`) para zero;
- Proibido utilizar `float`/`double` para entrada de `amount_value` (validação estrita como string decimal compatível com `NUMERIC(7,2)`);
- Escopo restrito a alimentos simples com base de referência `100 g` (compatível com `recipe_ingredients.unit = 'g'`), sem conversão automática de densidade e sem ingredientes compostos/industrializados;
- Cálculos históricos das Features 003, 004 e 005 permanecem inalterados após atualizações ou inativações futuras do alimento manual.

**Scale/Scope**: Catálogo oficial existente de 66 nutrientes da Feature 001; qualquer quantidade de alimentos manuais simples além dos 597 alimentos TACO atuais; série histórica ilimitada de versões imutáveis por alimento manual.

## Constitution Check

*GATE inicial e rechecagem pós-design (Phase 1): PASS.*

| Princípio / Gate | Evidência do desenho |
|---|---|
| **I. Princípio de Não Invenção** | Nutrientes não informados permanecem ausentes (nunca convertidos para zero); estados qualitativos preservados; nenhum alimento sintético, conversão de densidade ou nutriente inexistente é criado. |
| **II. Stack Definida** | PHP 8.4+, Laravel 13, Eloquent, Form Requests, Resources, Services, PostgreSQL 17 e PHPUnit; nenhuma biblioteca adicional introduzida. |
| **III. Arquitetura e Separação de Responsabilidades** | Fluxo estrito `Routes -> Controllers -> Services -> Models -> PostgreSQL`; `ManualFoodRegistrationController` fino delega ao `ManualFoodRegistrationService`; regras de duplicidade, proteção TACO e versionamento permanecem nos Services. |
| **IV. Motor de Cálculo** | `amount_value` recebido como string decimal exata compatível com `NUMERIC(7,2)`, sem `float` ou arredondamento silencioso; cálculos subsequentes continuam usando aritmética racional exata da Feature 003. |
| **V. Dados Nutricionais e Fontes (TACO)** | Base TACO permanece imutável no fluxo manual (bloqueio na camada de validação, Service e trigger PostgreSQL); origem distinta da TACO, código na fonte e evidências são obrigatórios e versionados. |
| **VI. Regras Regulatórias** | Alimentos manuais não contornam gates regulatórios das Features 004/005 e preservam estados e incompletudes exatamente como na TACO. |
| **Banco de Dados e Testes** | Migrations PostgreSQL com `CHECK`, `UNIQUE`, `FKs`, índice único normalizado e triggers contra `UPDATE`/`DELETE` em versões; cobertura planejada para unitários, integração PostgreSQL, contratos HTTP, imutabilidade histórica e regressão das features anteriores. |
| **Spec-Driven Development** | Desenho derivado estritamente de `spec.md` (`FR-001` a `FR-010`, `SC-001` a `SC-004`, `CQ-001` a `CQ-003`), sem antecipar código ou `tasks.md`. |

### Rechecagem após Phase 1

PASS. Os artefatos `research.md`, `data-model.md`, `contracts/manual-food-registration.md` e `quickstart.md` mantêm separação clara entre registros TACO e governança manual, garantem imutabilidade das versões e dos cálculos históricos, preservam integralmente os contratos anteriores e não introduzem funcionalidades fora do escopo aprovado.

## Architecture

### Fluxo principal

```text
POST /api/manual-foods
  -> StoreManualFoodRequest (formato, string decimal exata, proíbe source=TACO)
  -> ManualFoodRegistrationService::create (transação REPEATABLE READ)
       -> valida duplicidade exata normalizada contra foods (source+source_code e source+name)
       -> valida nutrient_ids existentes em nutrients
       -> cria Food + FoodNutrient vigentes
       -> cria ManualFoodRegistration (is_active=true, current_version_number=1)
       -> cria ManualFoodVersion v1 + ManualFoodVersionNutrient (imutáveis)
  -> ManualFoodRegistrationResource (201 Created)

GET /api/manual-foods/{food}
  -> ManualFoodRegistrationService::retrieve (somente alimentos com manualRegistration)
  -> ManualFoodRegistrationResource (versão vigente + histórico completo de versões)

PUT /api/manual-foods/{food}
  -> UpdateManualFoodRequest (mesmas regras + change_reason obrigatório)
  -> ManualFoodRegistrationService::update (transação REPEATABLE READ)
       -> bloqueia alimentos sem manualRegistration / TACO
       -> valida duplicidade exata excluindo o próprio food_id
       -> cria ManualFoodVersion v(N+1) + ManualFoodVersionNutrient (imutáveis)
       -> atualiza current_version_number e projeção vigente em foods / food_nutrients
  -> ManualFoodRegistrationResource

PATCH /api/manual-foods/{food}/status
  -> UpdateManualFoodStatusRequest (is_active booleano)
  -> ManualFoodRegistrationService::updateStatus (transação)
       -> bloqueia alimentos sem manualRegistration / TACO
       -> atualiza manual_food_registrations.is_active
  -> ManualFoodRegistrationResource

GET /api/foods?q=... (existente)
  -> NutritionBaseService::search (exclui apenas alimentos manuais com is_active=false)
```

### Serviços e responsabilidades

- **`ManualFoodRegistrationService`** *(novo)*: Orquestra criação, consulta, atualização versionada e ativação/inativação lógica em transação `REPEATABLE READ`, aplica bloqueios contra mutação da TACO e duplicidade exata normalizada (com `unaccent` e normalização de caixa/espaços) e mantém sincronizados `foods`/`food_nutrients` e o histórico imutável `manual_food_versions`.
- **`NutritionBaseService`** *(existente, ajuste pontual retrocompatível)*: Atualiza `search()` para filtrar alimentos manuais inativos (`is_active = false`), mantendo TACO e alimentos manuais ativos exatamente no mesmo formato contratado na Feature 001.
- **`StoreRecipeRequest` / `UpdateRecipeRequest`** *(existentes, validação aditiva)*: Rejeitam associação de alimento manual inativo em novas receitas ou como novo ingrediente na edição, preservando a integridade de receitas que já contenham o ingrediente antes da inativação.
- **Form Requests (`StoreManualFoodRequest`, `UpdateManualFoodRequest`, `UpdateManualFoodStatusRequest`)**: Validam formato, presença obrigatória, proibições de `source = 'TACO'`, strings decimais não negativas sem `float` e compatibilidade estado ↔ `amount_value`.
- **Resource (`ManualFoodRegistrationResource`)**: Serializa de forma determinística o cabeçalho de governança, a versão vigente (`current_version`) e a lista ordenada de versões imutáveis (`versions`).

## Project Structure

### Documentation (this feature)

```text
specs/006-manual-food-registration/
├── plan.md                                  # Este documento (/speckit-plan)
├── research.md                              # Phase 0 output
├── data-model.md                            # Phase 1 output
├── quickstart.md                            # Phase 1 output
├── contracts/
│   └── manual-food-registration.md          # Phase 1 output
├── checklists/
│   └── requirements.md                      # Quality checklist (16/16 passing)
└── spec.md                                  # Feature specification
```

### Source Code (repository root)

```text
app/
├── Http/
│   ├── Controllers/
│   │   └── ManualFoodRegistrationController.php
│   ├── Requests/
│   │   ├── StoreManualFoodRequest.php
│   │   ├── UpdateManualFoodRequest.php
│   │   ├── UpdateManualFoodStatusRequest.php
│   │   ├── StoreRecipeRequest.php           # existente; regra para manual inativo
│   │   └── UpdateRecipeRequest.php          # existente; regra para novo ingrediente inativo
│   └── Resources/
│       └── ManualFoodRegistrationResource.php
├── Models/
│   ├── Food.php                             # existente; relacionamento aditivo manualRegistration()
│   ├── ManualFoodRegistration.php
│   ├── ManualFoodVersion.php
│   └── ManualFoodVersionNutrient.php
└── Services/
    ├── ManualFoodRegistrationService.php
    └── NutritionBaseService.php             # existente; filtro de manual foods inativos na busca

database/
└── migrations/
    ├── *_create_manual_food_registrations_table.php
    ├── *_create_manual_food_versions_table.php
    ├── *_create_manual_food_version_nutrients_table.php
    ├── *_add_normalized_identity_indexes_to_foods_table.php
    └── *_protect_manual_food_versions_from_mutation.php

routes/
└── api.php                                  # rotas /api/manual-foods

tests/
└── Feature/
    ├── ManualFoodRegistrationTest.php       # contratos HTTP, criação, consulta, busca, receitas
    ├── ManualFoodPersistenceTest.php        # constraints PostgreSQL, duplicidade, proteção TACO, triggers
    └── ManualFoodVersioningHistoryTest.php  # atualização versionada, inativação e imutabilidade histórica
```

**Structure Decision**: Manter integralmente o monólito Laravel existente e a estrutura `Routes -> Controllers -> Services -> Models -> PostgreSQL` das Features 001–005, adicionando apenas os componentes estritamente necessários à governança e versionamento do cadastro manual sem introduzir pacotes, jobs ou filas externas.

## Testing Strategy

- **Contratos HTTP e validação (`ManualFoodRegistrationTest`)**: criação completa (`201`), busca imediata em `GET /api/foods?q=...` (inclusive com acentuação/caixa), consulta `GET /api/manual-foods/{food}`, rejeição de `source = 'TACO'`, rejeição de `amount_value` como `float`/negativo/3 casas decimais/incompatível com estado qualitativo, rejeição de payload sem nutrientes ou com `nutrient_id` inexistente/duplicado, e uso direto em receitas (`POST /api/recipes`) e cálculos (`POST /api/recipes/{recipe}/nutrition-calculations`).
- **Persistência PostgreSQL e integridade (`ManualFoodPersistenceTest`)**: colunas, `CHECK` constraints, `UNIQUE` constraints, índices de identidade normalizada, triggers bloqueando `UPDATE`/`DELETE` em `manual_food_versions` e `manual_food_version_nutrients`, trigger bloqueando associação manual a `Food` TACO e rollback atômico em caso de erro.
- **Versionamento e imutabilidade histórica (`ManualFoodVersioningHistoryTest`)**: atualização via `PUT /api/manual-foods/{food}` gerando versão `2` com `change_reason` obrigatório, preservação da versão `1` e recálculo futuro usando a versão `2` enquanto o cálculo histórico gerado na versão `1` permanece 100% idêntico; inativação via `PATCH /api/manual-foods/{food}/status` ocultando da busca e bloqueando novas receitas sem alterar receitas ou cálculos passados.
- **Regressão**: execução completa da suíte existente das Features 001–004/005 (`php artisan test --compact`), formatação com Laravel Pint (`vendor/bin/pint --format agent`) e `git diff --check`.

## Risks and Dependencies

- **Unicidade normalizada em `foods` vs. dados TACO existentes**: antes de aplicar índice único funcional de `(source, name)` sobre `foods`, será verificado durante a implementação que os 597 alimentos TACO originais não possuem colisão entre si sob `unaccent(LOWER(BTRIM(name)))`, garantindo que a migration nunca falhe ou altere dados TACO em bases já populadas.
- **Compatibilidade dimensional (`100 g` vs. `100 ml`)**: como `recipe_ingredients.unit` aceita exclusivamente `'g'` na Feature 002, alimentos cuja fonte nutricional esteja em `100 ml` permanecem bloqueados nesta feature até suporte explícito de ingredientes em `ml` e/ou conversão validada, evitando erro dimensional silencioso.
- **Segurança operacional dos endpoints**: assim como os demais endpoints da API em `routes/api.php`, `/api/manual-foods` não implementa autenticação própria nesta feature; permanece obrigatório proteger o acesso em ambiente de produção.

## Complexity Tracking

Nenhuma violação da Constitution 2.0.0 a justificar. As três novas tabelas (`manual_food_registrations`, `manual_food_versions`, `manual_food_version_nutrients`) constituem o conjunto mínimo necessário para separar a governança/versionamento imutável da projeção operacional `foods`/`food_nutrients` sem alterar os registros da TACO nem os contratos das Features 001–005.
