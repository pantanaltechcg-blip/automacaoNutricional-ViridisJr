# Feature Specification: taco-nutrition-base

**Feature Branch**: `001-taco-nutrition-base`

**Created**: 2026-10-05

**Status**: Draft

**Input**: User description: "Crie a primeira specification do projeto NutriLabel para a funcionalidade de Base Nutricional com uso da TACO..."

## Clarifications

### Session 2026-10-05

- Q: Quais componentes nutricionais da TACO devem compor o escopo inicial do sistema? (FR-009) → A: Todos os componentes descritos e disponíveis na tabela TACO completa. Serão preservados na base, sem implicar que todos serão utilizados pelo MVP (a definição de quais nutrientes entram nos cálculos virá nas próximas specifications).
- Q: Como tratar ou representar internamente marcadores específicos da TACO (Tr, NA e ausente)? (FR-010) → A: Utilizar um campo numérico para o valor quando existir e um campo separado para identificar o estado do dado (EXATO, TRACO, NA, AUSENTE). Esta spec foca na preservação dos estados; sua interpretação matemática virá na spec de cálculo.
- Q: Como deve funcionar a pesquisa de alimentos e em quais campos ela ocorrerá? (FR-007) → A: Busca parcial focada apenas no nome/descrição, ignorando diferenças entre maiúsculas/minúsculas e acentuação. Buscas avançadas (Full-Text, categoria, ranqueamento) estão fora do escopo inicial.
- Q: Qual é a regra aprovada para o parâmetro de pesquisa `q`? (FR-007) → A: `q` é obrigatório e deve ser normalizado pela remoção de espaços externos antes da validação e antes de ser entregue ao serviço de busca. Após a normalização, quando possuir conteúdo a busca deve ser executada; quando ficar vazio, a requisição deve falhar por validação e nenhuma pesquisa deve ser executada.
- Q: Qual é a fonte autoritativa da TACO para esta feature? (FR-009) → A: O arquivo `resources/data/taco/source/taco_4_edicao_ampliada_e_revisada.pdf`, correspondente à Tabela Brasileira de Composição de Alimentos — TACO, 4ª edição revisada e ampliada, NEPA/UNICAMP, 2011.

## User Scenarios & Testing

<!--
  IMPORTANT: User stories should be PRIORITIZED as user journeys ordered by importance.
  Each user story/journey must be INDEPENDENTLY TESTABLE.
-->

### User Story 1 - Armazenamento de Dados da TACO (Priority: P1)

O sistema deve possuir uma base nutricional formada a partir dos dados dos alimentos provenientes da TACO, preservando as informações necessárias para consulta e uso posterior no NutriLabel.

**Why this priority**: É o alicerce do sistema. Sem a base de alimentos armazenada de forma confiável e fidedigna à fonte, não será possível realizar consultas nem, futuramente, cálculos de receitas.

**Independent Test**: Pode ser testada comparando os dados armazenados com a fonte TACO, verificando se valores e marcadores são preservados sem perda ou conversões indevidas.

**Acceptance Scenarios**:

1. **Given** os dados de um alimento da TACO com valores numéricos, **When** armazenado no sistema, **Then** o sistema preserva os valores originais, categorias e unidades, referenciando a TACO como fonte.
2. **Given** um alimento que possui "Tr" (traço) ou "NA" em determinados nutrientes, **When** armazenado, **Then** o sistema preserva esses marcadores e não os converte para zero.

---

### User Story 2 - Consulta de Alimentos (Priority: P2)

Como usuário, quero pesquisar alimentos pelo nome na base de dados para visualizar sua composição nutricional por 100 g de parte comestível.

**Why this priority**: Permite que os usuários encontrem facilmente os alimentos necessários para uso posterior no sistema.

**Independent Test**: Pode ser testada realizando pesquisas e verificando se os registros retornados correspondem aos nomes pesquisados e exibem corretamente os dados nutricionais preservados.

**Acceptance Scenarios**:

1. **Given** a base populada, **When** o usuário pesquisa por uma palavra-chave presente no nome de um alimento, **Then** o sistema retorna a lista de alimentos correspondentes preservando a descrição e categoria originais.

---

### User Story 3 - Disponibilização para Receitas (Priority: P3)

Como usuário, quero que os alimentos da base possam ser selecionados para uso posterior na montagem de receitas.

