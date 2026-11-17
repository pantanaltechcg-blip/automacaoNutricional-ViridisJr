# Research: Declared Values, Rounding, and Non-Significant Quantities

## 1. Consumo da cadeia imutável das Features 003 e 004

**Decision**: A Feature 005 consome exclusivamente uma única execução imutável `ServingDailyValueCalculation` (Feature 004) e sua origem vinculada `NutritionCalculation` (Feature 003), sem consultar ou recalcular `Recipe`, `RecipeIngredient`, `Food`, `Nutrient` ou `FoodNutrient`, e sem alterar registros ou schemas das Features 001–004 (`FR-001`, `FR-002`).

- Os valores nutricionais racionais exatos na **porção**, as correspondências `MAP`, conversões, fatores `ENE`, aplicabilidades `ENE`, linhas `VDR`, autorização de porção (`portion_*`) e suas evidências vêm do snapshot imutável da Feature 004 (`serving_daily_value_calculations`, `serving_daily_value_nutrients`, `serving_daily_value_regulatory_results`, `serving_daily_value_energy_components`, `serving_daily_value_percentages`).
- Os valores nutricionais racionais exatos em **100 g/100 ml** (`reference_numerator` / `reference_denominator`, `reference_basis = 100`, `reference_unit`) vêm diretamente da tabela imutável `nutrition_calculation_nutrients` vinculada via `serving_daily_value_nutrients.nutrition_calculation_nutrient_id`.
- A conversão regulatória para a base de `100 g/100 ml` reutiliza exatamente o snapshot da correspondência `MAP` e do fator de conversão preservados em `serving_daily_value_regulatory_results` (exigindo autorização técnica e evidência válidas no ambiente de execução).

**Rationale**: Evita divergência entre o snapshot da Feature 004 e as tabelas atuais de alimentos/receitas, garante rastreabilidade integral de `100 g/100 ml` e da porção e atende integralmente a `FR-001`, `FR-002` e `CQ-003`.

**Alternatives considered**: Recalcular `100 g/100 ml` a partir de `portion * 100 / portion_value` (rejeitado por desnecessário e por ignorar o valor exato já snapshotado na Feature 003) ou ler `regulatory_profiles` atuais ignorando o snapshot da Feature 004 (rejeitado por risco de divergência histórica e violação de `FR-001` / `FR-013`).

---

## 2. Vinculação estrita ao perfil regulatório, versão normativa e escopo

**Decision**: A Feature 005 introduz `RegulatoryDeclarationProfile` como pacote versionado e imutável de regras de declaração, quantidades não significativas, arredondamento, ordem de recálculo e requisitos de rotulagem (`FR-001`, `FR-004`, `FR-010`).

Ao solicitar a criação de uma execução declarada (`POST`), o `DeclaredValuesRegulatoryGateService` exige coincidência exata entre o `RegulatoryDeclarationProfile` selecionado e o snapshot da Feature 004 em:
1. `regulatory_profile_code`;
2. `regulatory_profile_version`;
3. `regulatory_profile_normative_source`;
4. `regulatory_profile_normative_version`;
5. escopo de produto/formulação (`scope_recipe_id` quando preenchido deve igualar `source_recipe_id`; `scope_nutrition_calculation_id` quando preenchido deve igualar `nutrition_calculation_id`).

Divergência entre o pacote de declaração escolhido e o perfil/versão normativa/escopo preservados na execução 004 rejeita a requisição via `ValidationException` antes de qualquer persistência (`FR-001`). Já um `RegulatoryDeclarationProfile` compatível cujo `technical_status` seja `BLOCKED` (ou que possua regras individuais `BLOCKED`/`UNAVAILABLE`) é aceito para registrar de forma segura a execução imutável com valores `null`, `is_label_ready = false` e causas explícitas (`FR-006`, `FR-010`).

**Rationale**: Impede mistura silenciosa de perfis regulatórios ou versões normativas entre a etapa 004 e a etapa 005, mantendo o comportamento *deny-by-default* da Feature 004 sem alterar tabelas da Feature 004.

**Alternatives considered**: Reaproveitar diretamente `regulatory_profile_id` alterando tabelas da Feature 004 (rejeitado por violar `FR-002` e acoplar alterações de schema na Feature 004) ou aceitar qualquer perfil de arredondamento ignorando o perfil da execução 004 (rejeitado por violar `FR-001`).

---

## 3. Aritmética racional exata para arredondamento e formatação decimal

**Decision**: Estender `App\Services\NutritionCalculationArithmetic` (dependência `brick/math` 1.0.0) apenas com operações matemáticas genéricas sobre `Brick\Math\BigRational` e `Brick\Math\BigInteger`, sem regras regulatórias específicas:

