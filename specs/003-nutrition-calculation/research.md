# Research: Nutrition Calculation

## 1. Representação decimal exata

**Decision**: Fazer toda a aritmética como números racionais exatos com
`Brick\Math\BigRational`. Entradas decimais são convertidas diretamente de strings;
resultados são reduzidos e persistidos como numerador inteiro e denominador inteiro
positivo, ambos em representação decimal textual/`NUMERIC` sem escala fixa.

**Rationale**: As fórmulas podem produzir dízimas, por exemplo divisão por um
rendimento que não divide exatamente o numerador. `float`/`double` perdem precisão;
`BigDecimal`, BCMath e divisão `NUMERIC` precisam escolher uma escala para materializar
uma dízima. Uma razão mantém o resultado matemático sem arredondar.

`composer show brick/math` confirmou a versão instalada 1.0.0. O pacote não está no
`require` nem no `require-dev` do `composer.json` raiz: é transitivo e atualmente é
requerido por `laravel/framework` 13.34.0 e `ramsey/uuid` 4.9.4. A equipe aprovou o
uso de `Brick\Math\BigRational`, mas o código da aplicação não dependerá
silenciosamente dessa transitividade. Antes de qualquer importação `Brick\Math`, uma
task explícita deverá declará-lo como dependência direta com constraint compatível
com a versão 1.0.0 já resolvida no lock e validar `composer.json`/`composer.lock`.
Este ajuste documental não executa `composer require` nem altera dependências.

**Alternatives considered**:

- `float`/`double`: rejeitado por erro binário e violação da precisão.
- BCMath com escala fixa: rejeitado porque a escala arbitrária trunca resultados.
- `BigDecimal`/PostgreSQL `NUMERIC` dividido: rejeitado para resultados periódicos.
- armazenar somente decimal formatado: rejeitado porque exige arredondamento.
- usar `brick/math` apenas enquanto transitivo: rejeitado porque uma alteração nas
  dependências de terceiros poderia remover uma API usada diretamente pela aplicação.

## 2. Fórmulas e ordem das etapas

**Decision**: Para cada contribuição `EXACT`, calcular exatamente
`quantity × amount_value ÷ 100`. Consolidar por nutriente somando somente essas
contribuições numéricas. Se existir subtotal numérico, completo ou incompleto,
calcular a referência como `subtotal × 100 ÷ yield_value`.

Quando o subtotal for incompleto, o valor de referência também será explicitamente
incompleto e preservará o conjunto de estados qualitativos e a indicação de ausência
de relação. Ele nunca será apresentado como composição completa. Sem qualquer
contribuição `EXACT`, não há total nem referência numérica.

**Rationale**: Mantém as três etapas da Constitution, aplica a decisão aprovada nesta
sessão e impede que a normalização apague a incompletude.

**Alternatives considered**:

- normalizar somente totais completos: rejeitado pela decisão aprovada.
- converter informação qualitativa em zero: rejeitado.
- arredondar entre etapas: rejeitado.

## 3. Processamento de todos os nutrientes

**Decision**: Carregar todos os `Nutrients` uma vez e os `FoodNutrients` dos
`food_id` distintos da receita em uma consulta agrupada. Indexar em memória por
`food_id` e `nutrient_id`, mantendo cada ocorrência de ingrediente separada, e
percorrer ocorrência × nutriente.

**Rationale**: Todos os nutrientes precisam ser processados, inclusive quando a
relação não existe. A matriz é inerente ao requisito; consultas N+1 não são.

**Alternatives considered**:

- iterar apenas relações existentes: rejeitado porque omite ausência de relação.
- produto cartesiano persistido na Base Nutricional: rejeitado porque altera a base.
- consulta por ingrediente/nutriente: rejeitado por N+1.

## 4. Energia da Base Nutricional

**Decision**: Processar energia existente como qualquer outro `Nutrient` da fonte.

**Rationale**: É a decisão CQ-002. O resultado representa o dado da fonte, não valor
energético por fatores nem valor regulatório final.

