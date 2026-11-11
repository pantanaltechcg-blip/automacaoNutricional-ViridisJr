# Data Model: Serving and Daily Values

## Visão geral

```text
NutritionCalculation (existente, imutável)
  └── N ServingDailyValueCalculation
        ├── N ServingDailyValueNutrient
        │     └── N ServingDailyValueNutrientState
        ├── N ServingDailyValueRegulatoryResult
        ├── N ServingDailyValueEnergyComponent
        ├── N ServingDailyValuePercentage
        └── N ServingDailyValueResultCause

RegulatoryProfile (imutável)
  ├── N RegulatoryNutrientMapping (MAP)
  ├── N RegulatoryEnergyComponent (ENE)
  └── N RegulatoryDailyValue (VDR)

NutritionCalculation
  ├── N PortionAuthorization
  └── N RegulatoryEnergyApplicability
```

O primeiro conjunto é histórico: copia tudo que foi efetivamente usado, bloqueado ou
produzido. O segundo é uma fonte técnica versionada e deny-by-default. Não existe
interface administrativa nesta feature. `recipe_id` da origem identifica o produto e
`nutrition_calculation_id` identifica a formulação imutável avaliada.

## Convenção de razões exatas

Todo valor calculado ou fator racional usa:

- `<prefix>_numerator`: `NUMERIC` sem precisão/escala, inteiro;
- `<prefix>_denominator`: `NUMERIC` sem precisão/escala, inteiro positivo;
- ambos nulos ou ambos presentes;
- zero canônico: `0 / 1`.

Checks PostgreSQL rejeitam infinito, `NaN`, escala não zero, denominador não positivo,
presença parcial do par e zero não canônico. A aplicação lê/escreve strings e não usa
cast `float`, `double` ou `decimal:N`.

## Convenção de autorização técnica composta

Toda referência capaz de liberar cálculo possui, além do estado técnico, uma tupla de
evidência composta por:

- `evidence_reference`: identificador verificável do documento/decisão;
- `evidence_source`: origem rastreável;
- `evidence_version`: versão exata abrangida;
- `evidence_scope`: regra, componente, produto e/ou formulação abrangidos;
- `evidence_kind`: `DOCUMENTED` ou `TEST_FIXTURE`;
- `validated_by`: responsável identificável;
- `validated_on`: data da validação.

Constraints PostgreSQL exigem que a tupla esteja integralmente nula ou integralmente
preenchida, rejeitam strings vazias, restringem `evidence_kind` e exigem tupla completa
quando o estado técnico for `AUTHORIZED`. A presença da tupla não autoriza uma linha
`BLOCKED` ou `UNAVAILABLE`. A compatibilidade semântica do escopo, produto/formulação,
dependências e ambiente é validada pelo `ServingRegulatoryGateService`.

`TEST_FIXTURE` somente pode satisfazer um gate quando a aplicação está no ambiente
`testing`; em qualquer outro ambiente permanece bloqueada. Nenhuma evidência é recebida
pelo payload público. Um perfil autorizado não autoriza automaticamente MAP, ENE, VDR,
conversão ou aplicabilidade dependentes.

## Referências técnicas versionadas

### RegulatoryProfile

Tabela `regulatory_profiles`. Representa um pacote regulatório identificável, não uma
aprovação administrativa genérica.

| Campo | Tipo | Regra |
|---|---|---|
| `id` | BIGINT | PK |
| `code` | TEXT | Identificador único do perfil |
| `version` | TEXT | Versão imutável |
| `title` | TEXT | Nome rastreável |
| `normative_source` | TEXT | Atos oficiais abrangidos |
| `normative_version` | TEXT | Versão/vigência consultada |
| `consulted_on` | DATE | Data da consulta |
| `technical_status` | TEXT | `AUTHORIZED` ou `BLOCKED` |
| tupla `evidence_*` | campos condicionais | Convenção de autorização composta |
| `created_at` | TIMESTAMP | Momento técnico de registro |