1. **Comparação exata**: `$left->compareTo($right)` para avaliar limites de faixas (`min`/`max`, inclusivo/exclusivo) e thresholds de quantidades não significativas (`LTE` / `LT`), sem ponto flutuante.
2. **Arredondamento ao incremento com desempate `HALF_UP`**:
   Dado valor racional $v \ge 0$ e incremento positivo $s > 0$ (`BigRational`), calcular o quociente racional $r = v / s$ e arredondar para inteiro via `BigRational::toScale(0, RoundingMode::HalfUp)`:
   $$q = \left\lfloor \frac{v}{s} + \frac{1}{2} \right\rfloor = \text{BigRational}(v / s).\text{toScale}(0, \text{RoundingMode::HalfUp})$$
   e reconstruir a razão exata arredondada:
   $$v_{\text{rounded}} = \text{BigRational::of}(q) \times s$$
   O uso de `BigRational` + `BigInteger` internos do `brick/math` elimina qualquer risco de overflow de inteiro nativo (`PHP_INT_MAX`) em frações com numeradores/denominadores grandes provenientes de receitas com múltiplos ingredientes.
3. **Formatação decimal exata (`FR-003`)**:
   Formatar $v_{\text{rounded}}$ com `$decimalPlaces` exatos via `$v_rounded->toScale($decimalPlaces, RoundingMode::Unnecessary)->toString()`. Como `RoundingMode::Unnecessary` lança exceção se o incremento $s$ não for exatamente representável em `decimal_places` casas decimais, fica garantido matematicamente que não ocorre arredondamento ou truncamento silencioso na serialização.

**Rationale**: Cumpre `FR-003` (preservar simultaneamente string decimal na escala exata da faixa normativa e razão exata `numerator`/`denominator` já arredondada), evita `float`, `double` e `bcmath` com escala arbitrária, e mantém `NutritionCalculationArithmetic` agnóstico ao domínio regulatório.

**Alternatives considered**: `intdiv` nativo em PHP (rejeitado por risco de overflow acima de `PHP_INT_MAX`), `round()` nativo (rejeitado por usar IEEE-754 `float`), `bcmath` com escala fixa (rejeitado por truncamento intermediário em dízimas).

---

## 4. Modelagem data-driven de faixas de arredondamento e quantidades não significativas (RDC nº 429/2020 e IN nº 75/2020)

**Decision**: Em conformidade com a Constitution 2.0.0 (Princípio I — Não Invenção), `CQ-001`, `FR-004` e `FR-010`, nenhuma tabela numérica fixa da RDC nº 429/2020 ou IN nº 75/2020 será hardcoded no código PHP nem promovida automaticamente como `AUTHORIZED` por Seeder ou migration. As regras são modeladas em tabelas versionadas e *deny-by-default*, exigindo `technical_status = AUTHORIZED` e tupla de evidência completa (`evidence_reference`, `evidence_source`, `evidence_version`, `evidence_scope`, `evidence_kind`, `validated_by`, `validated_on`), com `TEST_FIXTURE` restrito exclusivamente ao ambiente `testing`:

- **Catálogo oficial a transcrever sob governança (estado inicial bloqueado até validação técnica/documental formal)**:
  - **RDC nº 429/2020**: Art. 7º, II (quantidades não significativas); Art. 12 (`%VD`); Art. 32, § 1º (cálculo do valor energético a partir dos valores declarados dos nutrientes).
  - **IN nº 75/2020 — Anexo III** (critérios de arredondamento e expressão numérica por faixas: inteiros para valor energético e `%VD`, faixas por ordem de grandeza para nutrientes em `g`, `mg`, `µg`, regra do dígito `5` / `HALF_UP` e escala de casas decimais).
  - **IN nº 75/2020 — Anexo IV** (limites de quantidades não significativas por nutriente/energia e base aplicável `100 g/100 ml` e/ou porção, bem como critério de declaração como `"0"`).
- **Estrutura configurável**:
  - `regulatory_non_significant_rules`: regra por `(declaration_profile_id, target_kind, map_code, basis)` com operador (`LTE` ou `LT`), threshold racional exato (`threshold_numerator`/`denominator`), unidade, expressão declarada (`"0"`) e razão declarada (`0/1`). Cada base (`REFERENCE_100` e `PORTION`) é avaliada estritamente segundo a regra autorizada para aquela base, impedindo contaminação indevida entre bases (Edge Case da Specification).
  - `regulatory_rounding_rules` + `regulatory_rounding_rule_ranges`: regra por `(declaration_profile_id, target_kind, basis, map_code/vdr_code)` com critério de desempate (`HALF_UP`) e faixas ordenadas (`min`, `max`, inclusividades, `step_numerator`/`step_denominator`, `decimal_places`). O `DeclaredValuesRegulatoryGateService` valida que a regra autorizada possui faixas não vazias, sem sobreposição e sem lacuna no domínio avaliado.

