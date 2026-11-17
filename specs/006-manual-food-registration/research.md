# Research: Governed Manual Food Registration (`006-manual-food-registration`)

**Date**: 2026-11-17 | **Spec**: [spec.md](./spec.md)

## Decisions

### 1. Integração com `foods` e `food_nutrients` sem quebrar Features 001–005

- **Decision**: Preservar `foods` e `food_nutrients` como catálogo operacional consultado por `NutritionBaseService::search()` (`GET /api/foods`), `RecipeService`, `StoreRecipeRequest`, `UpdateRecipeRequest` e `NutritionCalculationService`, acrescentando governança, metadados de evidência e histórico imutável de versões em tabelas dedicadas (`manual_food_registrations`, `manual_food_versions` e `manual_food_version_nutrients`).
- **Rationale**: Os contratos e testes existentes (`FoodSearchTest`, `RecipePersistenceTest`, `NutritionCalculationPersistenceTest`) dependem diretamente de `foods.id`, `food_nutrients.id`, da chave estrangeira `recipe_ingredients.food_id -> foods.id` e do formato exato de resposta de `GET /api/foods?q=...` (`['category', 'id', 'name', 'nutrients', 'source', 'source_code']`). Manter a projeção vigente do alimento manual em `foods` e `food_nutrients` permite reutilização transparente em receitas e cálculos sem alterar o schema ou quebrar snapshots das Features 001–005.
- **Alternatives considered**:
  - Armazenar alimentos manuais em tabela isolada sem linha correspondente em `foods`: rejeitado porque quebraria `recipe_ingredients.food_id -> foods.id` e exigiria duplicar ou reescrever toda a cadeia de receitas e cálculos da Feature 002/003.
  - Adicionar colunas de evidência e versão diretamente em `foods`: rejeitado para não misturar metadados exclusivos de cadastro manual aos registros da base oficial TACO nem exigir colunas anuláveis sem significado para registros TACO.

### 2. Proteção estrita dos registros da TACO (`source = 'TACO'`)

- **Decision**: Proibir em todas as camadas (Form Request, `ManualFoodRegistrationService` e constraints/triggers PostgreSQL) qualquer criação, edição, versionamento ou alteração de status via fluxo manual quando `UPPER(BTRIM(source)) = 'TACO'`, bem como bloquear operações dos endpoints `/api/manual-foods/{food}` sobre qualquer registro de `foods` que não possua vínculo em `manual_food_registrations` ou cuja origem seja `TACO`.
- **Rationale**: Garante conformidade inegociável com o Princípio V da Constitution, `FR-001`, `SC-002` e o documento de backlog `specs/backlog/manual-food-registration.md`. Defesa em profundidade no PostgreSQL impede mutação acidental mesmo fora da camada HTTP.
- **Alternatives considered**:
  - Validar apenas na camada HTTP (`FormRequest`): rejeitado porque chamadas diretas ao Service ou manipulações em banco não teriam garantia estrutural contra mutação da TACO.

### 3. Prevenção de duplicidade por identidade exata normalizada

- **Decision**: Aplicar bloqueio estrito de duplicidade no cadastro e na atualização de alimentos manuais contra toda a tabela `foods` (TACO e manuais) para duas chaves normalizadas:
  1. `UPPER(BTRIM(source))` + `UPPER(BTRIM(source_code))`;
  2. `UPPER(BTRIM(source))` + `lower(unaccent(BTRIM(name)))` (para impedir duplicação de nome equivalente dentro da mesma fonte, além de bloquear o uso de nomes já existentes na TACO quando combinados à fonte protegida).
  Também aplicar índices únicos funcionais/parciais no PostgreSQL para garantir atomicidade sob concorrência em transações `REPEATABLE READ`.
- **Rationale**: Atende diretamente à decisão aprovada (`Q3: A` e `CQ-003`) de bloqueio estrito por identidade exata sem sobrescrita silenciosa de registros existentes e aproveita a extensão `unaccent` já instalada no PostgreSQL pela Feature 001.
- **Alternatives considered**:
  - Permitir duplicidades com apenas um aviso textual: rejeitado pela decisão explícita `Q3: A`.
  - Comparação literal sem normalização de espaços/caixa/acentos: rejeitado porque permitiria duplicatas artificiais por diferença de maiúsculas ou espaços.

### 4. Versionamento imutável e preservação de cálculos históricos