`UNIQUE (code, version)`. Sem `updated_at`. O perfil atual documentado não recebe
`AUTHORIZED` enquanto seus bloqueios técnicos permanecerem. Mesmo um perfil autorizado
não libera linhas dependentes sem suas próprias autorizações.

### RegulatoryNutrientMapping

Tabela `regulatory_nutrient_mappings`, uma linha por MAP do perfil.

Campos: `profile_id`, `map_code`, `component_name`, `nutrient_id` nullable,
`source_unit` nullable, `regulatory_unit`, `technical_status`, `source_reference`,
tupla `evidence_*`, `conversion_status`, par racional opcional de conversão,
`conversion_source_unit`, `conversion_target_unit` e tupla
`conversion_evidence_*`. A autorização da MAP nunca autoriza automaticamente a
conversão.

Constraints:

- `UNIQUE (profile_id, map_code)`;
- `technical_status IN ('AUTHORIZED', 'BLOCKED', 'UNAVAILABLE')`;
- `nutrient_id` ausente nunca implica zero;
- `conversion_status IN ('NOT_REQUIRED', 'AUTHORIZED', 'BLOCKED')`;
- `NOT_REQUIRED` exige tupla própria de evidência completa de que identidade
  nutricional, unidade física e grandeza semântica são compatíveis, sem fator;
  igualdade textual de unidade não basta;
- `AUTHORIZED` exige razão válida, unidades e tupla própria de evidência completa;
- fator de conversão nulo quando `conversion_status` não for `AUTHORIZED`.

### RegulatoryEnergyComponent

Tabela `regulatory_energy_components`, uma linha por ENE do perfil.

Campos: `profile_id`, `ene_code`, `component_name`, `mapping_id` nullable, fator
racional nullable, `factor_input_unit`, `factor_output_unit`, `technical_status`,
`source_reference`, tupla `evidence_*`, `overlap_group` nullable,
`overlap_rule_code` nullable e `overlap_rule_status`.

Constraints:

- `UNIQUE (profile_id, ene_code)`;
- `technical_status IN ('AUTHORIZED', 'BLOCKED', 'UNAVAILABLE')`;
- fator obrigatório somente em linha `AUTHORIZED`;
- linha `AUTHORIZED` exige tupla de evidência completa;
- `overlap_rule_status IN ('AUTHORIZED', 'BLOCKED', 'NOT_REQUIRED')`;
- linhas potencialmente sobrepostas no mesmo grupo não podem participar juntas sem
  regra `AUTHORIZED`.

### RegulatoryDailyValue

Tabela `regulatory_daily_values`, uma linha por VDR do perfil.

Campos: `profile_id`, `vdr_code`, `component_name`, `mapping_id` nullable, VDR como
razão nullable, `unit`, `technical_status`, `source_reference` e
tupla `evidence_*`.

Estados permitidos:

- `AUTHORIZED`: VDR pode passar ao próximo gate;
- `BLOCKED`: transcrição/aplicação não liberada;
- `NO_NORMATIVE_VDR`: ausência normativa explícita, sem percentual;
- `UNAVAILABLE`: linha sem fonte suficiente.

`UNIQUE (profile_id, vdr_code)`. VDR existente não autoriza MAP, numerador ou
conversão. `AUTHORIZED` exige VDR racional válido, unidade e tupla completa de
evidência; `NO_NORMATIVE_VDR` exige evidência normativa completa da ausência e não
produz percentual.

### PortionAuthorization

Tabela `portion_authorizations`. Autoriza uma porção exata para uma formulação
imutável; não cria catálogo de categorias.

