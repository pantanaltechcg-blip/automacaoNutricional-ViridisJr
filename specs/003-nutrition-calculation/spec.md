# Feature Specification: Nutrition Calculation

**Feature Branch**: `003-nutrition-calculation`

**Created**: 2026-10-13

**Status**: Draft

**Input**: User description: "Calcular a composição nutricional de uma receita persistida a partir de seus ingredientes e dos dados da Base Nutricional, consolidando as contribuições e normalizando-as pelo rendimento, sem antecipar regras de estados qualitativos, energia, persistência, arredondamento ou rotulagem ainda não aprovadas."

## Clarifications

### Session 2026-10-13

- Q: Quando um `FoodNutrient` possui estado `TRACE`, qual deve ser o resultado da
  contribuição proporcional desse nutriente para o ingrediente? → A: Produzir uma
  contribuição qualitativa `TRACE`, sem valor numérico; `TRACE` não pode ser
  convertido para zero. Sua consolidação segue as decisões posteriores registradas
  nesta mesma sessão.
- Q: Quando um `FoodNutrient` possui estado `NA`, qual deve ser o resultado da
  contribuição proporcional desse nutriente para o ingrediente? → A: Produzir uma
  contribuição qualitativa `NA`, sem valor numérico; `NA` não pode ser convertido
  para zero. Sua consolidação segue as decisões posteriores registradas nesta mesma
  sessão.
- Q: Quando um `FoodNutrient` possui estado `ABSENT`, qual deve ser o resultado da
  contribuição proporcional desse nutriente para o ingrediente? → A: Produzir uma
  contribuição qualitativa `ABSENT`, sem valor numérico, sem conversão para zero e
  sem omissão silenciosa. Sua consolidação segue as decisões posteriores registradas
  nesta mesma sessão.
- Q: Quando um `FoodNutrient` possui estado `REEVALUATION`, qual deve ser o resultado
  da contribuição proporcional desse nutriente para o ingrediente? → A: Produzir uma
  contribuição qualitativa `REEVALUATION`, sem valor numérico, sem conversão para
  zero e sem omissão silenciosa. Sua consolidação segue as decisões posteriores
  registradas nesta mesma sessão.
- Q: Quando não existe qualquer relação `FoodNutrient` entre um alimento e
  determinado nutriente, qual deve ser o resultado para esse nutriente na
  contribuição do ingrediente? → A: Produzir uma indicação qualitativa explícita de
  dado nutricional não disponível por ausência de relação, sem valor numérico e
  distinta de `ABSENT`, `TRACE`, `NA` e `REEVALUATION`. Sua consolidação segue as
  decisões posteriores registradas nesta mesma sessão.
- Q: Quando um mesmo nutriente possui contribuições `EXACT` e `TRACE` em ingredientes
  diferentes, qual deve ser o resultado consolidado da receita? → A: Somar somente
  as contribuições `EXACT` e preservar separadamente que o consolidado contém
  `TRACE` e está incompleto. O subtotal não pode ser interpretado nem apresentado
  como composição nutricional completa daquele nutriente.
- Q: Quando um mesmo nutriente possui contribuições `EXACT` e `NA` em ingredientes
  diferentes, qual deve ser o resultado consolidado da receita? → A: Somar somente
  as contribuições `EXACT` e preservar separadamente que o consolidado contém `NA`
  e está incompleto. O subtotal representa apenas as contribuições conhecidas
  numericamente e não pode ser apresentado como composição completa daquele
  nutriente.
- Q: Quando um mesmo nutriente possui contribuições `EXACT` e `ABSENT` em
  ingredientes diferentes, qual deve ser o resultado consolidado da receita? → A:
  Somar somente as contribuições `EXACT` e preservar separadamente que o consolidado
  contém `ABSENT` e está incompleto. O subtotal representa apenas a parcela conhecida
  e não pode ser tratado como composição completa daquele nutriente.
- Q: Quando um mesmo nutriente possui contribuições `EXACT` e `REEVALUATION` em
  ingredientes diferentes, qual deve ser o resultado consolidado da receita? → A:
  Somar somente as contribuições `EXACT` e preservar separadamente que o consolidado
  contém `REEVALUATION` e está incompleto. O subtotal representa somente a parcela
  numericamente conhecida e não pode ser tratado como composição completa daquele
  nutriente.
- Q: Quando um mesmo nutriente possui contribuições `EXACT` e pelo menos um
  ingrediente sem relação `FoodNutrient`, qual deve ser o resultado consolidado da
  receita? → A: Somar somente as contribuições `EXACT` e preservar separadamente a
  indisponibilidade por ausência de relação, identificando o consolidado como
  incompleto. Essa indisponibilidade permanece distinta de `TRACE`, `NA`, `ABSENT` e
  `REEVALUATION`, e o subtotal não pode ser tratado como composição completa.
- Q: Quando todas as contribuições de um nutriente possuem o mesmo estado
  qualitativo — somente `TRACE`, somente `NA`, somente `ABSENT` ou somente
  `REEVALUATION` — qual deve ser o resultado consolidado? → A: Preservar no
  consolidado o mesmo estado qualitativo presente em todas as contribuições, sem
  valor numérico; o resultado permanece não numérico, explicitamente incompleto e não
  pode ser interpretado como composição nutricional completa.
- Q: Quando um nutriente possui dois ou mais estados qualitativos diferentes e
  nenhuma contribuição `EXACT`, qual deve ser o resultado consolidado? → A: Preservar
  no consolidado o conjunto completo de estados qualitativos presentes, sem aplicar
  precedência entre eles e sem produzir valor numérico; o resultado permanece
  explicitamente incompleto.
