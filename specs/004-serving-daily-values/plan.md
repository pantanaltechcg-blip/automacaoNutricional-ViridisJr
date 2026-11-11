# Implementation Plan: Serving and Daily Values

**Branch**: `004-serving-daily-values` | **Date**: 2026-11-10 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/004-serving-daily-values/spec.md`

## Summary

Implementar uma execução derivada imutável, vinculada a uma única
`NutritionCalculation`, que calcula valores nutricionais exatos por porção e mantém
energia derivada e `%VD` atrás de gates técnicos versionados. O desenho reutiliza
`Brick\Math\BigRational`, as razões `NUMERIC` da Feature 003, Controller fino,
Form Request, Service, Resource, transação PostgreSQL e proteção contra mutação.

As referências de porção e os perfis regulatórios são registros versionados e
imutáveis, sem interface administrativa nesta feature. Nenhuma linha `MAP`, `ENE` ou
`VDR` atualmente bloqueada é promovida pelo Plan. Quando um gate não é satisfeito, o
resultado dependente permanece `null` com causa regulatória estruturada; valores por
porção independentes continuam disponíveis. Arredondamento, quantidades não
significativas e valores finais do rótulo permanecem fora do escopo.

## Technical Context

**Language/Version**: PHP 8.4

**Primary Dependencies**: Laravel Framework 13.34.0; `brick/math` 1.0.0 como
dependência direta; Eloquent ORM; Laravel Form Requests e API Resources

**Storage**: PostgreSQL 17; razões exatas persistidas em pares `NUMERIC` sem
precisão/escala fixa; snapshots relacionais imutáveis

**Testing**: PHPUnit 12.5.38 por `php artisan test`; PostgreSQL real; Laravel Pint
1.32.1; `git diff --check`

**Target Platform**: API web Laravel executada em Windows no desenvolvimento e em
ambiente servidor compatível com PHP 8.4/PostgreSQL

**Project Type**: Serviço web monolítico Laravel, API JSON backend

**Performance Goals**: Uma execução realiza carga agrupada do snapshot da Feature
003 e dos registros regulatórios aplicáveis, sem N+1; uma transação atômica por
solicitação; resposta determinística ordenada por identificadores estáveis

**Constraints**: Aritmética racional exata; nenhum `float`/`double`, escala fixa,
truncamento ou arredondamento intermediário; zero `0/1` distinto de `null`; estados
qualitativos e ausência de relação preservados; matriz ENE deve estar completa e
tecnicamente autorizada antes de qualquer energia; `%VD` exige MAP, numerador, VDR,
unidade e eventual conversão autorizados. As Features 001–003 não sofrem mudança
funcional nem alteração de schema; helpers existentes podem receber somente extensões
genéricas, aditivas, retrocompatíveis e cobertas por testes

**Scale/Scope**: Todos os nutrientes presentes na execução 003; até 10 componentes
MAP, 15 linhas ENE e 43 linhas VDR na versão documental atual, sem codificar esses
limites como máximos; uma nova execução derivada por solicitação

## Constitution Check

*GATE inicial e pós-design: PASS.*

| Princípio/gate | Evidência do desenho |
|---|---|
| Não invenção | Dados ausentes, incompatíveis ou bloqueados permanecem `null` com causa; nenhum MAP, fator, VDR, conversão, fonte ou inaplicabilidade é inferido. |
| Stack definida | PHP 8.4, Laravel 13, PostgreSQL e PHPUnit; nenhuma dependência nova. |
| Separação de responsabilidades | Form Request valida transporte; Controller delega; Services aplicam gates e cálculo; Models persistem; Resource serializa; regras não ficam em Controller/Resource/Model. |
| Motor de cálculo | Reutiliza `BigRational`; cada etapa mantém numerador/denominador reduzidos e não arredonda. |
| Dados nutricionais e fontes | Consome somente o snapshot imutável da Feature 003; não altera TACO, Food, Nutrient ou FoodNutrient. |
| Regras regulatórias | Referências e estados técnicos são versionados; energia e `%VD` só são produzidos quando todos os gates dependentes estão autorizados. |
| Banco e testes | PostgreSQL real, constraints, FKs, uniques, checks, transação e triggers de imutabilidade; testes unitários, integração, contrato e regressão. |
| Autoridade administrativa e integridade técnica | Constitution 2.0.0 e `DEC-004-008` autorizam Plan; essa autorização não promove validação nutricional/normativa nem linhas bloqueadas. |
| Spec-driven development | O desenho deriva de FR-001–FR-023 e SC-001–SC-013; Tasks/Implement permanecem etapas posteriores. |

### Rechecagem após Phase 1

PASS. O modelo mantém referências técnicas separadas das execuções históricas,
preserva gates por resultado, registra causas de indisponibilidade e não introduz
arredondamento, rotulagem, autenticação, cadastro manual de alimentos ou edição de
histórico. As mudanças de banco propostas são necessárias para autorização técnica
versionada, aplicabilidade por formulação, snapshot imutável e rastreabilidade; não
substituem validação externa nem carregam valores regulatórios inventados.

## Architecture

### Fluxo principal

```text
POST + porção manual + portion_authorization_id
    -> StoreServingDailyValueCalculationRequest
    -> ServingDailyValueCalculationService (transação REPEATABLE READ)
       -> lê somente NutritionCalculation + filhos imutáveis
       -> valida PortionAuthorization para recipe/formulação
       -> calcula todos os valores por porção com BigRational
       -> ServingRegulatoryGateService avalia status + evidência + escopo + ambiente
       -> avalia perfil/MAP/ENE/VDR/conversão/aplicabilidade e dependências
       -> calcula apenas resultados tecnicamente autorizados
       -> persiste snapshot, resultados e causas em agregado imutável
    -> ServingDailyValueCalculationResource