| Campo | Regra |
|---|---|
| `nutrition_calculation_id` | FK para a formulação snapshot |
| `reference_code`, `reference_version`, `reference_source` | Identificação rastreável |
| `technical_status` | `AUTHORIZED` ou `BLOCKED` |
| `portion_value` | `NUMERIC` positivo e finito |
| `portion_unit` | `g` ou `ml`, igual ao rendimento da origem |
| `household_measure_mode` | `PROVIDED`, `WAIVED` ou `NOT_APPLICABLE` |
| `household_measure` | Texto não vazio somente em `PROVIDED` |
| `justification` | Obrigatória em `WAIVED`/`NOT_APPLICABLE` |
| tupla `evidence_*` | Evidência, origem, versão, escopo, responsável e data |
| `created_at` | Registro técnico |

A autorização é imutável. `AUTHORIZED` exige tupla completa cujo escopo corresponda à
`NutritionCalculation`, referência, versão e porção. Ausência, bloqueio, fixture fora
de `testing` ou divergência com o request rejeita a criação antes de persistir qualquer
execução.

### RegulatoryEnergyApplicability

Tabela `regulatory_energy_applicabilities`. Vincula uma formulação, um perfil e uma
linha ENE.

Campos: `nutrition_calculation_id`, `profile_id`, `energy_component_id`, `status`,
`justification`, `source_reference` e tupla `evidence_*`.

`status` é `APPLICABLE`, `NOT_APPLICABLE` ou `UNKNOWN`. `APPLICABLE` exige evidência
compatível com produto, formulação, perfil e ENE. `NOT_APPLICABLE` exige justificativa e
tupla completa com o mesmo escopo. `UNKNOWN` bloqueia a matriz; ausência de linha é
tratada como `UNKNOWN`. Mudança de formulação exige outro registro.

## Agregado histórico derivado

### ServingDailyValueCalculation

Tabela `serving_daily_value_calculations`. Uma solicitação concluída.

| Campo | Obrigatório | Regra |
|---|---:|---|
| `id` | sim | PK |
| `nutrition_calculation_id` | sim | FK sem cascade destrutivo |
| `source_recipe_id` | sim | Cópia histórica do produto |
| `source_rules_version` | sim | Versão da Feature 003 |
| `source_yield_value` | sim | `NUMERIC` positivo copiado |
| `source_yield_unit` | sim | `g` ou `ml` |
| `portion_value` | sim | `NUMERIC` positivo, igual à autorização |
| `portion_unit` | sim | Igual à unidade do rendimento |
| `household_measure_mode` | sim | Os três modos aprovados |
| `household_measure` | condicional | Texto após normalização HTTP |
| `portion_justification` | condicional | Obrigatória para dispensa/inaplicabilidade |
| `portion_reference_code/version/source` | sim | Snapshot da autorização |
| `portion_reference_technical_status` | sim | Estado técnico histórico da autorização (`AUTHORIZED` ou `BLOCKED`) |
| `portion_evidence_*` e `portion_validated_on` | sim | Snapshot completo da evidência utilizada |
| `regulatory_profile_code/version/title/status` | sim | Snapshot do perfil considerado, inclusive quando bloqueado |
| `regulatory_profile_normative_source/version` | sim | Fonte e versão normativa consultadas |
| `regulatory_profile_consulted_on` | sim | Data de consulta da fonte normativa |
| `regulatory_profile_evidence_*` e `regulatory_profile_validated_on` | condicional | Evidência snapshotada quando existente |
| `energy_numerator/denominator` | não | Energia derivada ou subtotal |
| `energy_unit` | não | `kcal` quando houver valor |
| `energy_is_complete` | sim | Independente de presença numérica |
| `energy_availability` | sim | `AVAILABLE`, `PARTIAL` ou `UNAVAILABLE` |
| `rules_version` | sim | `serving-daily-values-v1` |
| `created_at` | sim | Sem `updated_at` |

Cada POST cria outra linha. Não há unique em `nutrition_calculation_id`.

### ServingDailyValueNutrient

