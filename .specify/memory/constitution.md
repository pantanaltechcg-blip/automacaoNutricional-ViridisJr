<!--
Sync Impact Report
- Version change: 1.0.0 -> 2.0.0
- Modified Sections:
  - Desenvolvimento Orientado a Specifications (Spec Kit) e Controle de Versão
  - Governance
- Added Sections:
  - Autoridade Administrativa e Integridade Técnica
- Removed Sections: N/A
- Follow-up TODOs:
  - Alinhar a Specification e os artefatos da Feature 004 à governança 2.0.0 antes do Plan.
  - Manter documentados e isolados os bloqueios nutricionais e regulatórios ainda não validados.
-->

# NutriLabel Constitution

## Core Principles

### I. Princípio de Não Invenção (NON-NEGOTIABLE)
Agentes de IA não devem inventar fórmulas nutricionais, valores ausentes, requisitos, funcionalidades não solicitadas ou regras regulatórias. Não devem assumir decisões silenciosas para resolver ambiguidades nem alterar decisões arquiteturais sem aprovação. Se a documentação não fornecer informação suficiente, a dúvida deve ser registrada explicitamente para validação da equipe.

### II. Stack Definida
O projeto utiliza backend em PHP com Laravel, banco de dados PostgreSQL e frontend inicial em HTML/CSS/JavaScript Vanilla (com posterior integração ao Laravel Blade). O backend deve obrigatoriamente aproveitar os recursos nativos do ecossistema Laravel (Eloquent ORM, Migrations, Validation/Form Requests, Controllers e Services). Nenhuma biblioteca ou framework adicional deve ser introduzido sem decisão explícita da equipe.

### III. Arquitetura e Separação de Responsabilidades
O fluxo arquitetural obedece estritamente o caminho: Frontend -> Routes -> Controllers -> Services -> Models -> PostgreSQL.
Controllers são responsáveis apenas pela orquestração. Regras de negócio, cálculos nutricionais e geração visual do rótulo devem ser processados separadamente em Services e jamais concentrados em Controllers ou processados no Frontend.

### IV. Motor de Cálculo
O cálculo nutricional é crítico. O sistema deve separar as etapas: cálculo proporcional de ingredientes, agregação da receita, cálculo por rendimento final, valores por 100g/ml, valores por porção, %VD, valor energético, regras de arredondamento e tratamento de quantidades não significativas. Valores intermediários devem manter precisão suficiente para evitar perda prematura de informação. Arredondamentos e formatações devem ocorrer somente na etapa determinada pelas regras de cálculo e regulatórias aplicáveis. Não devem ser usadas regras genéricas de arredondamento quando houver regra específica documentada.

### V. Dados Nutricionais e Fontes (TACO)
O sistema preservará a rastreabilidade da origem dos dados nutricionais (como a tabela TACO). Valores classificados como traço (Tr), não aplicáveis (NA) ou ausentes não podem ser convertidos silenciosamente para zero. O sistema não deve inferir que todos os nutrientes estão sempre disponíveis e nunca inventará valores faltantes.

### VI. Regras Regulatórias
As regras de rotulagem nutricional devem ser implementadas de forma determinística e testável com base no planejamento e referências fornecidas. Ambiguidade ou ausência de regulação implica a interrupção imediata da implementação daquele aspecto até a validação com a equipe.

## Domínios do Backend
O escopo estabelecido contempla exclusivamente os módulos definidos: autenticação, receitas, ingredientes, base de alimentos, importação TACO, cálculos (100g/ml, porção, %VD), regras de arredondamento/significância, geração e preview da tabela nutricional, rotulagem frontal, exportação e histórico. Esta Constitution proíbe a criação de novos comportamentos para esses domínios sem especificações prévias aprovadas.

## Receitas e Rendimento
Os valores nutricionais da receita são obtidos de seus ingredientes. O sistema deve distinguir explicitamente a quantidade de cada ingrediente, rendimento final da receita, quantidade da embalagem, tamanho e número de porções. Não assuma que o rendimento final é exatamente a soma das massas iniciais; o cálculo obedece às regras específicas documentadas para o projeto.

## Geração do Rótulo
Cálculo nutricional e representação visual são estritamente desacoplados. O motor de cálculo fornece os resultados para a geração e exportação da tabela nutricional (conforme formatos definidos). Regras de formatação visual não devem ser deduzidas ou inventadas pelo agente de IA.