- Q: Quando não existe nenhuma contribuição `EXACT` e coexistem estados qualitativos
  com ausência de relação `FoodNutrient`, qual deve ser o resultado consolidado? → A:
  Preservar separadamente o conjunto completo de estados qualitativos presentes e a
  indisponibilidade por ausência de relação, sem precedência e sem valor numérico; o
  resultado permanece explicitamente incompleto e a ausência de relação continua
  distinta de `TRACE`, `NA`, `ABSENT` e `REEVALUATION`.
- Q: Quando todos os ingredientes estão sem relação `FoodNutrient` para um mesmo
  nutriente, qual deve ser o resultado consolidado? → A: Produzir uma indicação
  consolidada de dado indisponível por ausência de relação, sem valor numérico; o
  resultado permanece explicitamente incompleto, distinto de `ABSENT`, `TRACE`, `NA`
  e `REEVALUATION` e não pode ser reclassificado como qualquer desses estados.
- Q: Desconsiderando por enquanto o tratamento específico da energia, quais
  nutrientes devem integrar o cálculo desta feature? → A: Processar todos os
  `Nutrients` existentes na Base Nutricional, aplicando as regras aprovadas para
  `EXACT`, estados qualitativos e ausência de relação. Isso não determina quais
  nutrientes serão exibidos em rótulo ou utilizados em `%VD`; o tratamento da
  energia é definido na resposta seguinte desta mesma sessão.
- Q: Como a energia já armazenada na Base Nutricional deve participar do cálculo
  desta feature? → A: Tratar a energia existente na Base Nutricional como qualquer
  outro `Nutrient`, aplicando cálculo proporcional, consolidação e as regras aprovadas
  de CQ-001. Esse resultado representa exclusivamente o dado energético da fonte e
  não define nem substitui o futuro cálculo por fatores de conversão ou o valor que
  será utilizado na rotulagem final.
- Q: Como a unidade do rendimento persistido deve determinar a referência final da
  composição nesta feature? → A: `yield_unit = g` produz referência de 100 g e
  `yield_unit = ml` produz referência de 100 ml. O valor do rendimento é usado
  exatamente como persistido, as quantidades dos ingredientes permanecem em gramas
  e nenhuma conversão entre massa e volume ou densidade é inferida.
- Q: Quando o consolidado contém ao menos uma contribuição `EXACT` e também estados
  qualitativos e/ou ausência de relação `FoodNutrient`, como o subtotal incompleto
  participa da normalização pelo rendimento? → A: Somar somente as contribuições
  `EXACT`, preservar todos os estados qualitativos e, separadamente, a ausência de
  relação, marcar o consolidado como incompleto e calcular a referência por
  `subtotal × 100 ÷ rendimento persistido`. A referência numérica resultante também
  permanece incompleta, preserva as mesmas indicações e nunca pode ser apresentada
  como composição nutricional completa.
- Q: Depois de produzido, o resultado do cálculo deve existir apenas durante a
  solicitação ou deve ser preservado para consulta e reprodução posteriores? → A:
  Preservar o resultado produzido para permitir consulta e servir de base à
  reprodutibilidade e auditoria. Essa decisão não define histórico avançado, snapshot
  das entradas, versionamento ou comportamento de um novo cálculo.
- Q: Cada resultado preservado deve permanecer vinculado a um snapshot imutável das
  entradas utilizadas naquele cálculo? → A: Sim. O snapshot permanece associado ao
  resultado e não é alterado por modificações posteriores na receita, em seus
  ingredientes ou na Base Nutricional. Essa decisão não implica versionamento global
  da aplicação nem histórico avançado de todas as entidades.
- Q: Quais entradas devem compor o snapshot imutável associado a cada resultado? → A:
  Preservar a identificação da receita; rendimento e unidade; cada ocorrência de
  ingrediente, sua ordem, quantidade e identificação/rastreabilidade do alimento; e,
  para cada nutriente processado, sua identificação, nome, unidade e o valor `EXACT`,
  estado qualitativo ou ausência de relação utilizado. O snapshot contém somente os
  dados que participaram do cálculo e independe dos valores atuais da receita ou da
  Base Nutricional; a exigência funcional e o formato técnico da versão das regras
  são definidos, respectivamente, nas respostas posteriores e no Plan/Research.
- Q: Cada resultado preservado deve identificar explicitamente a versão das regras ou
  do motor de cálculo que o produziu? → A: Sim. Todo resultado preservado possui um
  identificador explícito da versão utilizada, capaz de distinguir execuções produzidas
  por conjuntos de regras diferentes e permanentemente associado ao resultado. O
  formato técnico dessa identificação será definido no Plan/Research.
- Q: O que deve acontecer com um resultado e seu snapshot já preservados quando a
  `Recipe` correspondente é alterada posteriormente? → A: O resultado antigo e seu
  snapshot permanecem imutáveis e consultáveis, representando exatamente as entradas
  e regras utilizadas naquela execução; a `Recipe` alterada passa a possuir um estado
  atual distinto. Alterações posteriores na receita, em seus ingredientes ou na Base
  Nutricional não modificam retroativamente essa execução.
- Q: Quando um novo cálculo é solicitado para uma `Recipe` que já possui resultado
  preservado, o novo cálculo deve criar outro resultado ou substituir o anterior? → A:
  Criar um novo resultado preservado com snapshot imutável e identificação da versão
  próprios, mantendo todos os resultados anteriores imutáveis e consultáveis. Cada
  solicitação representa uma nova execução independente e não substitui nem altera
  execuções anteriores.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Calcular contribuição de cada ingrediente (Priority: P1)