Tabela `serving_daily_value_nutrients`, uma linha para cada nutriente do snapshot de
origem.

Campos:

- FKs para raiz e `nutrition_calculation_nutrient` imutável;
- cópia de `nutrient_id`, nome, unidade;
- razão total de origem nullable, `source_is_complete`,
  `source_has_missing_relation`;
- razão por porção nullable;
- `is_complete` e `has_missing_relation` copiados semanticamente da origem.

`UNIQUE (serving_daily_value_calculation_id, nutrient_id)`. Se a origem não possui
razão, porção é `null`; se possui zero, porção é `0/1`.

### ServingDailyValueNutrientState

Tabela `serving_daily_value_nutrient_states`.

Campos: FK para o nutriente derivado e `state`, restrito a `TRACE`, `NA`, `ABSENT` e
`REEVALUATION`. `UNIQUE (serving_daily_value_nutrient_id, state)`. Ausência de relação
fica em campo separado; `EXACT` não é estado qualitativo.

### ServingDailyValueRegulatoryResult

Tabela `serving_daily_value_regulatory_results`, uma linha por MAP considerada,
inclusive bloqueada ou sem `nutrient_id`.

Campos:

- `map_code`, nome do componente e snapshot do estado técnico;
- FK opcional para o valor nutricional por porção usado;
- `nutrient_id` e unidades snapshotadas;
- `map_source_reference` e tupla `map_evidence_*` snapshotadas;
- `conversion_status`, unidades, conversão racional e tupla
  `conversion_evidence_*` snapshotadas;
- razão regulatória por porção nullable;
- `is_complete`;
- `availability` (`AVAILABLE`, `PARTIAL`, `UNAVAILABLE`).

`UNIQUE (calculation_id, map_code)`. MAP bloqueada ou conversão não autorizada exige
valor regulatório `null`.

### ServingDailyValueEnergyComponent

Tabela `serving_daily_value_energy_components`, snapshot de cada ENE avaliada.

Campos: `ene_code`, nome, `map_code`, aplicabilidade, participação
(`INCLUDED`, `NON_APPLICABLE`, `BLOCKED`), fator racional/unidades, razão de entrada,
razão de contribuição, fontes, versões, estado técnico, tupla `ene_evidence_*`,
grupo/regra de sobreposição e tupla `applicability_evidence_*`; para não aplicabilidade,
também justificativa, fonte, validação, data e versão.

`UNIQUE (calculation_id, ene_code)`. `BLOCKED` não possui contribuição. Uma linha
`NOT_APPLICABLE` não é dado faltante, mas também não possui contribuição. `UNKNOWN`
bloqueia a energia na raiz.

### ServingDailyValuePercentage

Tabela `serving_daily_value_percentages`, uma linha por VDR considerada.

Campos: `vdr_code`, componente, `map_code`, FK opcional para numerador regulatório,
snapshot de VDR racional/unidade/fonte/versão/status, tupla `vdr_evidence_*`,
identificação e razão do numerador efetivamente usado, razão `%VD` nullable,
`is_complete` e `availability` (`AVAILABLE`, `PARTIAL`, `UNAVAILABLE`,
`NO_NORMATIVE_VDR`).

`UNIQUE (calculation_id, vdr_code)`. `%VD` só existe se todos os gates forem
autorizados. `NO_NORMATIVE_VDR` é diferente de `UNAVAILABLE`.

### ServingDailyValueResultCause

Tabela `serving_daily_value_result_causes`. Ledger estruturado de causas, separado de
estados nutricionais.

Campos: FK da raiz, `scope` (`REGULATORY_VALUE`, `ENERGY`, `DAILY_VALUE`),
`scope_key` (`MAP-*`, `ENERGY`, `VDR-*`), `category`, `code`, `detail` e referência
snapshot opcional.

