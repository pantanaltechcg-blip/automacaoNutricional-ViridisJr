# Feature Specification: Governed Manual Food Registration

**Feature Branch**: `006-manual-food-registration`

**Created**: 2026-11-17

**Status**: Clarified — Ready for `/speckit-plan` under Constitution 2.0.0

## Clarifications

### Session 2026-11-17

- Q: Como o sistema deve tratar atualizações e a eventual desativação de um alimento manual já utilizado em receitas ou cálculos anteriores? → A: Cada atualização gera uma nova versão imutável do alimento manual (mantendo versões anteriores preservadas para histórico) e permite inativação lógica para novos cadastros em receitas, mantendo receitas e cálculos antigos intactos.
- Q: Qual é o conjunto mínimo de nutrientes e a compatibilidade de base de referência para o cadastro manual? → A: Exigir ao menos 1 nutriente do catálogo oficial informado (valor numérico exato não negativo sem arredondamento ou estado qualitativo explícito `TRACE`, `NA`, `ABSENT`, `REEVALUATION`), mantendo os demais nutrientes não informados como ausentes e preservando a compatibilidade dimensional com os ingredientes de receita sem conversão automática de densidade.
- Q: Quais metadados mínimos de origem, evidência e identidade única devem ser exigidos no cadastro manual? → A: Exigir identificação da fonte distinta de `TACO`, código da fonte, referência documental/evidência, fonte da evidência, responsável e data de registro, bloqueando estritamente duplicidades por identidade exata normalizada (fonte + código e nome normalizado na mesma fonte).

## Clarification Status

- **CQ-001 — Disponibilidade e ciclo de vida (resolvida)**: Alimentos simples cadastrados manualmente com todos os campos obrigatórios válidos ficam imediatamente disponíveis na busca e para uso em receitas. Atualizações geram novas versões imutáveis do alimento manual, preservando as versões anteriores e permitindo inativação lógica para impedir seleção em novas receitas enquanto receitas e cálculos históricos permanecem intactos.
- **CQ-002 — Escopo de alimento simples e composição nutricional mínima (resolvida)**: O escopo contempla exclusivamente alimentos simples com composição nutricional por `100 g` (compatível dimensionalmente com os ingredientes de receita em `g`, sem conversão automática de densidade; referência em `100 ml` permanece bloqueada/fora desta fase enquanto ingredientes de receita forem exclusivamente em massa). Deve haver ao menos um nutriente do catálogo oficial informado com valor numérico exato não negativo ou estado qualitativo explícito (`TRACE`, `NA`, `ABSENT`, `REEVALUATION`), permanecendo os demais nutrientes como não informados/ausentes, sem jamais converter ausência ou estado qualitativo em zero.
- **CQ-003 — Identidade, fonte/evidência e prevenção de duplicidades (resolvida)**: Todo alimento manual exige origem distinta de `TACO`, código identificador na fonte, referência e fonte da evidência nutricional, responsável e data do registro. O sistema aplica bloqueio estrito contra duplicidades por identidade exata normalizada (combinação de fonte + código da fonte e nome normalizado dentro da mesma fonte) e proíbe qualquer criação ou edição com origem `TACO`.

**Input**: User description: "opção A" — Cadastro manual governado de alimentos ausentes da TACO (`specs/backlog/manual-food-registration.md`)

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Cadastrar alimento ausente da TACO com origem e composição rastreáveis (Priority: P1)

Como responsável técnico ou operador autorizado do NutriLabel, quero cadastrar manualmente um alimento que não exista na base oficial TACO, informando sua identificação, origem dos dados nutricionais, evidência associada e composição nutricional na base de referência de 100 g, para que novos ingredientes reais possam ser registrados no sistema sem alterar a base TACO.

**Why this priority**: Sem o registro governado do alimento ausente da TACO e de sua composição nutricional rastreável, receitas que dependem de ingredientes fora da TACO ficam bloqueadas ou incompletas.

**Independent Test**: Pode ser testada cadastrando um novo alimento com dados de identificação, fonte nutricional, evidência e valores nutricionais (incluindo valores exatos e estados qualitativos ou ausentes) e verificando que o registro é salvo de forma isolada da base TACO, preservando exatamente a origem e os estados informados.

**Acceptance Scenarios**:

1. **Given** um alimento ausente da base TACO com nome, categoria, fonte de dados nutricionais, evidência documental/referência e composição nutricional informada na base de 100 g, **When** o usuário submete o cadastro manual, **Then** o sistema registra o novo alimento com identificação própria, origem rastreável e valores nutricionais preservados sem modificar nenhum registro da base TACO.
2. **Given** um alimento cadastrado manualmente com nutrientes em estado qualitativo (`TRACE`, `NA`, `ABSENT`, `REEVALUATION`) ou não informados para determinados nutrientes, **When** o cadastro é salvo, **Then** o sistema preserva cada estado qualitativo ou ausência explicitamente, sem converter nenhum dado ausente ou qualitativo em zero numérico.
3. **Given** uma tentativa de cadastro manual sem identificação da fonte dos dados nutricionais ou sem base de referência válida, **When** o usuário tenta salvar o alimento, **Then** o sistema rejeita o cadastro e informa claramente os campos obrigatórios ausentes.