**Alternatives considered**: excluir energia ou calcular por fatores; ambas rejeitadas
para esta feature.

## 5. Estados qualitativos e ausência de relação

**Decision**: Uma contribuição contém exatamente uma das seguintes situações:

- `EXACT`: razão numérica, inclusive zero real;
- `TRACE`, `NA`, `ABSENT` ou `REEVALUATION`: estado preservado, sem valor numérico;
- relação inexistente: flag explícita de indisponibilidade, sem estado e sem valor.

No consolidado, preservar o conjunto sem precedência de todos os estados encontrados
e, separadamente, se houve ausência de relação. `complete` somente é verdadeiro
quando todas as contribuições são `EXACT`. Um subtotal numérico pode coexistir com
`complete = false`; sem `EXACT`, o valor numérico é nulo.

**Rationale**: Separa valor, estado, indisponibilidade e completude, que são dimensões
funcionais diferentes.

**Alternatives considered**: enum único misturando dimensões, precedência de estados,
estado genérico e zero; rejeitados pelas clarificações.

## 6. Unidade de referência

**Decision**: O snapshot preserva `yield_value` e `yield_unit`. `g` determina base
`100 g`; `ml`, base `100 ml`. Ingredientes continuam em gramas; não há densidade nem
conversão.

**Rationale**: Aplica CQ-002 sem inferência física.

**Alternatives considered**: converter unidades, inferir densidade ou usar soma dos
ingredientes; rejeitados.

## 7. Agregado persistido

**Decision**: Modelar cada execução em tabelas relacionais normalizadas:

- raiz da execução;
- snapshots das ocorrências de ingredientes;
- snapshot e resultado consolidado por nutriente;
- contribuição por ocorrência e nutriente;
- conjunto de estados qualitativos por nutriente consolidado.

**Rationale**: Constraints, unicidade, rastreabilidade e consulta são verificáveis no
PostgreSQL. Uma execução agrega exatamente as entradas e saídas aprovadas.

**Alternatives considered**:

- JSONB único: rejeitado por constraints e unicidade mais fracas.
- somente FKs para dados atuais: rejeitado porque não é snapshot.
- event sourcing ou cópia integral de todas as entidades: rejeitados por excesso.

## 8. Conteúdo do snapshot

**Decision**: Preservar na raiz `recipe_id`, rendimento e unidade; por ocorrência,
`recipe_ingredient_id`, posição, quantidade/unidade, `food_id`, `source` e
`source_code`; por nutriente, `nutrient_id`, nome e unidade; por contribuição, a
identidade histórica do `FoodNutrient` quando existente, seu `amount_value`/estado ou
a ausência de relação, além do resultado proporcional.

Os identificadores de origem são valores históricos copiados, não dependências para
reconstruir a execução. A leitura usa somente o snapshot.

**Rationale**: É o mínimo aprovado para reproduzir e explicar o cálculo sem copiar
campos que não participaram dele.

**Alternatives considered**: guardar apenas IDs vivos ou copiar registros completos;
rejeitados, respectivamente, por mutabilidade e excesso.

## 9. Versão das regras

**Decision**: Usar o identificador opaco e versionado em código
`nutrition-calculation-v1`, copiado para cada execução.

**Rationale**: É determinístico, explícito e muda somente quando um conjunto de
regras muda. Não depende do ambiente de implantação.

**Alternatives considered**: commit Git, timestamp, versão global da aplicação e
valor configurável por ambiente; rejeitados por não identificarem de modo estável o
conjunto funcional de regras.

## 10. Imutabilidade

**Decision**: Expor somente criação e consulta. O Service não terá atualização ou
exclusão. Migrations instalarão proteção PostgreSQL contra `UPDATE`/`DELETE` nas
tabelas do agregado após inserção. Relações internas usam FKs e exclusão em cascata
apenas como integridade estrutural, não como operação funcional exposta.

**Rationale**: A imutabilidade deve sobreviver a caminhos que contornem Eloquent.