Como responsável técnico pelo produto, quero obter a contribuição proporcional de
cada ingrediente para cada componente nutricional aprovado, para compreender como a
quantidade utilizada participa da composição da receita.

**Why this priority**: A contribuição por ingrediente é a primeira etapa do motor de
cálculo e fornece a base verificável para qualquer consolidação posterior.

**Independent Test**: Pode ser testada com uma receita válida contendo um ingrediente
cujo alimento possua um valor numérico `EXACT`, comparando a contribuição produzida
com `quantidade utilizada × valor por 100 g ÷ 100`, sem arredondamento.

**Acceptance Scenarios**:

1. **Given** uma receita válida com 200 g de um ingrediente cujo alimento possui
   12,5 g de determinado nutriente por 100 g, **When** a contribuição desse
   ingrediente é calculada, **Then** o resultado para esse nutriente corresponde a
   `200 × 12,5 ÷ 100` e mantém a unidade definida para o nutriente.
2. **Given** um ingrediente associado a múltiplos componentes nutricionais
   numéricos, **When** suas contribuições são calculadas, **Then** cada componente é
   calculado separadamente com base em seu próprio valor por 100 g.
3. **Given** um valor `EXACT` igual a zero, **When** sua contribuição é calculada,
   **Then** ele permanece um zero numérico real e não é reclassificado como estado
   qualitativo.
4. **Given** um dado nutricional com estado `TRACE`, **When** sua contribuição é
   calculada para o ingrediente, **Then** o resultado permanece qualitativo `TRACE`,
   sem valor numérico e sem conversão para zero.
5. **Given** um dado nutricional com estado `NA`, **When** sua contribuição é
   calculada para o ingrediente, **Then** o resultado permanece qualitativo `NA`,
   sem valor numérico e sem conversão para zero.
6. **Given** um dado nutricional com estado `ABSENT`, **When** sua contribuição é
   calculada para o ingrediente, **Then** o resultado permanece qualitativo `ABSENT`,
   sem valor numérico, sem conversão para zero e sem omissão silenciosa.
7. **Given** um dado nutricional com estado `REEVALUATION`, **When** sua contribuição
   é calculada para o ingrediente, **Then** o resultado permanece qualitativo
   `REEVALUATION`, sem valor numérico, sem conversão para zero e sem omissão
   silenciosa.
8. **Given** que não existe relação `FoodNutrient` entre o alimento de um ingrediente
   e determinado nutriente, **When** sua contribuição é avaliada, **Then** o resultado
   indica explicitamente que o dado nutricional não está disponível por ausência de
   relação, sem valor numérico e sem classificá-lo como `ABSENT`, `TRACE`, `NA` ou
   `REEVALUATION`.
9. **Given** os nutrientes existentes na Base Nutricional, **When** a composição da
   receita é processada, **Then** todos esses nutrientes são considerados conforme as
   regras aprovadas para valores `EXACT`, estados qualitativos e ausência de relação,
   sem determinar sua futura exibição em rótulo ou utilização em `%VD` e sem antecipar
   o tratamento específico da energia.
10. **Given** energia existente como `Nutrient` na Base Nutricional, **When** a
    composição da receita é processada, **Then** sua contribuição e consolidação
    seguem as mesmas regras dos demais nutrientes e o resultado permanece identificado
    exclusivamente como dado energético da fonte, sem representar cálculo por fatores
    de conversão ou valor final de rotulagem.

---

### User Story 2 - Consolidar composição da receita (Priority: P2)

Como responsável técnico pelo produto, quero consolidar as contribuições dos
ingredientes por componente nutricional, para obter o total de cada nutriente na
receita completa sem misturar componentes diferentes.

**Why this priority**: A composição total da receita depende das contribuições da
primeira etapa e é necessária antes da normalização pelo rendimento.

**Independent Test**: Pode ser testada com uma receita contendo dois ou mais
ingredientes com valores `EXACT` para o mesmo nutriente, confirmando que o total é a
soma das contribuições desse nutriente e que nutrientes distintos permanecem
separados.

**Acceptance Scenarios**:

1. **Given** dois ingredientes com contribuições numéricas para o mesmo nutriente,
   **When** a receita é consolidada, **Then** o total desse nutriente corresponde à
   soma integral das duas contribuições.
2. **Given** contribuições de nutrientes diferentes, **When** a receita é
   consolidada, **Then** cada total permanece associado ao nutriente e à unidade
   correspondentes, sem combinação entre componentes.
3. **Given** contribuições `EXACT` e `TRACE` para o mesmo nutriente, **When** a receita
   é consolidada, **Then** somente as contribuições `EXACT` compõem o subtotal
   numérico e o resultado indica explicitamente que ele está incompleto devido à
   presença de `TRACE`, sem apresentá-lo como composição completa.
4. **Given** contribuições `EXACT` e `NA` para o mesmo nutriente, **When** a receita é
   consolidada, **Then** somente as contribuições `EXACT` compõem o subtotal numérico
   e o resultado preserva explicitamente `NA`, indicando que representa apenas as
   contribuições conhecidas numericamente e não a composição completa.
5. **Given** contribuições `EXACT` e `ABSENT` para o mesmo nutriente, **When** a
   receita é consolidada, **Then** somente as contribuições `EXACT` compõem o subtotal
   numérico e o resultado preserva explicitamente `ABSENT`, indicando que representa
   apenas a parcela conhecida e não a composição completa.