GET execução derivada
    -> lê exclusivamente o agregado histórico
    -> retorna razões como strings e causas preservadas
```

### Serviços e responsabilidades

- `ServingDailyValueCalculationService`: orquestra carga, gates, cálculo, snapshot e
  persistência atômica. Não altera a execução 003 nem fontes atuais.
- `ServingRegulatoryGateService`: avalia autorização composta da referência de porção,
  perfil, correspondências, matriz ENE completa, aplicabilidade, sobreposição, VDR,
  unidades e conversões. Cada gate exige status autorizado, tupla de evidência completa,
  proveniência/versão, escopo compatível, vínculo ao produto/formulação, ambiente
  permitido e ausência de bloqueios dependentes. `AUTHORIZED` ou evidência textual
  isolados nunca liberam cálculo. O Service retorna decisões estruturadas; não faz
  aritmética nem promove status.
- `NutritionCalculationArithmetic` (existente): permanece helper técnico de
  `BigRational`. Pode receber apenas operações genéricas estritamente necessárias;
  nenhuma regra MAP/ENE/VDR/completude é movida para ele.
- `StoreServingDailyValueCalculationRequest`: valida formato e tipos de entrada. A
  aplicação dos gates técnicos e a comparação com o snapshot pertencem aos Services.
- `ServingDailyValueCalculationResource`: serializa snapshot já carregado,
  numeradores/denominadores como strings e coleções em ordem determinística.

### Estratégia de persistência

O banco ganha dois conjuntos explicitamente distintos:

1. referências técnicas versionadas e imutáveis (`RegulatoryProfile`, MAP, ENE, VDR,
   autorização de porção e aplicabilidade por formulação);
2. execução derivada imutável com cópia dos valores, estados, fatores, VDR,
   referências, decisões de aplicabilidade e causas efetivamente usados.

Todas as referências capazes de autorizar cálculo usam a convenção comum de evidência
definida em `data-model.md`. O PostgreSQL valida presença conjunta, enums e dependências
estruturais; o Service valida suficiência semântica, escopo e ambiente. Conversões têm
estado e evidência próprios. `TEST_FIXTURE` somente passa no ambiente `testing`, nunca é
carregada por Seeder e não pode promover o perfil documental real.

Nenhum endpoint de administração dessas referências pertence à Feature 004. A versão
atual do registro regulatório pode ser representada com estados bloqueados, mas não
será convertida em perfil tecnicamente autorizado. Testes usam fixtures controladas;
dados reais só podem ser carregados por processo posterior explicitamente autorizado.

## Project Structure

### Documentation (this feature)

```text
specs/004-serving-daily-values/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── serving-daily-values.md
├── checklists/
│   └── requirements.md
├── regulatory-decision-register.md
├── pending-viridis-validation.md
└── spec.md
```

### Source Code (repository root)

```text
app/
├── Http/
│   ├── Controllers/
│   │   └── ServingDailyValueCalculationController.php
│   ├── Requests/
│   │   └── StoreServingDailyValueCalculationRequest.php
│   └── Resources/
│       └── ServingDailyValueCalculationResource.php
├── Models/
│   ├── RegulatoryProfile.php
│   ├── RegulatoryNutrientMapping.php
│   ├── RegulatoryEnergyComponent.php
│   ├── RegulatoryDailyValue.php
│   ├── PortionAuthorization.php
│   ├── RegulatoryEnergyApplicability.php
│   ├── ServingDailyValueCalculation.php
│   ├── ServingDailyValueNutrient.php
│   ├── ServingDailyValueNutrientState.php
│   ├── ServingDailyValueRegulatoryResult.php
│   ├── ServingDailyValueEnergyComponent.php
│   ├── ServingDailyValuePercentage.php
│   └── ServingDailyValueResultCause.php
└── Services/
    ├── NutritionCalculationArithmetic.php   # existente; reutilizado
    ├── ServingDailyValueCalculationService.php
    └── ServingRegulatoryGateService.php

