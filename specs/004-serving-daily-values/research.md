# Research: Serving and Daily Values

## 1. Origem imutável da Feature 003

**Decision**: A Feature 004 consome exclusivamente `NutritionCalculation` e seus
filhos snapshotados. `recipe_id` identifica o produto no domínio atual e
`nutrition_calculation_id` identifica inequivocamente a versão da formulação usada.
Não serão consultados Recipe, RecipeIngredient, Food, Nutrient ou FoodNutrient para
reconstruir valores.

**Rationale**: A Feature 003 já preserva rendimento, nutrientes, razões, estados,
ausência de relação e versão das regras. Reusar esse agregado satisfaz FR-001–FR-003,
FR-019 e FR-020 sem criar `Product` ou `Formulation`.

**Alternatives considered**: recalcular a receita; consultar dados atuais; criar novas
entidades Product/Formulation. Rejeitadas por quebrar imutabilidade ou ampliar escopo.

## 2. Aritmética racional exata

**Decision**: Reutilizar `NutritionCalculationArithmetic` e `Brick\Math\BigRational`
1.0.0, dependência direta do projeto. Entradas decimais permanecem strings; resultados
são pares reduzidos de numerador/denominador inteiros. Zero é `0/1`; ausência é
`null`.

**Rationale**: O padrão já está testado e persistido na Feature 003 e representa
dízimas sem `float`, escala fixa ou arredondamento.

**Alternatives considered**: `float`/`double`, `decimal:N`, BCMath com escala fixa,
decimal formatado. Rejeitadas por inexatidão, truncamento ou arredondamento.

## 3. Fórmulas e ordem

**Decision**:

- porção: `total_or_subtotal × portion_value ÷ source_yield_value`;
- componente energético: `regulatory_portion_value × authorized_factor`;
- energia: soma das contribuições autorizadas somente após o gate da matriz completa;
- `%VD`: `authorized_regulatory_numerator × 100 ÷ authorized_vdr`.

Cada etapa recebe e produz `BigRational`; não há materialização decimal intermediária.

**Rationale**: Aplica FR-005, FR-012, FR-015 e FR-016 e mantém a ordem funcional
aprovada.

**Alternatives considered**: usar a referência 100 g/ml em vez do total; usar energia
TACO como substituta; arredondar entre etapas. Rejeitadas pela Specification.

## 4. Registro técnico versionado e deny-by-default

**Decision**: Persistir perfis regulatórios imutáveis e versionados com linhas MAP,
ENE e VDR e seus estados técnicos. `AUTHORIZED` é condição necessária, mas nunca
suficiente isoladamente. Cada autorização efetiva exige simultaneamente estado técnico
autorizado, tupla de evidência completa, referência verificável, origem, versão,
responsável, data, escopo compatível com a regra e, quando aplicável, com o produto e a
formulação, ambiente compatível com o tipo de evidência e autorização de todos os gates
dependentes. A simples existência de uma tupla textual não comprova suficiência técnica;
o `ServingRegulatoryGateService` valida sua coerência semântica e aplica deny-by-default.

A tupla padronizada contém `evidence_reference`, `evidence_source`,
`evidence_version`, `evidence_scope`, `evidence_kind`, `validated_by` e
`validated_on`. `evidence_kind` é `DOCUMENTED` ou `TEST_FIXTURE`. O banco garante
presença conjunta, valores permitidos e exigência da tupla para estados autorizados; o
Service garante escopo, produto/formulação, dependências e ambiente. Evidência sem
estado autorizado, estado autorizado sem evidência, escopo incompatível ou qualquer
bloqueio dependente mantêm o resultado indisponível.

`TEST_FIXTURE` somente pode satisfazer gates quando a aplicação está no ambiente
`testing`. Fora dele, permanece bloqueada mesmo que a linha tenha status
`AUTHORIZED`. Fixtures são criadas apenas dentro dos testes, sem Seeder, endpoint ou
processo que possa promover a matriz real. Ausência de perfil, linha ou evidência
equivale a bloqueio, nunca a autorização. Não haverá endpoint administrativo nem carga
automática de valores tecnicamente pendentes.

**Rationale**: O servidor precisa verificar a autorização em vez de confiar em um
estado declarado pelo cliente. A estrutura permite rastrear versões sem promover o
registro `004-CQ002-2026-10-30.1`, cujas restrições permanecem vigentes.

**Alternatives considered**: confiar em flags do request; parsear Markdown em runtime;
hardcode de fatores/VDR; considerar o aceite administrativo como autorização técnica.
Rejeitadas por falta de integridade e violação da não invenção.

## 5. Autorização da porção

**Decision**: O POST recebe porção manual e `portion_authorization_id`. O registro
imutável de autorização é vinculado à `NutritionCalculation` de origem e contém a
referência, versão, fonte, estado técnico, valor/unidade autorizados e regra da medida
caseira, além da tupla de evidência técnica. O Service exige coincidência exata entre
request, autorização e formulação e somente aceita `AUTHORIZED` com evidência completa,
escopo compatível com a `NutritionCalculation` e ambiente permitido.