6. **Given** contribuições `EXACT` e `REEVALUATION` para o mesmo nutriente, **When** a
   receita é consolidada, **Then** somente as contribuições `EXACT` compõem o subtotal
   numérico e o resultado preserva explicitamente `REEVALUATION`, indicando que
   representa somente a parcela numericamente conhecida e não a composição completa.
7. **Given** contribuições `EXACT` para um nutriente e pelo menos um ingrediente sem
   relação `FoodNutrient` para esse mesmo nutriente, **When** a receita é consolidada,
   **Then** somente as contribuições `EXACT` compõem o subtotal numérico e o resultado
   preserva explicitamente a indisponibilidade por ausência de relação, distinta dos
   estados qualitativos e identificada como composição incompleta.
8. **Given** que todas as contribuições de um nutriente possuem o mesmo estado
   qualitativo, **When** a receita é consolidada, **Then** o resultado preserva esse
   estado específico, não possui valor numérico e é identificado explicitamente como
   incompleto, sem ser interpretado como composição nutricional completa.
9. **Given** dois ou mais estados qualitativos diferentes para o mesmo nutriente e
   nenhuma contribuição `EXACT`, **When** a receita é consolidada, **Then** o resultado
   preserva o conjunto completo de estados presentes, sem precedência, sem valor
   numérico e com identificação explícita de que está incompleto.
10. **Given** estados qualitativos e ausência de relação `FoodNutrient` para o mesmo
    nutriente, sem contribuição `EXACT`, **When** a receita é consolidada, **Then** o
    resultado preserva separadamente todos os estados presentes e a indisponibilidade
    por ausência de relação, sem precedência, sem valor numérico e com identificação
    explícita de que está incompleto.
11. **Given** que nenhum ingrediente possui relação `FoodNutrient` para determinado
    nutriente, **When** a receita é consolidada, **Then** o resultado indica
    explicitamente indisponibilidade por ausência de relação, sem valor numérico,
    identificado como incompleto e sem reclassificação como `ABSENT`, `TRACE`, `NA`
    ou `REEVALUATION`.

---

### User Story 3 - Normalizar pelo rendimento informado (Priority: P3)

Como responsável técnico pelo produto, quero obter a composição da receita na
referência final de 100 g ou 100 ml a partir do rendimento informado, para comparar
o produto em uma base comum sem recalcular ou inferir seu rendimento.

**Why this priority**: A referência final depende dos totais consolidados e é a
saída nutricional desta feature antes de porção, percentuais, arredondamento e
rotulagem.

**Independent Test**: Pode ser testada usando um total conhecido e o rendimento
persistido, comparando cada valor de referência com
`total do nutriente × 100 ÷ rendimento total`, confirmando que o rendimento não foi
substituído pela soma dos ingredientes e que sua unidade determina a referência.

**Acceptance Scenarios**:

1. **Given** uma receita válida com total numérico conhecido para um nutriente e
   rendimento persistido, **When** o valor de referência é calculado, **Then** o
   resultado corresponde a `total do nutriente × 100 ÷ rendimento total`.
2. **Given** um rendimento diferente da soma das quantidades dos ingredientes,
   **When** a normalização é realizada, **Then** o sistema utiliza literalmente o
   rendimento persistido.
3. **Given** ingredientes medidos em gramas e rendimento registrado em `ml`,
   **When** a normalização é solicitada, **Then** nenhuma densidade ou conversão
   entre massa e volume é inventada e a referência final corresponde a 100 ml.
4. **Given** rendimento registrado em `g`, **When** a normalização é realizada,
   **Then** a referência final corresponde a 100 g e utiliza o rendimento exatamente
   como persistido.
5. **Given** um consolidado com subtotal numérico formado por contribuições `EXACT`
   e também com estados qualitativos e/ou ausência de relação `FoodNutrient`,
   **When** a normalização é realizada, **Then** o sistema calcula
   `subtotal × 100 ÷ rendimento persistido`, marca o valor de referência como
   incompleto, preserva os mesmos estados e a mesma indicação de ausência de relação
   e não o apresenta como composição nutricional completa.

---

### User Story 4 - Reproduzir e auditar o cálculo (Priority: P4)

Como responsável pela validação técnica, quero reproduzir um cálculo nutricional e
identificar as entradas e regras que produziram seu resultado, para comparar
execuções e auditar divergências.

**Why this priority**: A Constitution exige cálculo determinístico e testável, e a
preservação do resultado, das entradas e da versão permite explicar e comparar cada
execução independentemente dos dados atuais.

**Independent Test**: Pode ser testada repetindo o cálculo com as mesmas entradas e
regras, verificando resultados idênticos e confirmando que cada execução preserva seu
próprio snapshot e identificação da versão sem alterar execuções anteriores.

**Acceptance Scenarios**:

1. **Given** as mesmas entradas nutricionais, quantidades, rendimento e regras
   aprovadas, **When** o cálculo é repetido, **Then** todas as contribuições, totais e
   valores de referência correspondem aos resultados anteriores.
2. **Given** uma receita alterada após um cálculo, **When** o resultado anterior é
   consultado, **Then** ele e seu snapshot permanecem imutáveis e continuam
   representando as entradas e regras da execução original.
3. **Given** um cálculo concluído, **When** sua solicitação termina, **Then** o
   resultado produzido continua disponível para consulta e como base para
   reprodutibilidade e auditoria, sem que isso determine por si só histórico avançado,
   snapshot ou política de novo cálculo.
4. **Given** um resultado preservado com seu snapshot de entradas, **When** a receita,
   seus ingredientes ou a Base Nutricional são modificados posteriormente, **Then** o
   snapshot associado ao resultado permanece imutável.