---

### User Story 2 - Controlar duplicidades e disponibilidade imediata na busca e em receitas (Priority: P2)

Como responsável pela governança dos dados nutricionais, quero garantir que alimentos cadastrados manualmente de forma completa fiquem imediatamente disponíveis na busca e em receitas, enquanto tentativas de cadastro com identidade exata duplicada em relação à TACO ou ao catálogo manual sejam bloqueadas.

**Why this priority**: Garantir disponibilidade operacional ágil após validação dos dados obrigatórios sem permitir duplicidades exatas protege a integridade e a consistência do catálogo de ingredientes.

**Independent Test**: Pode ser testada cadastrando um alimento manual completo e verificando sua disponibilidade imediata na busca e na criação de receitas, bem como tentando cadastrar um alimento com identidade exata já existente na TACO ou no catálogo manual e verificando o bloqueio.

**Acceptance Scenarios**:

1. **Given** um alimento simples cadastrado manualmente com todos os campos obrigatórios válidos (identificação, fonte, evidência e composição por 100 g), **When** o cadastro é concluído, **Then** o alimento fica imediatamente disponível na busca e para associação como ingrediente em receitas, com indicação explícita de sua origem não-TACO.
2. **Given** a tentativa de cadastrar manualmente um alimento com identidade exata conflitante (nome normalizado e fonte/código idênticos) com um alimento já existente na base TACO ou no catálogo manual, **When** o cadastro é submetido, **Then** o sistema bloqueia estritamente o cadastro duplicado com mensagem explicativa e não sobrescreve o alimento existente.

---

### User Story 3 - Versionar correções futuras preservando cálculos e snapshots históricos (Priority: P3)

Como auditor ou responsável técnico, quero que correções ou atualizações na composição nutricional de um alimento cadastrado manualmente afetem apenas versões e cálculos futuros, mantendo integralmente inalterados todos os cálculos nutricionais, porções e valores declarados históricos já produzidos.

**Why this priority**: A imutabilidade histórica é um princípio central do NutriLabel; qualquer alteração posterior em um alimento manual jamais pode alterar retroativamente resultados já emitidos.

**Independent Test**: Pode ser testada executando um cálculo nutricional com um alimento manual aprovado, atualizando em seguida um valor nutricional desse alimento e verificando que o cálculo histórico anterior permanece idêntico enquanto um novo cálculo utiliza a versão atualizada.

**Acceptance Scenarios**:

1. **Given** um alimento manual já utilizado em um cálculo nutricional histórico preservado, **When** um usuário autorizado corrige ou atualiza um valor nutricional ou metadado de fonte desse alimento, **Then** o sistema registra a alteração com rastreabilidade de versão e origem sem modificar nenhum resultado histórico já calculado.
2. **Given** uma alteração realizada em um alimento manual, **When** uma nova execução de cálculo é solicitada para uma receita que utiliza esse alimento, **Then** o novo cálculo utiliza a versão vigente autorizada do alimento e preserva o vínculo rastreável com a versão utilizada.

---

### Edge Cases