`household_measure_mode` será `PROVIDED`, `WAIVED` ou `NOT_APPLICABLE`. `PROVIDED`
exige texto não vazio; os outros modos exigem ausência e justificativa expressa na
autorização.

**Rationale**: Verifica por produto/formulação sem catálogo de categorias, densidade,
inferência ou interface administrativa.

**Alternatives considered**: aceitar qualquer referência textual; usar peso da
unidade; inferir por categoria; armazenar somente a medida. Rejeitadas por CQ-001.

## 6. Normalização HTTP

**Decision**: Manter `TrimStrings` e `ConvertEmptyStringsToNull` globais. A medida
caseira é preservada exatamente após essa normalização de transporte. Não há mudança
de caixa, correção semântica, conversão de unidade ou arredondamento.

**Rationale**: É o comportamento atual do Laravel e é coerente com a fronteira HTTP da
Feature 002. Strings vazias ou apenas espaços se tornam ausência antes do Form Request.

**Alternatives considered**: excluir o campo do trim ou preservar bytes externos.
Rejeitadas por criar exceção global sem requisito funcional e por permitir texto vazio
disfarçado.

## 7. Valores por porção e incompletude

**Decision**: Criar um snapshot para cada `NutritionCalculationNutrient`, inclusive
quando não há número. Copiar nome, unidade, razão de origem, `is_complete`,
`has_missing_relation` e todos os estados. Se existir razão, calcular porção; caso
contrário, manter `null`. A completude e os indicadores nunca são inferidos do valor.

**Rationale**: Preserva zero, subtotal, estados e ausência como dimensões distintas.

**Alternatives considered**: omitir nutrientes nulos; converter qualitativo em zero;
marcar subtotal numérico como completo. Rejeitadas por FR-009–FR-011.

## 8. Gate de energia

**Decision**: Calcular energia somente se o perfil ENE aplicável à origem estiver
completo, tecnicamente autorizado e com aplicabilidade definida para cada componente.
Perfil autorizado não autoriza automaticamente MAP ou ENE. Uma linha aplicável exige
MAP, fonte quantitativa, fator, unidade, evidência técnica e regra de sobreposição
autorizados. `APPLICABLE` exige evidência compatível com produto e formulação;
`NOT_APPLICABLE` exige justificativa e evidência específicas; `UNKNOWN` ou ausência de
registro bloqueiam a matriz. Qualquer risco de dupla contagem sem regra autorizada
bloqueia toda a energia, inclusive subtotal.

Após o gate: dados nutricionais completos produzem energia completa; dados
nutricionais incompletos produzem subtotal parcial somente a partir dos valores
numéricos disponíveis; nenhum número produz `null`.

**Rationale**: Distingue falta de autorização da incompletude nutricional e implementa
FR-014, FR-015 e SC-005/SC-010.

**Alternatives considered**: soma parcial antes de completar a matriz; ausência como
zero; ausência na TACO como não aplicabilidade; precedência implícita. Rejeitadas.

## 9. Gate de `%VD`

**Decision**: Avaliar cada resultado independentemente. `%VD` exige MAP autorizada com
evidência compatível, numerador regulatório racional, VDR autorizado com evidência
compatível, unidade e grandeza semântica compatíveis ou conversão explicitamente
autorizada com evidência própria. Ausência normativa de VDR é um estado próprio e
distinto de VDR pendente. Qualquer gate ausente mantém `%VD = null` com uma ou mais
causas.

**Rationale**: Um bloqueio não deve impedir resultados independentes nem ser
confundido com ausência normativa.

**Alternatives considered**: calcular com VDR candidato; aplicar conversão nominal;
usar valor TACO sem MAP; bloquear a execução inteira. Rejeitadas por FR-016/FR-017.

## 10. Conversões

**Decision**: Nenhuma conversão é autorizada pela implementação base. Cada MAP registra
`conversion_status` como `NOT_REQUIRED`, `AUTHORIZED` ou `BLOCKED`. `NOT_REQUIRED`
somente é válido quando identidade nutricional, unidade física e grandeza semântica são
compatíveis; igualdade textual de unidade não comprova equivalência nutricional.
Diferenças como niacina em `mg` versus `mg NE`, folato versus DFE e outras distinções
conceituais continuam bloqueadas sem decisão específica.

`AUTHORIZED` exige fator racional válido, unidades de origem/destino e tupla própria de
evidência técnica completa e compatível. A autorização da MAP não autoriza sua
conversão. `NOT_REQUIRED` também exige evidência própria de que identidade nutricional,
unidade física e grandeza semântica são compatíveis, mas não possui fator. `BLOCKED`,
evidência ausente/incompatível ou grandeza não comprovada bloqueiam o resultado antes da
aritmética. A estrutura pode receber no futuro uma conversão versionada, mas não a
infere por prefixo SI, igualdade textual ou conveniência.

**Rationale**: Preserva os bloqueios atuais, inclusive equivalências conceituais e a
conversão candidata de cobre.