5. **Given** um resultado preservado, **When** seu cálculo precisa ser reproduzido ou
   explicado, **Then** o snapshot fornece a identificação da receita, seu rendimento e
   unidade, cada ocorrência ordenada de ingrediente com quantidade e rastreabilidade do
   alimento, e a identificação, nome, unidade e dado efetivamente utilizado para cada
   nutriente processado, sem depender dos valores atuais nem copiar campos alheios ao
   cálculo.
6. **Given** dois resultados produzidos por conjuntos diferentes de regras, **When**
   suas execuções são consultadas ou comparadas, **Then** cada resultado mantém um
   identificador explícito da versão das regras ou do motor que permite distingui-los.
7. **Given** um resultado e seu snapshot já preservados, **When** a receita, seus
   ingredientes ou a Base Nutricional são alterados, **Then** o resultado antigo e seu
   snapshot permanecem imutáveis e consultáveis, representando exatamente a execução
   original e permanecendo distintos do estado atual da receita.
8. **Given** uma receita com resultado preservado, **When** um novo cálculo é
   solicitado, **Then** uma nova execução independente é preservada com snapshot e
   identificação da versão próprios, sem substituir ou alterar resultados anteriores.

### Edge Cases

- Um valor `EXACT` igual a zero continua sendo numérico e distinto de `TRACE`, `NA`,
  `ABSENT` e `REEVALUATION`.
- Nenhum estado qualitativo pode ser convertido automaticamente para zero.
- A combinação, no mesmo nutriente, de valores `EXACT` com estados qualitativos
  produz subtotal limitado aos valores `EXACT`, preserva explicitamente todos os
  estados presentes e nunca representa composição completa. Esse subtotal é
  normalizado pelo rendimento e produz referência numérica igualmente incompleta,
  com os mesmos estados preservados.
- A combinação de valores `EXACT` com ausência de relação `FoodNutrient` produz
  subtotal limitado aos valores `EXACT` e preserva explicitamente a indisponibilidade
  do dado. Esse subtotal é normalizado pelo rendimento e produz referência numérica
  igualmente incompleta, preservando a indicação de ausência de relação. Combinações
  sem contribuição `EXACT` seguem as regras já aprovadas em CQ-001 e não produzem
  valor numérico.
- A presença de um mesmo alimento em diferentes posições da receita não elimina
  nenhuma ocorrência; cada quantidade persistida representa uma contribuição.
- Um rendimento diferente da soma das quantidades deve ser utilizado como informado.
- Rendimento em `ml` não autoriza conversão das quantidades dos ingredientes, que
  permanecem em gramas, nem uso de densidade não informada.
- Valores com muitas casas decimais não podem ser arredondados, truncados ou formatados
  para apresentação durante as etapas desta feature.
- Uma receita inválida ou cujas relações necessárias não existam não pode ser
  corrigida, completada ou modificada silenciosamente pelo cálculo.
- O valor de referência da geleia de amora somente pode ser comparado para os
  componentes e estados cujo tratamento esteja aprovado.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: O sistema MUST calcular a composição nutricional somente para uma
  receita válida já persistida, utilizando seus ingredientes, os alimentos
  referenciados e os dados nutricionais associados existentes.
- **FR-002**: O sistema MUST utilizar a quantidade em gramas persistida para cada
  ingrediente e os valores nutricionais definidos por 100 g de parte comestível na
  Base Nutricional.
- **FR-003**: Para cada valor nutricional numérico aplicável, o sistema MUST calcular
  a contribuição do ingrediente pela fórmula
  `quantidade utilizada × valor do nutriente por 100 g ÷ 100`.
- **FR-004**: O sistema MUST calcular separadamente cada componente nutricional e
  MUST NOT combinar valores pertencentes a nutrientes diferentes.
- **FR-005**: O sistema MUST consolidar separadamente as contribuições de cada
  nutriente. Quando houver contribuições `EXACT` combinadas com `TRACE`, `NA`,
  `ABSENT` ou `REEVALUATION` para o mesmo nutriente, MUST somar somente os valores
  `EXACT`, MUST preservar explicitamente cada estado qualitativo presente e MUST
  indicar que o subtotal numérico representa apenas a parcela conhecida
  numericamente; esse subtotal MUST NOT ser interpretado nem apresentado como
  composição nutricional completa. A mesma regra de subtotal MUST ser aplicada
  quando `EXACT` coexistir com ausência de relação `FoodNutrient`, preservando essa
  indisponibilidade de dado explicitamente e de forma distinta dos estados
  qualitativos. Quando todas as contribuições possuírem o mesmo estado qualitativo,
  o consolidado MUST preservar esse estado, MUST NOT possuir valor numérico e MUST
  ser identificado como incompleto, sem representar composição nutricional completa.
  Quando houver estados qualitativos diferentes e nenhuma contribuição `EXACT`, o
  consolidado MUST preservar o conjunto completo de estados presentes, MUST NOT
  aplicar precedência entre eles, MUST NOT possuir valor numérico e MUST ser
  identificado como incompleto. Quando estados qualitativos coexistirem com ausência
  de relação `FoodNutrient` e não houver contribuição `EXACT`, o consolidado MUST
  preservar separadamente todos os estados presentes e a indisponibilidade por
  ausência de relação, MUST NOT aplicar precedência, MUST NOT possuir valor numérico
  e MUST ser identificado como incompleto. Quando nenhum ingrediente possuir relação
  `FoodNutrient` para determinado nutriente, o consolidado MUST indicar explicitamente
  indisponibilidade por ausência de relação, MUST NOT possuir valor numérico, MUST
  ser identificado como incompleto e MUST NOT ser reclassificado como estado
  qualitativo.
