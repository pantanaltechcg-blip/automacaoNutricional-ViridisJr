# Feature Specification: taco-nutrition-base

**Feature Branch**: `001-taco-nutrition-base`

**Created**: 2026-10-05

**Status**: Draft

**Input**: User description: "Crie a primeira specification do projeto NutriLabel para a funcionalidade de Base Nutricional com uso da TACO..."

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
  O sistema deve preservar a distinção desse estado conforme a fonte e não inventar nem converter o valor para zero. A representação interna será definida posteriormente.

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
- **FR-007**: O sistema MUST permitir a pesquisa/consulta de alimentos pelo nome.  
  `[NEEDS CLARIFICATION: Como deve funcionar a pesquisa de alimentos? Ex.: busca exata, aproximada, parcial por nome? Em quais campos a busca deve ocorrer?]`
- **FR-008**: O sistema MUST permitir que alimentos da base sejam utilizados como ingredientes em receitas futuras.
- **FR-009**: O sistema MUST definir o conjunto inicial de componentes nutricionais.  
  `[NEEDS CLARIFICATION: Quais componentes nutricionais da TACO devem compor o escopo inicial do sistema? Ex.: macronutrientes, micronutrientes específicos ou todos os componentes necessários ao projeto?]`
- **FR-010**: O sistema MUST garantir uma forma consistente de distinguir os estados dos valores provenientes da TACO.  
  `[NEEDS CLARIFICATION: Como tratar ou representar internamente marcadores específicos da TACO como Tr, NA e ausente, mantendo sua distinção sem perda de informação?]`

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