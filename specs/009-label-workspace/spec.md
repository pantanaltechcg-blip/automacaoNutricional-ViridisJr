# Feature Specification: Área de Rótulos

**Feature ID**: `009-label-workspace`
**Feature Branch**: `codex/009-label-workspace`
**Created**: 2026-11-27
**Status**: Clarify concluído — pronto para Plan
**Constitution**: 2.0.0
**Input**: Continuação do fechamento da interface: tornar disponível a consulta de execuções existentes na área “Rótulos” e remover a área “Configurações”, que não é necessária.

## Contexto e limites

A Feature 008 entregou o fluxo de receita, a tabela em estado bloqueado e a montagem condicionada à existência de uma execução válida da Feature 005. A interface mantém “Rótulos” indisponível por não haver uma listagem de execuções. “Configurações” não é necessária e será removida da interface.

Uma execução da Feature 005 só existe quando suas entradas e referências exigidas estão disponíveis. A ausência de execução válida não autoriza gerar valores, substituir referências ou ocultar as causas do bloqueio. Esta Feature não aprova dados nutricionais ou regulatórios e não remove os gates das Features 004 e 005.

## Clarifications

### Session 2026-11-27

- Q: O que a área “Configurações” deve administrar: referências nutricionais/regulatórias, preferências de interface, ambos ou outro conjunto específico? → A: Não é necessário ter área de Configurações; remover o item da interface e não criar essa funcionalidade.
- Q: Quem pode cadastrar, revisar e autorizar essas referências, considerando que o aplicativo atual não autentica usuários? → A: O NutriLabel é para uso interno da empresa; autenticação não é necessária neste momento. O acesso público não faz parte do escopo.

## User Scenarios & Testing

### User Story 1 — Consultar execuções de rótulo (Priority: P1)

Como pessoa usuária do NutriLabel, quero consultar as execuções de declaração nutricional já preservadas para localizar uma execução e compreender se ela está completa ou bloqueada.

**Why this priority**: O menu “Rótulos” existe no protótipo, mas permanece indisponível; uma consulta às execuções reais permite continuar o fluxo sem criar resultados fictícios.

**Independent Test**: Criar execuções reais de teste com estados distintos e confirmar que a área lista somente registros existentes, apresenta o estado e as causas persistidas e não altera nenhuma execução.

**Acceptance Scenarios**:

1. **Given** que existem execuções de declaração preservadas, **When** a pessoa abre “Rótulos”, **Then** cada execução existente é apresentada com identificação suficiente para distingui-la e com o estado de prontidão e as causas informadas pela execução.
2. **Given** uma execução incompleta ou não apta à rotulagem, **When** ela é consultada, **Then** permanece identificada como incompleta/não apta e nenhuma causa é omitida ou convertida em sucesso.
3. **Given** que não há execuções de declaração, **When** a pessoa abre “Rótulos”, **Then** é apresentada uma mensagem de lista vazia, sem criar cálculos ou dados de demonstração.
4. **Given** uma execução existente, **When** a pessoa solicita sua montagem, **Then** somente aquela execução é consultada; abrir a montagem não recalcula nem modifica qualquer histórico.
5. **Given** que a pessoa abre qualquer tela desta interface, **When** a navegação é apresentada, **Then** não existe item de menu nem página “Configurações”.

### Edge Cases

- Não existe execução de declaração para uma receita: a área mostra lista vazia, sem inferir que a receita tem rótulo.
- Execução preservada está incompleta: apresentar o estado e todas as causas disponíveis; não promover subtotal a resultado completo.
- Uma execução histórica é consultada após mudanças posteriores na receita ou em referências: exibir a execução preservada, sem reconstruí-la com dados atuais.
- Há registros de mais de uma execução para a mesma receita: cada execução permanece distinguível e consultável, sem substituição silenciosa.
- A aplicação é usada internamente, sem autenticação nesta etapa; a exposição pública não faz parte do escopo.

## Requirements

### Functional Requirements

- **FR-001**: A área “Rótulos” MUST permitir consultar as execuções de declaração nutricional já preservadas no NutriLabel, sem criar uma execução automaticamente.
- **FR-002**: Cada item MUST identificar inequivocamente a execução consultada e MUST apresentar seu estado de prontidão e as causas persistidas sem omissão ou reinterpretação.
- **FR-003**: Uma execução incompleta, bloqueada ou não apta MUST permanecer claramente distinguida de uma execução completa e apta; nenhum valor ausente ou estado qualitativo MUST ser convertido em zero ou sucesso.
- **FR-004**: A consulta ou abertura de uma montagem MUST preservar a execução e seu snapshot históricos, sem recalcular ou substituir os dados originais.
- **FR-005**: A lista MUST apresentar um estado vazio quando não houver execuções, sem dados fictícios, amostras ou resultados derivados da receita atual.
- **FR-006**: A área “Rótulos” MUST abrir a montagem somente para uma execução de declaração efetivamente existente; a ausência da execução MUST produzir estado indisponível explícito.
- **FR-007**: A interface MUST remover o item de menu “Configurações” e MUST NOT oferecer uma área de configurações nesta Feature.
- **FR-008**: Esta Feature MUST operar no contexto interno da empresa e MUST NOT introduzir autenticação. A aplicação MUST NOT ser exposta publicamente sem uma decisão e controles de acesso posteriores.
- **FR-009**: Nenhuma funcionalidade desta Feature MUST inserir, aprovar ou ativar valores MAP, ENE, VDR, fatores, conversões ou referências sem os fundamentos e evidências exigidos pela Constitution e pelas Features 004 e 005.
- **FR-010**: Resultados preservados MUST continuar distinguindo o cálculo exato anterior à declaração, os valores declarados e a prontidão para rotulagem, conforme o contrato vigente da Feature 005.
- **FR-011**: Esta Feature MUST NOT alterar regras, fontes, resultados históricos ou contratos das Features 001–008, nem remover avisos de bloqueio regulatório enquanto a causa persistir.

### Key Entities

- **Execução de declaração**: resultado já preservado pela Feature 005, com seu estado de completude/prontidão e causas.
- **Execução de origem**: execução nutricional/porção à qual o resultado declarado está vinculado e cuja rastreabilidade histórica deve ser mantida.

## Success Criteria

### Measurable Outcomes

- **SC-001**: 100% dos registros preservados elegíveis para consulta aparecem como execuções distinguíveis, sem criação de registros durante a consulta.
- **SC-002**: 100% das execuções incompletas ou não aptas continuam identificadas com seu estado e causas, sem serem apresentadas como completas.
- **SC-003**: Consultar uma execução histórica não modifica seu conteúdo ou snapshot.
- **SC-004**: Quando não existir execução, a área informa a lista vazia sem exibir qualquer valor nutricional fictício.
- **SC-005**: A interface da Feature MUST NOT apresentar item ou página “Configurações”.

## Assumptions

- A Feature 005 permanece a fonte das execuções de valores declarados e de seus estados; esta Feature consulta esses resultados, sem recalculá-los.
- Os gates técnicos e regulatórios atuais continuam autoritativos. Aprovação administrativa de desenvolvimento não transforma uma referência em validação nutricional ou normativa.
- A Feature 008 continua responsável pelo fluxo de receita e pelas mensagens de bloqueio; a ausência de uma execução válida da Feature 005 é mostrada honestamente até que referências reais, autorizadas e compatíveis permitam produzi-la.
- O uso interno da empresa não requer autenticação nesta Feature; exposição pública permanece fora do escopo e não é autorizada por esta decisão.
- A exportação e apresentação final de arquivos permanecem fora do escopo desta Feature.
