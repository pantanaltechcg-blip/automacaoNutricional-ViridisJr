# Data Model: Governed Manual Food Registration (`006-manual-food-registration`)

## Visão geral

```text
Food (existente, Feature 001)
  ├── N FoodNutrient (existente, Feature 001 — projeção vigente para busca e novos cálculos)
  └── 0..1 ManualFoodRegistration (novo — exclusivamente para alimentos manuais)
             └── N ManualFoodVersion (novo, imutável — version_number 1..N)
                   └── N ManualFoodVersionNutrient (novo, imutável)
```

Esta feature não altera, remove ou migra dados existentes de `foods`, `nutrients`, `food_nutrients`, `recipes`, `recipe_ingredients` ou dos snapshots imutáveis das Features 003, 004 e 005. Alimentos provenientes da base oficial TACO não possuem registro em `manual_food_registrations` e permanecem protegidos contra criação, edição ou inativação via fluxo manual.

---

## Entidades novas

### 1. `manual_food_registrations`

Representa o cabeçalho de governança e disponibilidade de um alimento manual vinculado a um registro único em `foods`.

| Campo | Tipo PostgreSQL | Obrigatório | Regra |
|---|---|---|---|
| `id` | `BIGINT` | sim | Chave primária gerada pelo Laravel |
| `food_id` | `BIGINT` | sim | `UNIQUE`, FK para `foods.id` (`ON DELETE RESTRICT`) |
| `is_active` | `BOOLEAN` | sim | Padrão `true`; controla disponibilidade na busca e em novas receitas |
| `current_version_number` | `INTEGER` | sim | `>= 1`; reflete o número da versão vigente em `manual_food_versions` |
| `reference_unit` | `VARCHAR(2)` | sim | Fixo em `'g'` (`100 g` de base comestível nesta fase) |
| `created_at` | `TIMESTAMP` | sim | Timestamp padrão Laravel |
| `updated_at` | `TIMESTAMP` | sim | Timestamp padrão Laravel |

#### Constraints de `manual_food_registrations`

- `PRIMARY KEY (id)`.
- `UNIQUE (food_id)`.
- `FOREIGN KEY (food_id) REFERENCES foods(id) ON DELETE RESTRICT`.
- `CHECK (current_version_number >= 1)`.
- `CHECK (reference_unit = 'g')`.
- Trigger `BEFORE INSERT OR UPDATE` garantindo que o registro referenciado em `foods` não possua `UPPER(BTRIM(source)) = 'TACO'`.

#### Relacionamentos Eloquent

- `ManualFoodRegistration belongsTo Food`.
- `ManualFoodRegistration hasMany ManualFoodVersion` (ordenadas por `version_number`).
- `Food hasOne ManualFoodRegistration` (método aditivo opcional no Model `Food`, sem alterar o comportamento da TACO).

---

### 2. `manual_food_versions` *(imutável)*

Registra cada versão histórica completa dos metadados de identificação, origem, evidência e responsabilidade do alimento manual. Nenhuma linha pode sofrer `UPDATE` ou `DELETE` após inserida.

| Campo | Tipo PostgreSQL | Obrigatório | Regra |
|---|---|---|---|
| `id` | `BIGINT` | sim | Chave primária |
| `manual_food_registration_id` | `BIGINT` | sim | FK para `manual_food_registrations.id` (`ON DELETE RESTRICT`) |
| `version_number` | `INTEGER` | sim | `>= 1`; sequencial por alimento manual |
| `name` | `TEXT` | sim | Não vazio após `BTRIM` |
| `category` | `TEXT` | não | `NULL` ou não vazio após `BTRIM` |
| `source` | `TEXT` | sim | Não vazio após `BTRIM`; proibido `'TACO'` (case-insensitive) |
| `source_code` | `TEXT` | sim | Não vazio após `BTRIM` |
| `reference_unit` | `VARCHAR(2)` | sim | Fixo em `'g'` |
| `evidence_reference` | `TEXT` | sim | Identificador verificável do laudo, rótulo ou referência; não vazio após `BTRIM` |
| `evidence_source` | `TEXT` | sim | Procedência/emissor da evidência; não vazio após `BTRIM` |
| `registered_by` | `TEXT` | sim | Responsável identificável pelo cadastro ou atualização; não vazio após `BTRIM` |
| `registered_on` | `DATE` | sim | Data da evidência/registro |
| `change_reason` | `TEXT` | condicional | `NULL` ou texto na versão `1`; obrigatório e não vazio após `BTRIM` quando `version_number > 1` |
| `created_at` | `TIMESTAMP` | sim | Momento técnico da gravação da versão (sem `updated_at`) |

#### Constraints de `manual_food_versions`

- `PRIMARY KEY (id)`.
- `UNIQUE (manual_food_registration_id, version_number)`.
- `FOREIGN KEY (manual_food_registration_id) REFERENCES manual_food_registrations(id) ON DELETE RESTRICT`.
- `CHECK (version_number >= 1)`.
- `CHECK (BTRIM(name) <> '')`.
- `CHECK (category IS NULL OR BTRIM(category) <> '')`.
- `CHECK (BTRIM(source) <> '' AND UPPER(BTRIM(source)) <> 'TACO')`.
- `CHECK (BTRIM(source_code) <> '')`.
- `CHECK (reference_unit = 'g')`.
- `CHECK (BTRIM(evidence_reference) <> '')`.
- `CHECK (BTRIM(evidence_source) <> '')`.
- `CHECK (BTRIM(registered_by) <> '')`.
- `CHECK (version_number = 1 OR (change_reason IS NOT NULL AND BTRIM(change_reason) <> ''))`.
- Trigger `BEFORE UPDATE OR DELETE` invocando `prevent_manual_food_version_mutation()`.