Categorias mínimas: `REGULATORY_BLOCK`, `MISSING_SOURCE`, `UNKNOWN_APPLICABILITY`,
`UNIT_INCOMPATIBLE`, `UNAUTHORIZED_CONVERSION`, `MISSING_NUMERATOR`,
`NO_NORMATIVE_VDR`, `INCOMPLETE_NUTRITION`, `DOUBLE_COUNTING_RISK`.

`UNIQUE (calculation_id, scope, scope_key, code)`. Este ledger nunca substitui
`qualitative_states` ou `has_missing_relation`.

## Timestamps e configuração Eloquent

Registros versionados e raízes históricas possuem somente `created_at`; linhas filhas
usam o momento da raiz/perfil e não possuem timestamps próprios.

| Model | `created_at` | `updated_at` | Configuração Eloquent | Preenchimento |
|---|---:|---:|---|---|
| `RegulatoryProfile` | sim | não | `public const UPDATED_AT = null;` | Eloquent no insert |
| `RegulatoryNutrientMapping` | não | não | `public $timestamps = false;` | nenhum |
| `RegulatoryEnergyComponent` | não | não | `public $timestamps = false;` | nenhum |
| `RegulatoryDailyValue` | não | não | `public $timestamps = false;` | nenhum |
| `PortionAuthorization` | sim | não | `public const UPDATED_AT = null;` | Eloquent no insert |
| `RegulatoryEnergyApplicability` | não | não | `public $timestamps = false;` | nenhum |
| `ServingDailyValueCalculation` | sim | não | `public const UPDATED_AT = null;` | Eloquent no insert |
| `ServingDailyValueNutrient` | não | não | `public $timestamps = false;` | nenhum |
| `ServingDailyValueNutrientState` | não | não | `public $timestamps = false;` | nenhum |
| `ServingDailyValueRegulatoryResult` | não | não | `public $timestamps = false;` | nenhum |
| `ServingDailyValueEnergyComponent` | não | não | `public $timestamps = false;` | nenhum |
| `ServingDailyValuePercentage` | não | não | `public $timestamps = false;` | nenhum |
| `ServingDailyValueResultCause` | não | não | `public $timestamps = false;` | nenhum |

O timestamp não implementa imutabilidade. Triggers PostgreSQL bloqueiam `UPDATE` e
`DELETE` em todas as treze tabelas; nova versão ou execução sempre cria novos registros.

## Regras do agregado

1. Existe um resultado por porção para cada nutriente da origem, inclusive `null`.
2. O número não determina completude; `0/1` é presente e `null` é ausente.
3. A referência de porção bloqueada, sem evidência suficiente ou com fixture fora de
   `testing` impede criar a raiz; bloqueios MAP/ENE/VDR não impedem a raiz.
4. Perfil autorizado não promove dependências; MAP bloqueada, sem evidência ou com
   escopo incompatível afeta apenas seu resultado e dependentes.
5. ENE incompleta, bloqueada, desconhecida ou com risco de dupla contagem mantém
   energia e subtotal `null`.
6. Com matriz autorizada, incompletude nutricional pode produzir subtotal energético
   parcial; sem número disponível, energia permanece `null`.
7. VDR sem MAP, numerador, unidade e eventual conversão autorizados por status,
   evidência e escopo compatíveis mantém `%VD = null`.
8. Todos os registros são criados em uma transação `REPEATABLE READ`.
9. Triggers PostgreSQL impedem `UPDATE` e `DELETE` no agregado e nas referências
   técnicas versionadas.
10. Nova solicitação cria nova raiz; nenhuma autorização posterior altera históricos.

## Fluxos de estado

```text
referência de porção ausente/bloqueada/divergente
  -> request rejeitado, sem execução

porção autorizada
  -> valores por porção calculados e snapshotados
  -> cada gate MAP/VDR produz resultado ou indisponibilidade independente
  -> gate ENE completo produz energia; qualquer bloqueio estrutural produz null
  -> agregado persistido
  -> estado terminal imutável
```
