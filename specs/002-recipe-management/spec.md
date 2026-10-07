# Feature Specification: Recipe Management

**Feature Branch**: `002-recipe-management`

**Created**: 2026-10-07

**Status**: Draft

**Input**: User description: "Permitir criar, salvar, consultar e editar receitas compostas por ingredientes vinculados aos alimentos existentes na Base Nutricional, preservando quantidades, unidades, ordem e rendimento, sem realizar cálculos nutricionais."

## Clarifications

### Session 2026-10-07

- Q: Quais características do produto, além do nome, devem ser obrigatoriamente informadas e preservadas em cada receita? → A: Nenhuma característica adicional; somente o nome nesta feature. Características futuras exigem alteração formal e aprovada desta specification.
- Q: Quais unidades podem ser informadas para a quantidade de cada ingrediente e deve ocorrer alguma conversão nesta feature? → A: Somente gramas (`g`). A quantidade será informada e preservada em gramas, sem conversão. Suporte a `kg`, `ml` ou medidas caseiras exige specification posterior; esta decisão não define rendimento, porção nem referência de produtos líquidos.
- Q: Qual regra deve determinar se a quantidade em gramas de um ingrediente é válida? → A: A quantidade deve ser maior que zero; zero e valores negativos são inválidos. Frações de grama são permitidas. Esta feature não define precisão decimal máxima sem decisão explícita adicional da equipe.
- Q: Qual regra deve ser aplicada ao rendimento total da receita quanto à obrigatoriedade, unidade e validade? → A: O rendimento é obrigatório para uma receita completa, deve ser informado explicitamente em `g` ou `ml` e ser maior que zero; frações são permitidas e não há precisão decimal máxima definida nesta feature. O valor não é inferido pela soma dos ingredientes nem convertido ou interpretado matematicamente.
- Q: Uma receita pode ser salva sem ingredientes e a reordenação deve produzir algum efeito além de preservar exatamente a ordem escolhida pelo usuário? → A: Não. A persistência exige ao menos um ingrediente válido, sem estado de rascunho ou receita incompleta. A reordenação apenas preserva a ordem definida pelo usuário e não altera alimento, quantidade, unidade, rendimento ou qualquer outro dado.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Criar e salvar uma receita (Priority: P1)

Como usuário, quero criar uma receita, informar seu nome, seus ingredientes e seu rendimento total para preservar as entradas necessárias às etapas posteriores do NutriLabel.

**Why this priority**: A receita persistida é a unidade central desta feature. Sem ela, não há como gerenciar ingredientes nem reutilizar as entradas posteriormente.

**Independent Test**: Pode ser testada criando uma receita completa com múltiplos ingredientes associados a alimentos existentes, salvando-a e confirmando que todos os dados informados foram preservados.

**Acceptance Scenarios**:

1. **Given** que existem alimentos disponíveis na Base Nutricional, **When** o usuário informa os dados obrigatórios da receita, adiciona múltiplos ingredientes válidos e um rendimento válido em `g` ou `ml`, e salva, **Then** a receita é persistida com os alimentos selecionados, quantidades em gramas, ordem, valor e unidade do rendimento informados.
2. **Given** uma tentativa de salvar uma receita com dados obrigatórios ausentes, **When** a persistência é solicitada, **Then** o sistema rejeita a entrada e não a considera uma receita completa válida.
3. **Given** uma tentativa de salvar uma receita sem ingredientes válidos, **When** a persistência é solicitada, **Then** o sistema rejeita o salvamento sem criar rascunho, receita incompleta, ingrediente ou alimento.

---

### User Story 2 - Gerenciar ingredientes da receita (Priority: P2)

Como usuário, quero adicionar, editar, remover e reordenar ingredientes para manter a composição informada da receita alinhada ao produto que estou cadastrando.

**Why this priority**: Após a existência da receita, o gerenciamento dos ingredientes é necessário para representar e corrigir sua composição sem executar cálculos nutricionais.

**Independent Test**: Pode ser testada em uma receita existente adicionando, alterando, removendo e reordenando ingredientes e verificando que o resultado salvo corresponde exatamente às escolhas do usuário.

**Acceptance Scenarios**:

1. **Given** uma receita existente e um alimento selecionado na Base Nutricional, **When** o usuário adiciona esse alimento como ingrediente com quantidade válida em gramas, **Then** o ingrediente passa a compor a receita na posição escolhida.
2. **Given** um ingrediente existente, **When** o usuário altera seus dados permitidos, **Then** os novos dados substituem os anteriores sem trocar silenciosamente o alimento selecionado.
3. **Given** um ingrediente existente, **When** o usuário o remove, **Then** ele deixa de fazer parte da receita sem remover ou modificar o alimento na Base Nutricional.
4. **Given** múltiplos ingredientes, **When** o usuário altera sua ordem, **Then** a ordem escolhida é preservada ao salvar e reabrir a receita sem alterar alimento, quantidade em gramas, rendimento ou qualquer outro dado.