- **FR-006**: O sistema MUST preservar, em cada contribuição, total e valor de
  referência, a unidade definida no nutriente correspondente.
- **FR-007**: Para cada total nutricional aplicável, o sistema MUST calcular o valor
  de referência pela fórmula
  `total do nutriente na receita × 100 ÷ rendimento total persistido`. Quando
  `yield_unit = g`, o resultado MUST possuir referência de 100 g; quando
  `yield_unit = ml`, MUST possuir referência de 100 ml. Quando o consolidado possuir
  subtotal numérico incompleto por combinar ao menos uma contribuição `EXACT` com
  `TRACE`, `NA`, `ABSENT`, `REEVALUATION` e/ou ausência de relação `FoodNutrient`, o
  sistema MUST normalizar esse subtotal por
  `subtotal × 100 ÷ rendimento total persistido`. O valor de referência resultante
  MUST permanecer marcado como incompleto, MUST preservar todos os mesmos estados
  qualitativos e, separadamente, a mesma indicação de ausência de relação, e MUST
  NOT ser apresentado como composição nutricional completa.
- **FR-008**: O sistema MUST utilizar o valor e a unidade de rendimento já
  persistidos e MUST NOT inferir ou recalcular o rendimento pela soma das
  quantidades dos ingredientes.
- **FR-009**: O sistema MUST NOT calcular densidade nem converter `g` para `ml` ou
  `ml` para `g`.
- **FR-010**: O sistema MUST preservar precisão suficiente em todas as etapas para
  impedir perda ou arredondamento prematuro de informação.
- **FR-011**: O sistema MUST NOT aplicar arredondamento de apresentação, regras de
  quantidade não significativa ou formatação de rótulo aos valores intermediários
  ou finais desta feature.
- **FR-012**: Um dado nutricional com estado `EXACT` e valor numérico zero MUST
  participar como zero real e MUST permanecer distinto de `TRACE`, `NA`, `ABSENT` e
  `REEVALUATION`.
- **FR-013**: O sistema MUST preservar a distinção entre `EXACT`, `TRACE`, `NA`,
  `ABSENT` e `REEVALUATION` e MUST NOT converter silenciosamente qualquer estado
  qualitativo para zero.
- **FR-014**: Dados nutricionais `TRACE`, `NA`, `ABSENT` e `REEVALUATION` MUST produzir
  contribuições qualitativas correspondentes, sem valor numérico, e MUST NOT ser
  convertidos para zero; `ABSENT` e `REEVALUATION` também MUST NOT ser omitidos
  silenciosamente. A inexistência de relação `FoodNutrient` MUST produzir uma
  indicação qualitativa explícita de dado nutricional não disponível, sem valor
  numérico e distinta desses estados. A consolidação envolvendo qualquer desses
  casos MUST seguir as demais decisões de CQ-001.
- **FR-015**: O sistema MUST processar todos os `Nutrients` existentes na Base
  Nutricional, aplicando as regras aprovadas para valores `EXACT`, estados
  qualitativos e ausência de relação. Essa abrangência MUST NOT determinar quais
  nutrientes serão exibidos em rótulo ou utilizados em `%VD`. A energia existente na
  Base Nutricional MUST ser tratada como qualquer outro `Nutrient`, com cálculo
  proporcional, consolidação e aplicação das regras de CQ-001; esse resultado MUST
  representar exclusivamente o dado energético da fonte e MUST NOT ser interpretado
  como cálculo por fatores de conversão ou valor final de rotulagem.
- **FR-016**: O resultado MUST ser determinístico e reproduzível para as mesmas
  entradas e regras aprovadas e MUST permanecer disponível após a solicitação para
  consulta e como base para reprodutibilidade e auditoria. Cada resultado preservado
  MUST permanecer associado a um snapshot imutável das entradas utilizadas, que MUST
  NOT ser alterado por modificações posteriores na receita, em seus ingredientes ou
  na Base Nutricional. O snapshot MUST preservar a identificação da receita; o
  rendimento e sua unidade; cada ocorrência de ingrediente, sua ordem, quantidade e
  identificação/rastreabilidade do alimento; e, para cada nutriente processado, sua
  identificação, nome, unidade e o valor `EXACT`, estado qualitativo ou ausência de
  relação utilizado. MUST conter somente dados que participaram do cálculo e MUST NOT
  depender dos valores atuais da receita ou da Base Nutricional. A identificação da
  versão das regras ou do motor utilizada MUST permanecer explicitamente associada a
  cada resultado e MUST permitir distinguir execuções produzidas por conjuntos de
  regras diferentes. Alterações posteriores na receita, em seus ingredientes ou na
  Base Nutricional MUST NOT modificar retroativamente nem impedir a consulta de
  resultados e snapshots anteriores, que MUST continuar representando exatamente as
  entradas e regras de sua execução. Cada solicitação de cálculo MUST produzir uma
  nova execução preservada com snapshot imutável e identificação da versão próprios e
  MUST NOT substituir nem alterar resultados anteriores.
- **FR-017**: O cálculo MUST NOT criar, corrigir, editar ou remover a receita, seus
  ingredientes, alimentos, nutrientes ou dados nutricionais associados.
- **FR-018**: O sistema MUST NOT preencher silenciosamente relações ou valores
  nutricionais inexistentes nem substituir referências a alimentos.
- **FR-019**: O caso de referência “GELEIA DE AMORA” MUST ser utilizável para validar
  as etapas pertencentes a esta feature, usando 1.000 g de amora, 1.000 g de açúcar
  cristal, 50 g de suco de limão e rendimento final de 1.680 g, após a
  Viridis/equipe responsável aprovar o mapeamento inequívoco dos Foods ainda não
  identificados. Até essa aprovação, o teste real MUST permanecer bloqueado e MUST
  NOT criar Food sintético nem escolher correspondência aproximada.