---

### 3. `manual_food_version_nutrients` *(imutável)*

Registra a composição nutricional exata pertencente a uma versão específica em `manual_food_versions`. Nenhuma linha pode sofrer `UPDATE` ou `DELETE` após inserida.

| Campo | Tipo PostgreSQL | Obrigatório | Regra |
|---|---|---|---|
| `id` | `BIGINT` | sim | Chave primária |
| `manual_food_version_id` | `BIGINT` | sim | FK para `manual_food_versions.id` (`ON DELETE RESTRICT`) |
| `nutrient_id` | `BIGINT` | sim | FK para `nutrients.id` (`ON DELETE RESTRICT`) |
| `amount_value` | `DECIMAL(7, 2)` | condicional | Presente e `>= 0` somente quando `status = 'EXACT'`; `NULL` nos demais estados |
| `status` | `TEXT` | sim | `EXACT`, `TRACE`, `NA`, `ABSENT` ou `REEVALUATION` |

#### Constraints de `manual_food_version_nutrients`

- `PRIMARY KEY (id)`.
- `UNIQUE (manual_food_version_id, nutrient_id)`.
- `FOREIGN KEY (manual_food_version_id) REFERENCES manual_food_versions(id) ON DELETE RESTRICT`.
- `FOREIGN KEY (nutrient_id) REFERENCES nutrients(id) ON DELETE RESTRICT`.
- `CHECK (status IN ('EXACT', 'TRACE', 'NA', 'ABSENT', 'REEVALUATION'))`.
- `CHECK ((status = 'EXACT' AND amount_value IS NOT NULL AND amount_value >= 0 AND amount_value <> 'Infinity'::numeric AND amount_value <> 'NaN'::numeric) OR (status <> 'EXACT' AND amount_value IS NULL))`.
- Trigger `BEFORE UPDATE OR DELETE` invocando `prevent_manual_food_version_mutation()`.

---

## Regras de integridade e proteção em `foods`

Para garantir o bloqueio estrito de duplicidade (`Q3: A` e `FR-006`) em PostgreSQL mantendo compatibilidade com os dados TACO existentes:

1. **Unicidade por fonte + código**:
   - Índice único funcional em `foods`:
     `CREATE UNIQUE INDEX foods_normalized_source_code_unique ON foods (UPPER(BTRIM(source)), UPPER(BTRIM(source_code))) WHERE source_code IS NOT NULL;`
   - Impede qualquer duplicação de `(source, source_code)` ignorando diferenças de caixa e espaços externos.
2. **Unicidade por fonte + nome normalizado**:
   - Como a extensão `unaccent` já está instalada na base pela migration `2026_10_06_153527_enable_unaccent_extension.php`, o `ManualFoodRegistrationService` valida antes da persistência (sob `REPEATABLE READ`) que não existe nenhum `Food` com:
     - mesmo `UPPER(BTRIM(source))` e mesmo `UPPER(BTRIM(source_code))`;
     - mesmo `UPPER(BTRIM(source))` e `unaccent(LOWER(BTRIM(name)))` igual;
     - colisão exata com nome normalizado de alimento da TACO quando o código da fonte também coincidir, ou tentativa de usar `source = 'TACO'`.
   - Adicionalmente, índice único em `foods` por `(UPPER(BTRIM(source)), LOWER(BTRIM(name)))` pode ser adicionado caso verificado sem colisão nos 597 alimentos TACO atuais (verificado em implementação/migration). Caso a base TACO possua variações acentuadas que exijam `unaccent` via função `IMMUTABLE` ou validação transacional no Service com lock apropriado, a decisão é registrada na migration sem alterar registros TACO existentes.

---

## Sincronização transacional da projeção vigente (`foods` e `food_nutrients`)

Dentro de transação `REPEATABLE READ`:

1. **Cadastro (`POST /api/manual-foods`)**:
   - Cria `foods` (`name`, `category`, `source`, `source_code`).
   - Cria `manual_food_registrations` (`food_id`, `is_active = true`, `current_version_number = 1`, `reference_unit = 'g'`).
   - Cria `manual_food_versions` (`version_number = 1`, todos os metadados de origem/evidência/responsável).
   - Cria linhas em `manual_food_version_nutrients` e `food_nutrients` para cada nutriente informado no payload. Nutrientes não informados não geram linhas.

2. **Atualização versionada (`PUT /api/manual-foods/{food}`)**:
   - Bloqueia se `food` não possuir `manualRegistration` ou se `UPPER(BTRIM(food.source)) = 'TACO'`.
   - Incrementa `current_version_number` em `manual_food_registrations`.
   - Insere nova linha imutável em `manual_food_versions` (`version_number = N + 1`, `change_reason` obrigatório) e suas linhas em `manual_food_version_nutrients`.
   - Atualiza `foods` (`name`, `category`, `source`, `source_code`) e substitui as linhas vigentes de `food_nutrients` do respectivo `food_id` pelos nutrientes da versão `N + 1`.
   - Nunca modifica `nutrition_calculations`, `nutrition_calculation_ingredients`, `nutrition_calculation_contributions`, `serving_daily_value_calculations` ou `declared_nutrition_calculations`.

3. **Inativação / reativação lógica (`PATCH /api/manual-foods/{food}/status`)**:
   - Bloqueia se `food` não possuir `manualRegistration` ou se `UPPER(BTRIM(food.source)) = 'TACO'`.
   - Atualiza apenas `manual_food_registrations.is_active`.
   - Não altera versões históricas em `manual_food_versions`, não apaga `foods` nem altera receitas ou cálculos existentes.