---

### User Story 3 - Consultar e editar uma receita existente (Priority: P3)

Como usuário, quero abrir uma receita já salva, consultar suas entradas e editá-la para continuar o trabalho sem perda de informações.

**Why this priority**: A reprodutibilidade das entradas permite que a receita seja utilizada com segurança pelas funcionalidades futuras, sem exigir que o usuário recadastre seus dados.

**Independent Test**: Pode ser testada salvando uma receita, reabrindo-a, comparando todas as entradas com o que foi informado e verificando que uma edição posterior também é preservada.

**Acceptance Scenarios**:

1. **Given** uma receita salva, **When** o usuário a consulta novamente, **Then** nome, ingredientes, alimentos selecionados, quantidades em gramas, ordem e rendimento são apresentados sem perda.
2. **Given** uma receita salva, **When** o usuário edita entradas válidas e salva novamente, **Then** a consulta posterior apresenta os dados atualizados e preserva as demais entradas.
3. **Given** uma receita reaberta, **When** seus ingredientes são consultados, **Then** cada ingrediente continua associado ao mesmo alimento interno selecionado originalmente.

### Edge Cases

- Quantidades iguais a zero ou negativas devem ser rejeitadas; frações de grama positivas são permitidas, sem correção, conversão ou arredondamento implícito.
- Uma receita sem ao menos um ingrediente válido não pode ser persistida, nem mesmo como rascunho ou receita incompleta.
- Um ingrediente sem alimento correspondente na Base Nutricional deve ser rejeitado; esta feature não cria alimentos substitutos nem duplica a base.
- A remoção de um ingrediente não deve remover o alimento correspondente da Base Nutricional.
- A reordenação não deve alterar o alimento nem a quantidade em gramas de nenhum ingrediente.
- O rendimento informado não deve ser substituído automaticamente pela soma das quantidades dos ingredientes.
- O rendimento igual a zero ou negativo deve ser rejeitado; valores fracionários positivos são permitidos em `g` ou `ml`, sem conversão entre essas unidades.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: O sistema MUST permitir criar uma receita.
- **FR-002**: O sistema MUST preservar o nome informado para a receita.
- **FR-003**: O sistema MUST NOT exigir ou persistir características adicionais de produto nesta feature; além dos ingredientes e das demais entradas especificadas, o único dado de identificação obrigatório da receita MUST ser o nome. Características futuras MUST exigir alteração formal e aprovada desta specification.
- **FR-004**: O sistema MUST permitir que uma receita seja composta por múltiplos ingredientes.
- **FR-005**: Cada ingrediente MUST referenciar um alimento já existente na Base Nutricional por sua identificação interna estável (`foods.id`).
- **FR-006**: O sistema MUST NOT recriar, duplicar ou modificar a Base Nutricional ao associar um alimento a um ingrediente.
- **FR-007**: Para cada ingrediente, o sistema MUST preservar o alimento selecionado, a quantidade informada em gramas (`g`) e sua posição na receita.
- **FR-008**: O sistema MUST permitir adicionar, editar e remover ingredientes de uma receita.
- **FR-009**: O sistema MUST permitir reordenar ingredientes e preservar exatamente a ordem escolhida após salvar e reabrir a receita. A reordenação MUST NOT alterar alimento, quantidade em gramas, rendimento ou qualquer outro dado.
- **FR-010**: O sistema MUST aceitar e preservar a quantidade de cada ingrediente exclusivamente em gramas (`g`) e MUST NOT realizar conversão de unidade. A quantidade MUST ser maior que zero, MUST permitir frações de grama e MUST rejeitar zero e valores negativos. Esta feature não estabelece precisão decimal máxima; qualquer limite futuro MUST exigir decisão explícita e alteração formal desta specification. Ingredientes sem referência a um alimento existente na Base Nutricional MUST ser rejeitados, sem criação ou substituição de alimento.
- **FR-011**: Para uma receita completa, o sistema MUST exigir que o usuário informe explicitamente um rendimento total maior que zero, em gramas (`g`) ou mililitros (`ml`), e MUST permitir valores fracionários. O sistema MUST preservar o valor e a unidade informados, sem inferir o rendimento pela soma das quantidades dos ingredientes, converter entre `g` e `ml`, calcular densidade ou interpretar matematicamente o rendimento. Esta feature não estabelece precisão decimal máxima; qualquer limite futuro MUST exigir decisão explícita e alteração formal desta specification.
- **FR-012**: O sistema MUST NOT persistir uma receita sem ao menos um ingrediente válido e MUST NOT introduzir estado de rascunho ou receita incompleta nesta feature.
- **FR-013**: Toda receita persistida MUST possuir ao menos um ingrediente válido.
- **FR-014**: O sistema MUST rejeitar dados obrigatórios ausentes.
- **FR-015**: O sistema MUST permitir consultar uma receita salva.
- **FR-016**: O sistema MUST permitir editar uma receita existente e salvar novamente suas entradas válidas.
- **FR-017**: Ao reabrir uma receita, o sistema MUST preservar os dados definidos nesta feature, incluindo nome, ingredientes, referências aos alimentos, quantidades em gramas, ordem, valor e unidade do rendimento.
- **FR-018**: O sistema MUST manter os ingredientes vinculados aos mesmos registros internos de alimento após salvar, consultar e editar a receita.
- **FR-019**: Esta feature MUST NOT realizar cálculo proporcional, consolidação nutricional, cálculo por rendimento, cálculo por 100 g ou 100 ml, cálculo por porção, cálculo de %VD ou valor energético.
- **FR-020**: Esta feature MUST NOT interpretar matematicamente estados nutricionais da Base Nutricional nem aplicar arredondamento, regras de valores não significativos ou regras regulatórias.
- **FR-021**: Esta feature MUST reutilizar a pesquisa e os alimentos disponibilizados pela feature `001-taco-nutrition-base`, sem reimplementar importação, entidades nutricionais, busca textual ou tratamento dos estados `EXACT`, `TRACE`, `NA`, `ABSENT` e `REEVALUATION`.