- **FR-020**: Quando os nutrientes participantes e seus tratamentos estiverem
  aprovados, o resultado de carboidratos da geleia de amora MUST poder ser comparado
  com a referência técnica aproximada de 65,21 g por 100 g, sem incorporar porção,
  percentual diário, arredondamento de apresentação ou valor energético não
  esclarecido.
- **FR-021**: Esta feature MUST NOT incluir criação ou edição de receitas e
  ingredientes, importação ou busca de alimentos, autenticação, medidas caseiras,
  cálculo por porção, percentual diário, regras de arredondamento, quantidades não
  significativas, açúcares adicionados, rotulagem, visualização ou exportação.

### Clarifications Resolved

- **CQ-001 — Estados e dados ausentes**: `TRACE`, `NA`, `ABSENT` e `REEVALUATION`
  produzem contribuições qualitativas correspondentes, sem valor numérico e sem
  conversão para zero; `ABSENT` e `REEVALUATION` não podem ser omitidos
  silenciosamente. A inexistência de relação `FoodNutrient` produz indicação
  qualitativa explícita de dado nutricional não disponível, sem valor numérico e
  distinta desses estados. Quando `EXACT` coexiste com `TRACE`, `NA`, `ABSENT` ou
  `REEVALUATION`, somente os valores `EXACT` são somados; cada estado presente é
  preservado explicitamente e o subtotal é identificado como limitado à parcela
  conhecida numericamente, sem ser apresentado como composição completa. A mesma
  regra vale quando `EXACT` coexiste com ausência de relação, preservada como
  indisponibilidade distinta dos estados existentes. Quando todas as contribuições
  possuírem o mesmo estado qualitativo, o consolidado preserva esse estado, permanece
  sem valor numérico e é identificado como incompleto. Quando estados qualitativos
  diferentes coexistem sem `EXACT`, o consolidado preserva o conjunto completo de
  estados, sem precedência, sem valor numérico e com identificação de incompletude.
  Quando estados qualitativos coexistem com ausência de relação e não há `EXACT`,
  o consolidado preserva separadamente todos os estados e a indisponibilidade, sem
  precedência, sem valor numérico e com identificação de incompletude. Quando todos
  os ingredientes estão sem relação para o nutriente, o consolidado indica
  indisponibilidade por ausência de relação, sem valor numérico, explicitamente
  incompleto e distinto dos estados qualitativos. Nenhum estado qualitativo ou caso
  de ausência de relação é convertido para zero. Quando existe subtotal `EXACT`
  incompleto, ele é normalizado pelo rendimento; a referência numérica preserva os
  mesmos estados e/ou ausência de relação e permanece explicitamente incompleta.
- **CQ-002 — Escopo e referência do cálculo**: Todos os `Nutrients` existentes na Base
  Nutricional são processados conforme as regras aprovadas para valores `EXACT`,
  estados qualitativos e ausência de relação, sem determinar exibição em rótulo ou
  utilização em `%VD`. A energia da Base Nutricional participa como qualquer outro
  `Nutrient` e representa exclusivamente o dado energético da fonte; cálculo por
  fatores de conversão e valor final de rotulagem pertencem a specifications
  posteriores. `yield_unit = g` determina referência de 100 g e `yield_unit = ml`
  determina referência de 100 ml, usando o rendimento exatamente como persistido,
  sem conversão entre massa e volume ou inferência de densidade.
- **CQ-003 — Reprodutibilidade e ciclo do resultado**: O resultado produzido permanece
  disponível após a solicitação para consulta e como base para reprodutibilidade e
  auditoria. Cada resultado permanece associado a um snapshot imutável das entradas
  utilizadas, inalterado por modificações posteriores na receita, em seus ingredientes
  ou na Base Nutricional. O snapshot preserva a identificação da receita; rendimento
  e unidade; cada ocorrência ordenada de ingrediente com quantidade e rastreabilidade
  do alimento; e a identificação, nome, unidade e dado utilizado para cada nutriente
  processado, sem copiar campos alheios ao cálculo ou depender dos valores atuais.
  Cada resultado mantém um identificador explícito da versão das regras ou do motor
  utilizada, capaz de distinguir execuções produzidas por conjuntos de regras
  diferentes; o formato técnico dessa identificação será definido no Plan/Research.
  Alterações posteriores na receita, em seus ingredientes ou na Base Nutricional não
  modificam nem impedem a consulta de resultados e snapshots anteriores, que
  continuam representando exatamente suas execuções e permanecem distintos do estado
  atual da receita. Cada solicitação de cálculo cria uma nova execução preservada com
  snapshot imutável e identificação da versão próprios, sem substituir nem alterar
  resultados anteriores.

### Key Entities

- **Receita**: Agregado já persistido que fornece o rendimento informado e a coleção
  de ingredientes utilizada no cálculo; não é criado nem alterado por esta feature.
- **Ingrediente da Receita**: Ocorrência de um alimento na receita, com quantidade
  persistida exclusivamente em gramas; cada ocorrência contribui separadamente.
- **Alimento**: Registro existente da Base Nutricional referenciado pelo ingrediente;
  não é duplicado nem modificado.
- **Nutriente**: Componente nutricional identificado na Base Nutricional, com sua
  unidade própria; componentes distintos nunca são consolidados entre si.
- **Dado Nutricional do Alimento**: Valor por 100 g de parte comestível ou estado
  qualitativo associado a um alimento e nutriente.