**Rationale**: Permite testar de forma determinística todo o motor normativo com fixtures controladas (`TEST_FIXTURE`) e bloqueios seguros em produção (`BLOCKED`), mantendo a transcrição oficial isolada sob controle de evidência e revisão técnica.

**Alternatives considered**: Hardcode de faixas da IN nº 75/2020 em classes PHP (rejeitado por violar `FR-004`, `FR-010` e `CQ-001`, que exigem registro versionado, auditável e *deny-by-default*) ou seeder automático marcando regras como `AUTHORIZED` (rejeitado pela Constitution 2.0.0 e padrão da Feature 004).

---

## 5. Ordem normativa de recálculo final e tratamento de incompletude (`CQ-002`, `FR-005`, `FR-008`)

**Decision**: O `DeclaredNutritionCalculationService` executa o cálculo em 4 etapas determinísticas sob transação `REPEATABLE READ`, exigindo `RegulatoryRecalculationPolicy` com `technical_status = AUTHORIZED`, `requires_complete_energy_inputs = true` e evidência válida:

1. **Etapa 1 — Valores Nutricionais Declarados (`REFERENCE_100` e `PORTION`)**:
   Para cada componente `MAP` presente no snapshot da Feature 004:
   - Preserva estados qualitativos (`TRACE`, `NA`, `ABSENT`, `REEVALUATION`), `has_missing_relation` e `is_complete` da origem.
   - Em cada base (`REFERENCE_100` e `PORTION`) de forma independente:
     - Se houver bloqueio no `MAP`/conversão, estado qualitativo impeditivo, ausência de relação, ausência de número ou subtotal parcial (`is_complete = false`), o valor declarado daquela base permanece `null` com causa explícita (`FR-007`, `FR-008`). Nunca gera zero por NSQ.
     - Se o valor regulatório exato for completo (`is_complete = true`) e não houver bloqueios: verifica a regra de NSQ autorizada para `(target_kind = COMPONENT, map_code, basis)`. Se satisfizer o limite (`LTE`/`LT`), declara conforme regra (`"0"`, `0/1`, `nsq_applied = true`, mantendo distinto de zero numérico exato `0/1` via `is_exact_zero`). Caso contrário, aplica a regra de arredondamento autorizada para `(COMPONENT, map_code, basis)` e grava `declared_string` e `declared_numerator`/`denominator`.
2. **Etapa 2 — Recálculo do Valor Energético Regulatório Final (`REFERENCE_100` e `PORTION`)**:
   Exige `RegulatoryRecalculationPolicy` autorizada + matriz `ENE` completa e autorizada (todos os componentes `ENE` snapshotados na Feature 004 com `applicability_status IN ('APPLICABLE', 'NOT_APPLICABLE')`, sem `UNKNOWN`, sem `BLOCKED` e sem conflito de sobreposição).
   - Para cada base (`REFERENCE_100` e `PORTION`):
     - Cada componente `APPLICABLE` da matriz `ENE` deve possuir valor **declarado completo** (`declared_numerator/denominator !== null` e `is_complete = true`) na respectiva base (`FR-008`). Se qualquer componente energético aplicável estiver `null`, parcial (`is_complete = false`) ou bloqueado, a energia regulatória final daquela base permanece **`null` (`UNAVAILABLE`, `is_complete = false`)**, sem gerar subtotal energético parcial (`FR-008`).
     - Quando todos os componentes aplicáveis possuírem valores declarados completos, calcula `exact_energy = sum(declared_component_value * ene_factor)` em `BigRational`, aplica regra de NSQ de energia autorizada para a base (se aplicável) e, não sendo não significativa, aplica a regra de arredondamento autorizada para `(ENERGY, basis)`.