**Alternatives considered**: disciplina apenas no Service ou eventos Eloquent;
rejeitados por poderem ser contornados por Query Builder/SQL.

## 11. Transação e snapshot coerente

**Decision**: Carregar entradas, calcular e persistir toda a execução em uma única
transação PostgreSQL com isolamento `REPEATABLE READ`. Qualquer falha reverte raiz e
filhos.

**Rationale**: Impede mistura entre versões de Recipe/Base alteradas durante uma
execução e evita resultados parciais.

**Alternatives considered**: leitura fora da transação ou transações separadas;
rejeitadas por snapshot incoerente/atomicidade insuficiente.

## 12. Contrato HTTP

**Decision**:

- `POST /api/recipes/{recipe}/nutrition-calculations`: cria uma execução sem payload
  funcional;
- `GET /api/nutrition-calculations/{calculation}`: consulta a execução preservada.

Não existem edição, exclusão, listagem, filtro ou paginação nesta feature. O contrato
não fixa status HTTP não aprovados. Razões são expostas como strings de numerador e
denominador; nunca como número JSON de ponto flutuante.

**Rationale**: O POST expressa a nova execução independente; o GET não depende do
estado atual da Recipe.

**Alternatives considered**: resultado apenas no POST, endpoint na Recipe para o
“resultado atual” ou atualização de resultado; rejeitados por violar persistência ou
independência das execuções.

## 13. Estratégia de testes

**Decision**: Usar PostgreSQL e `DatabaseTransactions`, fixtures controladas e três
camadas: aritmética racional; integração do agregado/constraints/transação; contrato
HTTP. Cobrir integralmente as combinações aprovadas de estados e ausência, zero
`EXACT`, repetições do mesmo Food, todos os nutrientes, rendimento g/ml, subtotal
incompleto normalizado, reprodutibilidade, nova execução e imutabilidade histórica.

**Rationale**: Os comportamentos críticos incluem aritmética e garantias específicas
do PostgreSQL; SQLite e mocks não os reproduzem.

**Alternatives considered**: apenas exemplos felizes, SQLite e mocks de persistência;
rejeitados.

## 14. Caso “GELEIA DE AMORA”

**Decision**: Manter o caso como validação obrigatória, mas não fabricar sua execução
enquanto os alimentos da fonte não forem inequivocamente localizáveis. A Base TACO
processada atual não contém Food aprovado para “amora”; contém `Açúcar, cristal`
(`source_code` 492) e mais de uma opção de limão, sem decisão que identifique qual é
o “suco de limão” do caso. O teste real não pode ser marcado como PASS até a
Viridis/equipe responsável aprovar o mapeamento. Esse bloqueio não impede os testes
genéricos do motor com fixtures controladas.

**Rationale**: Selecionar substituto, criar Food sintético ou atribuir composição
violaria a não invenção. O quickstart documenta a verificação reproduzível e o
bloqueio. Quando o material Viridis fornecer a identificação/rastreabilidade da
amora e do limão, o teste poderá comparar carboidratos com aproximadamente 65,21 g
por 100 g sem alterar a fórmula.

**Alternatives considered**: alimento sintético, correspondência por nome aproximado
ou escolha arbitrária entre limões; rejeitados.

## 15. Limites funcionais

**Decision**: Não implementar criação/edição de Recipe, importação/busca, conversão,
porção, %VD, energia por fatores, arredondamento, açúcares adicionados, autenticação,
rotulagem, visualização, exportação, cache, jobs ou filas.

**Rationale**: São exclusões expressas da specification.

**Alternatives considered**: nenhuma; expandiriam o escopo.

## Observações documentais

- A frase antiga do Edge Case dizendo que combinações sem `EXACT` “ainda dependem de
  CQ-001” foi removida da Specification; FR-005 já resolve esses casos.
- A decisão sobre subtotal incompleto está registrada na Clarification, no cenário de
  aceitação da US3, nos Edge Cases, no FR-007 e no SC-004 da Specification.