database/migrations/
├── *_create_regulatory_profiles_table.php
├── *_create_regulatory_nutrient_mappings_table.php
├── *_create_regulatory_energy_components_table.php
├── *_create_regulatory_daily_values_table.php
├── *_create_portion_authorizations_table.php
├── *_create_regulatory_energy_applicabilities_table.php
├── *_create_serving_daily_value_calculations_table.php
├── *_create_serving_daily_value_nutrients_table.php
├── *_create_serving_daily_value_nutrient_states_table.php
├── *_create_serving_daily_value_regulatory_results_table.php
├── *_create_serving_daily_value_energy_components_table.php
├── *_create_serving_daily_value_percentages_table.php
├── *_create_serving_daily_value_result_causes_table.php
├── *_protect_serving_daily_value_data_from_mutation.php
└── 2026_11_11_164338_add_portion_reference_technical_status_to_serving_daily_value_calculations_table.php

routes/
└── api.php

tests/
├── Feature/
│   ├── ServingDailyValueCalculationTest.php
│   ├── ServingDailyValuePersistenceTest.php
│   ├── ServingDailyValueRegulatoryGateTest.php
│   └── ServingDailyValueHistoricalSnapshotTest.php
└── Unit/
    └── ServingDailyValueArithmeticTest.php
```

**Structure Decision**: Manter o monólito Laravel e os padrões das Features 002 e
003. O novo agregado fica em `app/Models`, a lógica em Services, a fronteira HTTP em
Request/Controller/Resource e a persistência em migrations PostgreSQL. Não há novo
pacote, módulo raiz, frontend, job, fila, cache ou serviço externo.

## Testing Strategy

- **Unitários**: porção, fatores, soma e `%VD` com `BigRational`; dízimas; redução;
  zero `0/1`; `null`; rejeição de `float`/`double`.
- **Gates regulatórios**: data providers para MAP bloqueado, ENE incompleta,
  aplicabilidade desconhecida, fonte ausente, possível dupla contagem, VDR bloqueado,
  numerador inexistente, unidade incompatível, conversão não autorizada e
  não aplicabilidade comprovada; incluir status autorizado sem evidência, evidência sem
  status, escopo incompatível, fixture em ambiente não testing, perfil autorizado com
  componente dependente bloqueado e autorização completa consistente.
- **Integração PostgreSQL**: schema, checks dos pares racionais, FKs, uniques,
  transação, rollback, `REPEATABLE READ`, triggers contra `UPDATE`/`DELETE` e ausência
  de casts numéricos inexatos.
- **Contrato HTTP**: entrada decimal em string, unidade g/ml, medida obrigatória ou
  dispensa, referência autorizada, resposta determinística, razões em strings e
  causas separadas de estados nutricionais.
- **Histórico**: POST repetido gera IDs distintos; alterações posteriores nos
  dados atuais e novas versões das referências não mudam o GET antigo; tentativas de
  `UPDATE`/`DELETE` nas referências imutáveis são rejeitadas; a origem 003 nunca é
  atualizada. O GET histórico usa somente snapshots de proveniência e evidência.
- **Regressão**: toda a suíte existente das Features 001–003 continua passando.
- **Caso geleia de amora**: permanece condicionado; não criar Foods nem forçar valores.

## Risks and Dependencies

- Não foi fornecida uma autorização real de porção vinculada a uma formulação; o
  endpoint de produção deve permanecer bloqueado até existir esse registro técnico,
  enquanto os testes usam fixtures `TEST_FIXTURE` criadas somente no ambiente
  `testing`. Status isolado e fixture fora desse ambiente permanecem bloqueados.
- Não há hoje perfil real completo tecnicamente autorizado para energia; portanto a
  implementação inicial deve provar o bloqueio seguro e usar apenas fixtures
  controladas para o caminho autorizado.
- MAP e VDR bloqueados impedem somente o resultado dependente. A estrutura deve evitar
  que um bloqueio global esconda nutrientes independentes.
- A validação de sobreposição é conservadora: qualquer grupo potencialmente sobreposto
  sem regra versionada e autorizada bloqueia a energia inteira.
- `NUMERIC` sem escala preserva inteiros grandes, mas razões extremas podem consumir
  CPU/memória/armazenamento; nenhum limite funcional será inventado neste Plan.
- `TrimStrings` e `ConvertEmptyStringsToNull` permanecem ativos antes do Form Request.
  A medida caseira preservada é o valor resultante dessa normalização de transporte;
  não há correção de caixa, conteúdo ou domínio.
- A matriz atual não contém todas as fontes quantitativas. A infraestrutura não deve
  criar cadastro manual de alimentos ou dados complementares dentro desta feature.
- Referências tecnicamente autorizadas futuras devem chegar por processo controlado e
  versionado fora do contrato público desta feature.
- A existência física de uma tupla de evidência não comprova sua veracidade externa. O
  sistema preserva e valida integridade, escopo e dependências da decisão controlada;
  não inventa nem substitui a validação nutricional/normativa responsável.

## Complexity Tracking

Não há violação da Constitution a justificar. A quantidade de tabelas resulta da
separação entre referências versionadas e snapshots históricos, da cardinalidade
MAP/ENE/VDR e da necessidade de constraints e rastreabilidade; um JSON único ou
dependência de registros atuais enfraqueceria integridade, consulta e imutabilidade.