3. **Etapa 3 — Recálculo do `%VD` Regulatório Final (`PORTION`)**:
   Para cada linha `VDR` snapshotada na Feature 004:
   - Se `technical_status === 'NO_NORMATIVE_VDR'` (com evidência válida da Feature 004), preserva `availability = 'NO_NORMATIVE_VDR'` e `declared_percentage = null`, distinto de erro/bloqueio (`FR-009`).
   - Se a linha `VDR` exigir numerador de componente (`map_code !== null`), consome exclusivamente o valor declarado na porção do respectivo `MAP` (exigindo `portion_is_complete = true` e `portion_declared_numerator !== null`).
   - Se a linha `VDR` referir-se a energia (`map_code === null`, vinculada explicitamente por regra autorizada `vdr_energy_binding` / `RegulatoryRoundingRule` de `DAILY_VALUE` com unidade compatível `kcal`), consome exclusivamente a **energia regulatória final declarada na porção** (exigindo `portion_energy_is_complete = true`).
   - Calcula `unrounded_vd = input_declared * 100 / vdr_value` em `BigRational` e aplica a regra de arredondamento autorizada para `(DAILY_VALUE, PERCENT_VD, vdr_code)` (sem NSQ próprio ou após verificação conforme regra autorizada), gravando `declared_percentage_string` e `declared_percentage_numerator`/`denominator`.
   - Em caso de qualquer pendência no `VDR`, no valor declarado de entrada, na política de recálculo ou na regra de arredondamento, o `%VD` final permanece `null` (`UNAVAILABLE`), nunca substituindo pelo `%VD` derivado exato da Feature 004 (`FR-005`).
4. **Etapa 4 — Avaliação Global de Aptidão à Rotulagem (`is_label_ready`, `FR-011`)**:
   `is_label_ready = true` somente se **todos** os critérios cumulativos forem satisfeitos:
   - `RegulatoryDeclarationProfile` e `RegulatoryRecalculationPolicy` com `technical_status = AUTHORIZED` e evidência válida;
   - Snapshot da porção e medida caseira (`PROVIDED` / `WAIVED` / `NOT_APPLICABLE`) da Feature 004 com `portion_reference_technical_status = AUTHORIZED` e evidência válida no ambiente atual;
   - Existência de `RegulatoryDeclarationRequirement` autorizados para o perfil (conjunto vazio bloqueia por *deny-by-default*);
   - Todos os requisitos `MANDATORY` (e `VOLUNTARY`/`CONDITIONAL` marcados como `APPLICABLE`) satisfeitos e sem nenhum requisito em `UNKNOWN` ou `BLOCKED`:
     - Para `COMPONENT` aplicável: `reference_100_availability = 'AVAILABLE'` e `portion_availability = 'AVAILABLE'`;
     - Para `ENERGY` aplicável: `reference_100_energy_availability = 'AVAILABLE'` e `portion_energy_availability = 'AVAILABLE'`;
     - Para `DAILY_VALUE` aplicável: `availability IN ('AVAILABLE', 'NO_NORMATIVE_VDR')`.
   Qualquer falha registra causa estruturada no escopo `LABEL_READINESS` e mantém `is_label_ready = false`.

**Rationale**: Garante conformidade literal com `DEC-004-009`, `CQ-002`, `CQ-003`, `FR-005`, `FR-008` e `FR-011`.

**Alternatives considered**: Arredondar diretamente a energia e os `%VD` derivados da Feature 004 (rejeitado por violar `FR-005` e `DEC-004-009`) ou emitir energia parcial quando um componente energético for parcial (rejeitado explicitamente por `FR-008` / `Q6`).

---

## 6. Imutabilidade, isolamento transacional e contorno HTTP

**Decision**:
- Seguir exatamente o padrão arquitetural das Features 003 e 004: `FormRequest -> Controller -> Service (DB::transaction + REPEATABLE READ) -> Models -> PostgreSQL -> Resource`.
- Criar triggers PostgreSQL `BEFORE UPDATE OR DELETE` (`prevent_declared_nutrition_mutation()`) protegendo todas as novas tabelas de referência e de snapshot histórico.
- Expor apenas os dois endpoints de API necessários ao ciclo de vida da Feature 005 (sem interface visual, HTML/CSS, rotulagem frontal, exportação ou CRUD administrativo, conforme `FR-014`):
  - `POST /api/serving-daily-value-calculations/{servingDailyValueCalculation}/declared-nutrition-calculations`
  - `GET /api/declared-nutrition-calculations/{declaredNutritionCalculation}`
- Manter os middlewares padrão de normalização de transporte (`TrimStrings`, `ConvertEmptyStringsToNull`).

**Rationale**: Reutiliza convenções validadas do projeto e respeita estritamente os limites da Constitution 2.0.0 e `FR-012`–`FR-014`.

**Alternatives considered**: Endpoints de edição/exclusão ou administração de regras regulatórias nesta feature (rejeitados por violar imutabilidade e escopo de `FR-013`/`FR-014`).
