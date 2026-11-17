# Quickstart Validation: 005-declared-values-rounding

## Objetivo

Validar a apuração de valores nutricionais declarados em `100 g/100 ml` e na porção, a avaliação de quantidades não significativas (NSQ), o arredondamento racional exato `HALF_UP`, o recálculo regulatório final de energia e `%VD` na ordem normativa, a avaliação de `is_label_ready` e a imutabilidade histórica, sem alterar dados das Features 001–004.

---

## Pré-requisitos

- PHP 8.4, dependências Composer instaladas (`brick/math` 1.0.0 confirmado);
- PostgreSQL 17 configurado sem credenciais versionadas;
- Migrations das Features 001–005 aplicadas;
- Uma `ServingDailyValueCalculation` (Feature 004) vinculada a uma `NutritionCalculation` (Feature 003);
- Fixtures controladas de `RegulatoryDeclarationProfile` e regras filhas para cenários autorizados em testes automatizados (`TEST_FIXTURE` restrito ao ambiente `testing`).

```bash
composer show --direct brick/math
php artisan migrate
php artisan migrate:status
```

Não criar Seeder ou migration que promova regras da RDC nº 429/2020 ou IN nº 75/2020 a `AUTHORIZED` sem validação técnica formal.

---

## Execução da suíte automatizada

```bash
php artisan test --compact tests/Unit/DeclaredValuesArithmeticTest.php
php artisan test --compact tests/Feature/DeclaredNutritionRegulatoryGateTest.php
php artisan test --compact tests/Feature/DeclaredNutritionCalculationTest.php
php artisan test --compact tests/Feature/DeclaredNutritionPersistenceTest.php
```

---

## Cenários de verificação end-to-end

### 1. Estado *deny-by-default* (regras bloqueadas)

- **Entrada**: `POST /api/serving-daily-value-calculations/{id}/declared-nutrition-calculations` apontando para `RegulatoryDeclarationProfile` compatível com o perfil da execução 004, porém com `technical_status = BLOCKED` (ou regras NSQ/arredondamento/política bloqueadas).
- **Esperado**:
  - Execução imutável persistida (`201`);
  - Valores exatos da Feature 003 (`100 g/100 ml`) e da Feature 004 (`portion`, `source_portion_derived_exact`, `source_derived_exact`) preservados;
  - Valores declarados afetados permanecem `null` com causas estruturadas no ledger;
  - `is_label_ready = false` e `label_readiness_causes` preenchido.

### 2. Divergência de perfil, versão normativa ou escopo (`FR-001`)

- **Entrada**: `POST` com `regulatory_declaration_profile_id` cujo `regulatory_profile_code`, `regulatory_profile_version`, `normative_version` ou `scope_recipe_id`/`scope_nutrition_calculation_id` diverge da `ServingDailyValueCalculation` de origem.
- **Esperado**: Rejeição com `422` (`ValidationException`) e zero registros persistidos em `declared_nutrition_calculations`.

### 3. Arredondamento racional exato, desempate `HALF_UP` e NSQ independente por base

- **Entrada**: Fixture controlada em ambiente `testing` com componente positivo acima do limite em `100 g/100 ml` e abaixo/igual ao limite de NSQ na porção (e caso de empate exato `x.x5` ou `x.5`).
- **Esperado**:
  - Base `REFERENCE_100` arredondada pelo step e escala da faixa (`display` e `ratio` já arredondado);
  - Base `PORTION` declarada conforme regra NSQ (`display: "0"`, `ratio: {"numerator": "0", "denominator": "1"}`, `nsq_applied: true`, `is_exact_zero: false`);
  - Zero numérico exato `0/1` distinguido de zero por NSQ (`is_exact_zero: true`, `nsq_applied: false`).

### 4. Ordem normativa de recálculo final (`CQ-002`, `FR-005`)

- **Entrada**: Fixture controlada em que a soma energética calculada sobre os valores **declarados** (incluindo componente zerado por NSQ ou arredondado) difere do arredondamento direto da energia exata derivada da Feature 004; idem para `%VD`.
- **Esperado**:
  - `energy.source_portion_derived_exact` preserva o derivado da 004;
  - `energy.reference_100` e `energy.portion` refletem o recálculo sobre os valores **declarados** seguido do arredondamento da energia;
  - `daily_values[*].source_derived_exact` preserva o `%VD` derivado da 004, enquanto `declared_percentage` reflete `(input_declared * 100 / vdr)` arredondado.

### 5. Isolamento de estados qualitativos, subtotais parciais e `NO_NORMATIVE_VDR` (`FR-007`, `FR-008`, `FR-009`)

- **Entrada**: Origem com componente em `TRACE`/`NA`/`ABSENT`/`REEVALUATION`, outro com `has_missing_relation = true` ou `is_complete = false`, e linha `VDR` com `NO_NORMATIVE_VDR`.
- **Esperado**:
  - Nenhum componente afetado recebe NSQ ou valor declarado (permanece `null` com estados qualitativos e causas preservados);
  - Se o componente afetado integrar a matriz energética aplicável, a energia regulatória final da base afetada permanece `null` (`UNAVAILABLE`, sem subtotal parcial);
  - Linha `NO_NORMATIVE_VDR` preserva `availability = 'NO_NORMATIVE_VDR'` distinto de erro;
  - Componentes independentes completos e autorizados mantêm seus valores declarados; `is_label_ready = false`.

### 6. Aptidão à rotulagem e imutabilidade histórica (`FR-011`–`FR-013`)

- **Entrada**: Cenário com todos os requisitos obrigatórios completos e autorizados -> `is_label_ready = true`. Em seguida, `GET /api/declared-nutrition-calculations/{id}` e tentativa de `UPDATE`/`DELETE` via DB.
- **Esperado**:
  - Nova chamada `POST` gera novo `id` sem alterar a execução anterior;
  - Triggers PostgreSQL rejeitam qualquer `UPDATE` ou `DELETE` nas tabelas da Feature 005;
  - Tabelas e registros das Features 001–004 permanecem intactos.