- **Decision**: Cada cadastro manual cria `ManualFoodRegistration` (estado inicial `is_active = true`, `current_version_number = 1`) e a versão `1` em `manual_food_versions` + `manual_food_version_nutrients`. Cada atualização (`PUT /api/manual-foods/{food}`) exige `change_reason`, cria uma nova linha imutável `version_number = N + 1` em `manual_food_versions` + `manual_food_version_nutrients` e substitui apenas a projeção vigente em `foods` e `food_nutrients` dentro da mesma transação PostgreSQL `REPEATABLE READ`. As tabelas `manual_food_versions` e `manual_food_version_nutrients` são protegidas por trigger `BEFORE UPDATE OR DELETE` no PostgreSQL (padrão idêntico às Features 003 e 004).
- **Rationale**: Cálculos históricos da Feature 003 (`NutritionCalculation`) já copiam `food_source`, `food_source_code`, `input_status`, `input_amount_value`, `value_numerator` e `value_denominator` no momento da execução e possuem triggers próprios contra `UPDATE`/`DELETE`. Assim, atualizar a projeção vigente em `foods`/`food_nutrients` afeta exclusivamente **novos** cálculos, enquanto todas as versões anteriores do alimento manual e todos os cálculos históricos (003/004/005) permanecem 100% imutáveis e auditáveis.
- **Alternatives considered**:
  - Criar um novo `foods.id` a cada correção do alimento manual: rejeitado porque quebraria o vínculo estável com receitas existentes e fragmentaria a identidade do alimento no catálogo.
  - Editar `foods`/`food_nutrients` sem tabela de versões imutáveis: rejeitado porque perderia o histórico de evidências, responsáveis e composições anteriores exigido em `Q1: A` do Clarify e `FR-008`.

### 5. Inativação lógica e disponibilidade em buscas e receitas

- **Decision**: `manual_food_registrations.is_active` controla a disponibilidade do alimento manual. `NutritionBaseService::search()` exclui apenas alimentos vinculados a `manual_food_registrations` com `is_active = false` (preservando integralmente todos os alimentos TACO e todos os alimentos manuais ativos). Na criação de receitas (`StoreRecipeRequest`), qualquer `food_id` manual inativo é rejeitado. Na atualização de receitas (`UpdateRecipeRequest`), um `food_id` manual inativo somente é aceito se já estiver vinculado àquela receita antes da edição; qualquer inclusão nova de alimento manual inativo é rejeitada.
- **Rationale**: Garante `FR-007` e `FR-009`: novos alimentos manuais ficam disponíveis imediatamente ao serem cadastrados (`Q1: A` do Specify), e alimentos inativados saem da busca e de novas associações sem quebrar receitas existentes ou snapshots históricos.
- **Alternatives considered**:
  - Exclusão física (`DELETE`) do alimento manual: rejeitado porque violaria integridade referencial de `recipe_ingredients` e apagaria rastreabilidade histórica.
  - Bloquear até edição de receitas que já continham o alimento antes da inativação: rejeitado porque impediria ajustar a quantidade de outro ingrediente em uma receita preexistente sem obrigar a remover o histórico do ingrediente anterior.

### 6. Base de referência dimensional, precisão numérica e estados qualitativos

- **Decision**: Nesta fase, `reference_unit` do alimento manual é restrita a `'g'` (composição por `100 g`, compatível com `recipe_ingredients.unit = 'g'`), mantendo `100 ml` fora desta etapa até que receitas suportem ingredientes em volume, sem nenhuma conversão automática por densidade. Valores numéricos `EXACT` são validados como strings decimais não negativas (`^\d+(?:\.\d{1,2})?$`) dentro da capacidade exata de `food_nutrients.amount_value` (`NUMERIC(7,2)`, no máximo 5 dígitos inteiros e 2 casas decimais) sem conversão para `float`/`double` e sem arredondamento silencioso. Os estados permitidos são `EXACT`, `TRACE`, `NA`, `ABSENT` e `REEVALUATION` (os quatro últimos exigindo `amount_value = null`). Exige-se ao menos 1 nutriente informado; nutrientes do catálogo não informados não geram linhas em `food_nutrients` nem em `manual_food_version_nutrients` (preservando a ausência explícita como `is_missing_relation` nos cálculos futuros, nunca `0`).
- **Rationale**: Alinhamento total com a `FoodNutrient` da Feature 001, `NutritionCalculationService` da Feature 003 e Princípios I, IV e V da Constitution.
- **Alternatives considered**:
  - Aceitar `reference_unit = 'ml'` agora e converter automaticamente para `g`: rejeitado porque a Constitution e a Feature 004 proíbem conversão de densidade inferida.
  - Preencher nutrientes não informados com `0.00` ou criar produto cartesiano de `ABSENT`: rejeitado pelas regras da Feature 001 (`NutritionBaseStorageTest` e `data-model.md` da 001).

### 7. Normalização de transporte HTTP e segurança operacional

- **Decision**: Seguir o mesmo padrão das Features 001–005: `TrimStrings` e `ConvertEmptyStringsToNull` permanecem ativos na fronteira HTTP; strings decimais (`amount_value`) são recebidas e retornadas como strings (nunca números JSON de ponto flutuante na entrada de criação/edição manual); data `registered_on` validada em formato `Y-m-d` sem datas futuras implícitas inválidas (ou formato ISO padrão `date_format:Y-m-d`). Os novos endpoints seguem a mesma convenção sem autenticação própria já documentada nas Features 001–005, mantendo registrado o alerta operacional de não expor a API publicamente sem camada de proteção de acesso.
- **Rationale**: Consistência completa com os contratos de `recipes.md` e `serving-daily-values.md` e com a seção de Segurança Operacional do projeto.
- **Alternatives considered**:
  - Introduzir autenticação ad-hoc apenas em `/api/manual-foods`: rejeitado porque autenticação constitui módulo próprio na Constitution e não pertence ao escopo desta feature.