## Banco de Dados e Testes
PostgreSQL é o banco de dados definido para o projeto. Alterações estruturais no banco devem ser realizadas e versionadas por meio de Laravel Migrations. A modelagem deve preservar os relacionamentos entre receitas, ingredientes, alimentos e dados nutricionais, bem como a origem dos dados relevantes para os cálculos. Nenhuma credencial deve ser persistida em repositório. O motor de cálculo nutricional e as regras de rotulagem exigem cobertura por testes automatizados, utilizando a planilha de cálculo da Viridis Jr. como referência técnica base (mas sem sobrescrever as regras regulatórias puras).

## Autoridade Administrativa e Integridade Técnica

O diretor responsável pelo NutriLabel possui autoridade administrativa para aprovar
decisões do projeto, alterar escopo e prioridades e autorizar a passagem entre Specify,
Clarify, Plan, Tasks, Analyze, Implement, validação e entrega. Cada manifestação MUST ser
registrada com data, versão, alcance e origem rastreável. A manifestação executiva de
2026-11-10 registra que a Viridis delegou verbalmente essa autoridade ao diretor; esse
registro não simula assinatura, ata, documento ou validação técnica de terceiro.

Aprovação ou ratificação externa da Viridis não é pré-requisito administrativo para o
diretor autorizar Plan, Tasks ou Implement. Essa autonomia administrativa não constitui,
por si só, equivalência nutricional, aprovação de fator energético, autorização de
conversão, confirmação de unidade, fonte quantitativa ou conformidade normativa. Toda
regra sem fundamento técnico suficiente MUST permanecer documentada como pendente,
indisponível ou bloqueada, conforme o caso.

Pendências nutricionais e regulatórias não bloqueiam automaticamente a infraestrutura
nem comportamentos independentes e seguros. O desenvolvimento pode prosseguir quando a
Specification define de modo testável o isolamento da pendência e garante que:

- dado desconhecido, qualitativo, incompatível ou ausente nunca seja convertido em zero
  ou em valor fictício;
- cálculo dependente de correspondência, fator, conversão, fonte ou aplicabilidade não
  validada permaneça indisponível;
- resultado parcial ou incompleto preserve suas causas e nunca seja classificado como
  completo, validado ou apto à rotulagem;
- valores originais, fontes, versões, decisões e resultados históricos permaneçam
  rastreáveis e imutáveis;
- testes automatizados comprovem tanto os caminhos autorizados quanto os bloqueios
  seguros.

## Desenvolvimento Orientado a Specifications (Spec Kit) e Controle de Versão
Todas as alterações seguem o Spec-Driven Development: Constitution -> Specify -> Clarify
-> Plan -> Tasks -> Analyze -> Implement -> Validação. O diretor pode autorizar a passagem
entre etapas quando os critérios documentais e técnicos aplicáveis estiverem testáveis.
Nenhum requisito ausente deve ser inventado, e nenhuma regra técnica bloqueada pode ser
implementada como se estivesse validada. Partes independentes e seguras podem avançar
quando a Specification define explicitamente o comportamento de indisponibilidade,
incompletude e rastreabilidade. O versionamento via Git segue a esteira de `main` <-
`develop` <- `feature branches`, exigindo revisão antes da integração de funcionalidades.

## Governance
Esta Constitution governa as regras técnicas e administrativas do projeto NutriLabel. A
precedência hierárquica é: Constitution -> Specification -> Plan -> Tasks ->
Implementação.

O diretor do projeto é a autoridade responsável por aprovar emendas constitucionais,
mudanças de escopo, prioridades, critérios de implementação, passagem entre etapas e
preparação da entrega. A autorização MUST identificar o conteúdo aprovado, sua versão,
data, alcance, origem e eventuais ressalvas. Aprovação administrativa não substitui a
evidência técnica ou normativa que um cálculo ou uma declaração regulatória exige.

Emendas a esta Constitution MUST ser realizadas antes da aplicação da nova governança,
incluir relatório de impacto e atualizar a versão segundo SemVer: MAJOR para remoção ou
redefinição incompatível de governança, MINOR para novo princípio ou expansão material
compatível e PATCH para esclarecimento sem alteração semântica. Toda revisão de
Specification, Plan, Tasks ou implementação MUST verificar conformidade com a versão
vigente e registrar desvios ou bloqueios. Nenhuma expansão de escopo pode ocorrer sem
manifestação rastreável do diretor.

**Version**: 2.0.0 | **Ratified**: 2026-10-05 | **Last Amended**: 2026-11-10
