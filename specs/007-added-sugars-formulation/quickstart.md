# Quickstart Validation Guide: Added Sugars Formulation (007-added-sugars-formulation)

**Date**: 2026-11-23  
**Spec**: [spec.md](./spec.md) | **Data Model**: [data-model.md](./data-model.md) | **Contracts**: [added-sugars-formulation.md](./contracts/added-sugars-formulation.md)

## Prerequisites

- Docker Desktop / PostgreSQL 17 ativo (via Sail `./vendor/bin/sail up -d` ou PostgreSQL local configurado no `.env`)
- PHP 8.4+ e Composer instalados
- Migrations executadas (`php artisan migrate`)
- Base TACO importada (`php artisan nutrition:import-taco`)

## Validation Scenarios

### Scenario 1 — Registro versionado, evidências e bloqueio *deny-by-default* (US1, US2)
1. Criar uma receita (`POST /api/recipes`) com 1 ingrediente simples (fruta excluída normativamente) e 1 ingrediente composto/industrializado (com ficha técnica de fabricante declarando açúcares adicionados por `100 g`) e executar o cálculo nutricional (`POST /api/recipes/{recipe}/nutrition-calculations`).
2. Registrar a formulação `v1` (`POST /api/recipes/{recipe}/added-sugars-formulations`) com classificações `EXCLUDED_NORMATIVE` e `INCLUDED` + evidências.
3. Executar a avaliação antes da validação técnica (`POST /api/nutrition-calculations/{calc}/added-sugars-evaluations`).
4. **Resultado esperado**: `201 Created`, contribuições individuais preservadas, `validation.status = "PENDING"`, `total = null`, `reference = null`, `is_regulatory_eligible = false`, causa `FORMULATION_VALIDATION_PENDING` registrada.

### Scenario 2 — Validação técnica *append-only*, cálculo exato e zero confirmado (US1, US2, US3)
1. Registrar validação técnica `AUTHORIZED` sobre `v1` (`POST /api/added-sugars-formulation-versions/{v1}/validations`) com escopo `recipe:{id}/added-sugars-formulation:v1`.
2. Executar nova avaliação (`POST /api/nutrition-calculations/{calc}/added-sugars-evaluations`).
3. **Resultado esperado**:
   - A avaliação anterior do Scenario 1 permanece inalterada (`GET /api/added-sugars-evaluations/{eval1}`).
   - A nova avaliação retorna `total` e `reference` em frações racionais exatas, `is_complete = true`, `is_regulatory_eligible = true`.
   - Em receita cujos ingredientes sejam todos `EXCLUDED_NORMATIVE` ou `0` validados, retorna `total = 0/1`, `reference = 0/1`, `is_confirmed_zero = true`.

### Scenario 3 — Dados ausentes, somente açúcares totais e conflito de evidências (US1, US2)
1. Registrar formulação contendo ingrediente `MISSING` (com evidência `only_total_sugars_reported = true`) ou `CONFLICTING`.
2. Mesmo após tentar avaliar, **resultado esperado**: nenhum zero é inventado; `total = null`, `reference = null`, `is_regulatory_eligible = false`, `is_confirmed_zero = false`, com causas explícitas (`ONLY_TOTAL_SUGARS_REPORTED`, `INGREDIENT_ADDED_SUGARS_MISSING` ou `CONFLICTING_EVIDENCES`).

### Scenario 4 — Versionamento imutável e preservação histórica (US3)
1. Criar nova versão `v2` da formulação (`POST /api/recipes/{recipe}/added-sugars-formulations` com `change_reason`), alterar o teor de açúcares adicionados ou revogar a validação de `v1` (`decision_status = "REVOKED"`).
2. **Resultado esperado**: `GET /api/added-sugars-evaluations/{eval2}` continua reproduzindo exatamente o snapshot original; tentativas de `UPDATE`/`DELETE` direto nas tabelas de versão, evidência, validação ou avaliação falham no PostgreSQL por trigger de imutabilidade.

### Scenario 5 — Integração opcional com Features 004 (`MAP-004`/`VDR-004`) e 005 (US3)
1. Tentar criar `ServingDailyValueCalculation` passando `added_sugars_evaluation_id` de outra `NutritionCalculation` → rejeitado com `422`.
2. Criar `ServingDailyValueCalculation` com `added_sugars_evaluation_id` elegível da mesma `NutritionCalculation`:
   - `MAP-004` recebe valor exato por porção (`total × portion ÷ yield`) e `source_reference` (`100 g/100 ml`);
   - `VDR-004` calcula `%VD` somente se `VDR-004` estiver tecnicamente autorizado;
   - Nenhum fator energético adicional é criado;
   - Feature 005 (`POST /api/serving-daily-value-calculations/{sv}/declared-nutrition-calculations`) arredonda e avalia NSQ de `MAP-004` nas bases `100 g/100 ml` e porção.
3. Repetir passando avaliação bloqueada/pendente → `MAP-004` e `VDR-004` permanecem `null` (`UNAVAILABLE`) com causas herdadas.

## Verification Commands

```bash
php artisan test --compact tests/Feature/AddedSugarsFormulationPersistenceTest.php
php artisan test --compact tests/Feature/AddedSugarsFormulationTest.php
php artisan test --compact tests/Feature/AddedSugarsEvaluationIntegrationTest.php
php artisan test --compact
vendor/bin/pint --dirty --format agent
git diff --check
```