**Alternatives considered**: tabela genérica de conversões automáticas; inferência por
prefixo SI. Rejeitadas por falta de autorização nutricional/normativa.

## 11. Snapshot e imutabilidade

**Decision**: Cada POST cria nova raiz e copia: produto, origem 003 e sua versão;
rendimento; porção, medida ou justificativa de dispensa; referência e evidência da
porção; perfil, fonte e versão normativa, data de consulta e evidência; MAP e sua
proveniência; autorização própria de conversão; ENE, aplicabilidade e respectivas
evidências; fatores; VDR, fonte, versão, unidade e evidência; numeradores efetivamente
utilizados; estados técnicos; valores completos/parciais; causas e versão
`serving-daily-values-v1`. Todas as tabelas históricas bloqueiam `UPDATE`/`DELETE` por
trigger PostgreSQL. O GET usa exclusivamente essas cópias e nunca consulta referências
regulatórias atuais para reconstruir ou promover o resultado.

**Rationale**: O GET histórico não pode depender de referências atuais. Nova
autorização ou nova regra exige nova execução.

**Alternatives considered**: somente FKs para registros atuais; atualização in-place;
JSON único sem constraints. Rejeitadas por fragilizar histórico e integridade.

## 12. Transação e concorrência

**Decision**: Carregar origem, autorização e perfil, calcular e persistir em uma única
transação PostgreSQL `REPEATABLE READ`. Qualquer falha reverte raiz e filhos.

**Rationale**: Evita misturar versões de referências em uma execução e segue o padrão
da Feature 003. A documentação oficial do Laravel 13 confirma rollback automático em
exceções de `DB::transaction`.

**Alternatives considered**: leituras externas à transação e gravações em etapas.
Rejeitadas por snapshot incoerente e resultado parcial.

## 13. Contrato HTTP

**Decision**:

- `POST /api/nutrition-calculations/{nutritionCalculation}/serving-daily-value-calculations`;
- `GET /api/serving-daily-value-calculations/{servingDailyValueCalculation}`.

Não há listagem, edição ou exclusão. O POST sempre cria nova execução. O GET usa
somente o snapshot. Razões são objetos de strings. Códigos HTTP específicos não são
fixados sem requisito aprovado; falhas usam o tratamento padrão aplicável do Laravel.

**Rationale**: Espelha o contrato da Feature 003 e o ciclo imutável aprovado.

**Alternatives considered**: resultado atual por Recipe; PUT/PATCH; resposta montada
das tabelas regulatórias atuais. Rejeitadas por violar histórico.

## 14. Persistência relacional

**Decision**: Usar tabelas normalizadas para perfis/linhas, autorização/aplicabilidade
e agregado histórico. Pares racionais recebem checks de presença conjunta, inteiros
finitos, denominador positivo e zero canônico. Models não aplicam casts `float`,
`double` ou `decimal:N`.

**Rationale**: PostgreSQL garante FKs, unicidade, estados permitidos e coerência mesmo
fora do Eloquent.

**Alternatives considered**: payload JSONB único ou campos decimais formatados.
Rejeitadas por constraints fracas e perda de exatidão.

## 15. Estratégia de testes

**Decision**: Usar PostgreSQL real e quatro camadas: aritmética unitária; gates
unitários/integração; persistência e imutabilidade; contrato HTTP e regressão. Fixtures
controladas representam perfis autorizados apenas nos testes, com
`evidence_kind=TEST_FIXTURE`, e são rejeitadas fora do ambiente `testing`. Os testes
cobrem status sem evidência, evidência sem status, escopo incompatível, dependência
bloqueada, conversão sem autorização própria, matriz incompleta e caminho integralmente
autorizado. O estado real bloqueado é testado separadamente e nunca promovido.

**Rationale**: Constraints, triggers, `NUMERIC` e isolamento não são fielmente
representados por SQLite ou mocks.

**Alternatives considered**: somente happy path, SQLite, mocks de banco. Rejeitadas.

## 16. Fronteira com rotulagem

**Decision**: Expor valores nutricionais por porção, energia derivada e `%VD` como
razões exatas anteriores à declaração final, com fontes e completude. Não expor campo
de “apto ao rótulo”. A feature futura deve aplicar arredondamento, quantidades não
significativas e, quando a norma exigir, recalcular energia/%VD a partir dos valores
declarados.

**Rationale**: Implementa DEC-004-009 e evita arredondamento prematuro.

**Alternatives considered**: arredondar a resposta da Feature 004 ou considerar seus
derivados como finais. Rejeitadas.

## 17. Caso “GELEIA DE AMORA”

**Decision**: Manter o cenário real condicionado ao mapeamento autorizado dos Foods.
Não criar Food sintético, selecionar limão por aproximação nem forçar `13,04 g` ou
`54,82 kcal`. Testes genéricos usam fixtures controladas.

**Rationale**: Preserva FR-021/FR-022 e o princípio de não invenção.

**Alternatives considered**: correspondência textual e valores forçados. Rejeitadas.