- O que acontece quando um usuário tenta editar ou excluir um alimento oficial da base TACO através do fluxo de cadastro manual? O sistema deve bloquear qualquer alteração ou exclusão de alimentos da base TACO.
- O que acontece quando são informados valores nutricionais negativos, unidades incompatíveis com o nutriente do catálogo ou valores qualitativos misturados com valor numérico no mesmo nutriente? O sistema deve rejeitar a inconsistência com mensagem clara.
- O que acontece quando um alimento manual possui apenas parte dos nutrientes do catálogo preenchida? Os nutrientes não informados devem permanecer ausentes/não informados (nunca convertidos para zero), preservando o comportamento seguro de incompletude nos cálculos futuros.
- O que acontece quando um alimento manual aprovado é posteriormente reprovado, suspenso ou corrigido? Snapshots históricos existentes permanecem intocados, e novos usos seguem o estado vigente do alimento.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: O sistema MUST permitir o cadastro manual de novos alimentos ausentes da base oficial TACO, atribuindo-lhes origem distinta da TACO e impedindo qualquer modificação ou sobrescrita dos registros originais da TACO.
- **FR-002**: O sistema MUST exigir, no cadastro manual, a identificação do alimento (nome obrigatório e categoria opcional), a origem/fonte dos dados nutricionais distinta de `TACO` (fonte e código identificador na fonte), a evidência associada (referência documental e fonte da evidência), a identificação do responsável e data do registro, e a unidade de referência nutricional (`100 g`, com suporte explícito de compatibilidade dimensional sem conversão automática de densidade).
- **FR-003**: O sistema MUST contemplar exclusivamente alimentos simples com composição nutricional informada diretamente por base de referência (`100 g`), deixando ingredientes compostos ou industrializados (com lista de ingredientes, dados específicos de fabricante ou sub-receitas) fora do escopo desta feature.
- **FR-004**: O sistema MUST exigir ao menos um nutriente do catálogo oficial existente informado na composição do alimento manual, preservando exatamente a unidade de cada nutriente e suportando tanto valores numéricos exatos não negativos (sem arredondamento) quanto estados qualitativos explícitos (`TRACE`, `NA`, `ABSENT`, `REEVALUATION`), mantendo nutrientes não informados como ausentes e sem nunca converter ausência ou estado qualitativo em zero.
- **FR-005**: O sistema MUST rejeitar a criação de nutrientes inexistentes no catálogo oficial do sistema ou a atribuição automática de valores sintéticos ou aproximados.
- **FR-006**: O sistema MUST aplicar bloqueio estrito de duplicidade por identidade exata normalizada (combinação de fonte e código na fonte, bem como nome normalizado sem distinção de maiúsculas/minúsculas ou acentuação dentro da mesma fonte) em relação aos alimentos já existentes na base TACO e no catálogo manual, além de proibir o uso da origem `TACO`, impedindo a criação de registros duplicados e preservando os existentes sem sobrescrita.
- **FR-007**: O sistema MUST tornar o alimento simples cadastrado manualmente disponível imediatamente na busca e para uso em receitas assim que o cadastro completo e válido for concluído, suportando inativação lógica para que alimentos inativos não sejam selecionáveis em novas receitas enquanto receitas e cálculos anteriores permanecem intactos.
- **FR-008**: O sistema MUST gerar uma nova versão imutável a cada atualização do alimento manual, preservando as versões anteriores e registrando rastreabilidade completa de origem, evidência, responsável, data e motivo/versão da alteração.
- **FR-009**: O sistema MUST garantir que correções, novas versões ou inativações futuras de um alimento cadastrado manualmente afetem exclusivamente novos cálculos, preservando integralmente todos os cálculos nutricionais, porções e valores declarados históricos já produzidos com versões anteriores.
- **FR-010**: O sistema MUST permitir consultar e buscar alimentos cadastrados manualmente de forma integrada ou filtrável em relação aos alimentos da TACO, deixando explícita a origem de cada alimento retornado.

### Key Entities *(include if feature involves data)*

- **Alimento Manual (extensão governada de Alimento)**: Representa um alimento ausente da TACO cadastrado manualmente, com nome, categoria, natureza do alimento, origem distinta da TACO, estado de aprovação/disponibilidade e versão vigente.
- **Fonte e Evidência Nutricional do Alimento**: Registra a procedência dos dados informados (ex.: laudo laboratorial, rótulo de fabricante, tabela de referência), referência/evidência associada, responsável pelo registro ou aprovação e data.
- **Composição Nutricional do Alimento Manual**: Associa o alimento manual aos nutrientes do catálogo existente, preservando valor numérico exato (quando aplicável), estado qualitativo (`EXACT`, `TRACE`, `NA`, `ABSENT`, `REEVALUATION`) e unidade oficial do nutriente na base de 100 g.
- **Histórico / Versão do Alimento Manual**: Preserva o histórico de alterações e revisões do alimento manual para garantir auditabilidade e imutabilidade dos cálculos históricos que referenciam versões anteriores.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% dos alimentos cadastrados manualmente preservam sua origem distinta da TACO, sua evidência associada e os estados nutricionais originais sem conversão silenciosa de ausência ou estado qualitativo para zero.
- **SC-002**: 100% dos registros originais da base TACO e 100% dos cálculos históricos previamente executados permanecem inalterados após qualquer operação de cadastro, correção ou inativação de alimentos manuais.
- **SC-003**: Usuários autorizados conseguem concluir o cadastro completo de um alimento ausente da TACO (com identificação, fonte e composição nutricional básica) em uma única operação guiada, com feedback imediato de validação para campos ausentes ou inconsistentes.
- **SC-004**: Alimentos manuais elegíveis tornam-se localizáveis na busca e utilizáveis em receitas imediatamente após atenderem às regras de disponibilidade definidas, com identificação clara de sua origem não-TACO.

## Assumptions

- O catálogo oficial de nutrientes já existente no sistema (66 nutrientes importados na Feature 001) permanece como referência única de nutrientes e unidades suportadas, sem criação automática de novos nutrientes nesta feature.
- A gestão de autenticação e papéis de usuário será tratada conforme a política de acesso vigente do projeto (ou módulo próprio de autenticação previsto na Constitution), assumindo que a identificação do responsável/autor da operação pode ser registrada no fluxo.
- Valores numéricos informados manualmente mantêm precisão exata nas etapas de cálculo subsequentes, seguindo os mesmos princípios de aritmética exata já adotados no NutriLabel.
- Alimentos cadastrados manualmente continuam sujeitos aos mesmos gates regulatórios das Features 004 e 005 quando utilizados em receitas (ex.: indisponibilidade de nutrientes obrigatórios ou bloqueios de correspondência não são contornados pelo cadastro manual).