### Key Entities

- **Receita**: Representa o produto que o usuário deseja cadastrar, com nome, rendimento final explicitamente informado em `g` ou `ml` e sua coleção ordenada de ingredientes.
- **Ingrediente da Receita**: Representa uma entrada da composição da receita, preservando a referência ao alimento existente, a quantidade informada em gramas e a posição escolhida pelo usuário.
- **Alimento**: Registro já existente na Base Nutricional, identificado internamente por `foods.id` e apenas referenciado por esta feature; não é recriado nem duplicado.

## Scope Boundaries

### Included

- Criação, persistência, consulta e edição de receitas.
- Adição, edição, remoção e reordenação de ingredientes.
- Referência de cada ingrediente a um alimento existente.
- Preservação da quantidade dos ingredientes em gramas, da ordem e do valor e unidade do rendimento informado em `g` ou `ml`.
- Validações relacionadas ao cadastro e à completude da receita.

### Excluded

- Autenticação e gestão de usuários.
- Cálculos nutricionais de qualquer natureza, incluindo proporcionalidade, consolidação, rendimento, 100 g, 100 ml, porção, %VD e valor energético.
- Interpretação matemática dos estados nutricionais da TACO.
- Regras de arredondamento, valores não significativos e açúcares adicionados.
- Rotulagem frontal, geração ou visualização da tabela nutricional e exportação PDF/PNG.
- Histórico de resultados processados, gestão de clientes e propostas comerciais.
- Recriação da Base Nutricional, importação TACO ou nova busca de alimentos.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Em 100% dos cenários de aceitação com entradas válidas, uma receita completa pode ser salva e consultada novamente.
- **SC-002**: Em 100% das receitas reabertas nos testes de aceitação, nome, ingredientes, alimentos selecionados, quantidades em gramas, ordem, valor e unidade do rendimento correspondem ao último salvamento.
- **SC-003**: Em 100% dos ingredientes persistidos, a referência interna corresponde ao mesmo alimento selecionado na Base Nutricional.
- **SC-004**: Após adicionar, editar, remover ou reordenar ingredientes, 100% das alterações válidas permanecem preservadas ao reabrir a receita.
- **SC-005**: 100% das entradas classificadas como inválidas pelas regras aprovadas são rejeitadas sem correção, conversão ou preenchimento silencioso.
- **SC-006**: Nenhuma operação desta feature produz valores nutricionais calculados, resultados por rendimento, valores por 100 g ou 100 ml, valores por porção, %VD ou resultados de rotulagem.
- **SC-007**: Um usuário consegue concluir o fluxo criar, salvar, consultar e editar uma receita sem recadastrar alimentos já existentes na Base Nutricional.

## Assumptions

- A feature `001-taco-nutrition-base` está concluída e disponibiliza alimentos identificados internamente por `foods.id`.
- A pesquisa de alimentos existente será reutilizada somente para selecionar um alimento; esta feature não altera seu comportamento.
- A quantidade de cada ingrediente é informada e preservada somente em gramas. O rendimento final é informado e preservado separadamente em `g` ou `ml`. Nenhum desses valores é convertido ou interpretado matematicamente nesta feature.
- Não são presumidos limites de tamanho de texto, número máximo de ingredientes, faixas numéricas ou precisão decimal.
- Autenticação, permissões e comportamentos administrativos permanecem fora do escopo.
- As dúvidas explicitamente marcadas devem ser resolvidas pelo fluxo `$speckit-clarify` antes do planejamento.