- **Contribuição Nutricional do Ingrediente**: Resultado proporcional de um
  ingrediente para um nutriente, antes da consolidação da receita.
- **Total Nutricional da Receita**: Soma das contribuições de um mesmo nutriente em
  todos os ingredientes, conforme o tratamento aprovado dos estados.
- **Valor Nutricional de Referência**: Total ou subtotal numérico normalizado pelo
  rendimento informado para 100 g quando `yield_unit = g` ou para 100 ml quando
  `yield_unit = ml`, preservando a incompletude quando aplicável.

## Scope Boundaries

### Included

- Cálculo proporcional de valores nutricionais por ingrediente.
- Consolidação das contribuições por nutriente.
- Processamento de todos os nutrientes existentes na Base Nutricional, sem definir
  sua posterior exibição regulatória.
- Normalização dos totais e subtotais numéricos pelo rendimento informado para uma
  referência aprovada de 100 g ou 100 ml.
- Preservação da precisão durante as etapas de cálculo.
- Distinção entre zero numérico `EXACT` e estados qualitativos.
- Validação pelo caso de referência da geleia de amora nas partes abrangidas pela
  feature.
- Preservação do resultado e de um snapshot imutável das entradas efetivamente
  utilizadas para reprodutibilidade e auditoria, no limite funcional definido em
  CQ-003.

### Excluded

- Criação ou edição de receitas e ingredientes.
- Importação TACO ou busca de alimentos.
- Autenticação e autorização.
- Conversão de unidades, densidade e medidas caseiras.
- Cálculo por porção e definição de porção.
- Percentual diário e valor energético por fatores de conversão enquanto sua regra
  não estiver aprovada.
- Arredondamento de apresentação e regras de quantidades não significativas.
- Açúcares adicionados.
- Rotulagem frontal, geração ou visualização de tabela nutricional.
- Preview, PDF, PNG e templates de rótulo.
- Auditoria avançada, event sourcing, cache e execução assíncrona.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Em 100% dos casos de teste com valores `EXACT`, cada contribuição por
  ingrediente corresponde a `quantidade × valor por 100 g ÷ 100`, sem diferença
  causada por arredondamento intermediário.
- **SC-002**: Em 100% dos casos de teste resolvidos pelas decisões de CQ-001, o total
  de cada nutriente corresponde exatamente à consolidação aprovada das contribuições
  daquele nutriente, sem mistura com outros componentes.
- **SC-003**: Em 100% dos casos de teste, o rendimento utilizado corresponde ao valor
  persistido na receita e nunca à soma inferida dos ingredientes.
- **SC-004**: Em 100% dos casos de teste, cada valor de referência corresponde a
  `total ou subtotal numérico do nutriente × 100 ÷ rendimento total` e é identificado
  como referência de 100 g quando `yield_unit = g` ou 100 ml quando
  `yield_unit = ml`. Quando a origem for um subtotal incompleto, a referência também
  permanece explicitamente incompleta e preserva os mesmos estados qualitativos e a
  mesma indicação de ausência de relação.
- **SC-005**: Valores de teste com casas decimais além das necessárias à apresentação
  atravessam contribuição, consolidação e normalização sem arredondamento ou
  truncamento prematuro.
- **SC-006**: Em 100% dos cenários de estados qualitativos e dados ausentes, o
  resultado segue a interpretação aprovada em CQ-001 e nenhum estado é convertido
  silenciosamente para zero.
- **SC-007**: Repetir o cálculo com as mesmas entradas e regras produz resultados
  idênticos, e cada resultado preservado fornece no snapshot imutável todas as
  entradas aprovadas necessárias para reproduzir e explicar sua execução, além de um
  identificador que distingue a versão das regras ou do motor utilizada; cada nova
  solicitação preserva uma execução independente sem alterar as anteriores.
- **SC-008**: Em 100% dos cálculos, a receita, seus ingredientes e todos os registros
  da Base Nutricional permanecem inalterados.
- **SC-009**: Após a Viridis/equipe responsável aprovar o mapeamento inequívoco dos
  Foods, o caso “GELEIA DE AMORA” pode ser executado com as entradas documentadas e
  seu resultado de carboidratos por 100 g pode ser comparado à referência aproximada
  de 65,21 g, com qualquer diferença explicada exclusivamente pelos dados e regras
  aprovados. Até esse mapeamento existir, o teste real permanece bloqueado e não pode
  ser marcado como PASS; os testes genéricos do motor com fixtures controladas não
  são bloqueados.
- **SC-010**: Nenhum resultado desta feature contém cálculo por porção, percentual
  diário, arredondamento de apresentação, valor energético por fatores não
  aprovados ou informação de rotulagem.

## Assumptions

- As features `001-taco-nutrition-base` e `002-recipe-management` estão concluídas e
  seus dados válidos estão disponíveis sem necessidade de recriação.
- Cada dado nutricional numérico da Base Nutricional representa a composição por
  100 g de parte comestível e mantém a unidade do nutriente correspondente.
- Cada quantidade de ingrediente é positiva, persistida em gramas e já validada pela
  feature 002.
- O rendimento é positivo, persistido separadamente em `g` ou `ml` e não equivale
  necessariamente à soma das quantidades dos ingredientes.
- A referência aproximada de 65,21 g de carboidratos por 100 g para a geleia de amora
  é um caso técnico de comparação, não uma regra de arredondamento ou tolerância.
- As decisões de CQ-001, CQ-002 e CQ-003 foram resolvidas e incorporadas a esta
  Specification antes do planejamento técnico.