**Why this priority**: Estabelece o vínculo funcional necessário para que a futura specification de receitas possa utilizar a base nutricional.

**Independent Test**: Pode ser testada verificando se um alimento consultado pode ser identificado de maneira não ambígua para uso posterior por outras funcionalidades, preservando sua origem e seus dados nutricionais.

**Acceptance Scenarios**:

1. **Given** um alimento encontrado na base, **When** disponibilizado para uso por outras funcionalidades, **Then** ele mantém sua rastreabilidade e seus valores nutricionais por 100 g inalterados.

### Edge Cases

- O que acontece quando um alimento da TACO não possui valores disponíveis para determinados componentes nutricionais?  
  O sistema deve preservar a distinção desse estado conforme a fonte e não inventar nem converter o valor para zero. O dado deve ser identificado como AUSENTE, conforme definido no FR-010.

- Como o sistema deve tratar futuramente valores classificados como `Tr` ou `NA` durante cálculos?  
  Esta specification não define comportamento de cálculo. Essa regra será definida em specification posterior do motor de cálculo.

## Requirements

### Functional Requirements

- **FR-001**: O sistema MUST armazenar alimentos provenientes da TACO, preservando a identificação e descrição do alimento.
- **FR-002**: O sistema MUST preservar a categoria/grupo do alimento e a origem dos dados nutricionais (TACO).
- **FR-003**: O sistema MUST preservar as unidades associadas a cada componente nutricional.
- **FR-004**: O sistema MUST armazenar e representar os valores nutricionais com base na proporção de 100 gramas de parte comestível.
- **FR-005**: O sistema MUST distinguir valores numéricos de valores classificados como traço (`Tr`), não aplicáveis (`NA`) ou dados ausentes.
- **FR-006**: O sistema MUST NÃO converter silenciosamente `Tr`, `NA` ou valores ausentes para zero em nenhuma hipótese.
- **FR-007**: O sistema MUST permitir a pesquisa de alimentos através de busca parcial restrita ao campo de nome/descrição, ignorando obrigatoriamente diferenças de maiúsculas/minúsculas e acentuação. O parâmetro `q` é obrigatório e deve ter os espaços externos removidos antes da validação e antes de ser entregue ao serviço de busca; após essa normalização, quando possuir conteúdo a busca deve ser executada e, quando ficar vazio, a requisição deve falhar por validação e nenhuma pesquisa deve ser executada. Buscas avançadas ou por categoria estão descartadas deste escopo inicial.
- **FR-008**: O sistema MUST permitir que alimentos da base sejam utilizados como ingredientes em receitas futuras.
- **FR-009**: O sistema MUST armazenar a totalidade dos componentes nutricionais descritos e disponíveis na tabela TACO completa, garantindo a preservação integral da base (os componentes efetivamente utilizados no MVP e rotulagem serão definidos em specifications posteriores).
- **FR-010**: O sistema MUST representar a informação nutricional usando campos separados: um numérico puro para armazenar o valor exato (quando aplicável) e um campo descritivo/estado para identificar inequivocamente se o registro é EXATO, TRACO (`Tr`), NÃO APLICÁVEL (`NA`) ou AUSENTE, adiando a interpretação matemática para futuras especificações de cálculo.

### Key Entities

- **Alimento**: conceito que representa um alimento presente na base nutricional, com identificação, descrição, categoria/grupo quando disponível e referência à fonte dos dados.
- **Informação Nutricional**: conjunto de informações nutricionais associadas a um alimento, preservando valores, unidades e os estados especiais existentes na fonte.

## Success Criteria

### Measurable Outcomes

- **SC-001**: 100% dos registros importados/armazenados da TACO devem manter a distinção entre valor numérico zero, `Tr`, `NA` e dado ausente, verificável por comparação com a fonte.
- **SC-002**: Pesquisas por nome de alimento devem retornar resultados consistentes com a descrição da fonte original.
- **SC-003**: O módulo de base nutricional deve funcionar de forma independente das funcionalidades futuras de cálculo e receitas.

## Assumptions

- Esta feature não realiza cálculos de receita, porção, %VD ou rotulagem.
- A base será disponibilizada para consulta e para uso posterior pelas funcionalidades de receitas.
- Autenticação e rotulagem não estão no escopo desta feature.
