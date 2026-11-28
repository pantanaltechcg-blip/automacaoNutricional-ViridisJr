# Tasks: Área de Rótulos

**Input**: Design documents from `/specs/009-label-workspace/`

**Prerequisites**: `plan.md`, `spec.md`, `research.md`, `data-model.md`, `contracts/ui-contracts.md`, `quickstart.md`.

**Tests**: Incluídos porque a Specification exige validação independente e o projeto requer testes automatizados.

**Organization**: Tarefas agrupadas pela única história de usuário da Specification.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode ser executada em paralelo sem dependências em arquivos/tarefas pendentes.
- **[Story]**: história de usuário correspondente à Specification.
- Cada tarefa aponta os caminhos exatos dos arquivos envolvidos.

## Phase 1: Setup

**Purpose**: Confirmar que a estrutura Laravel existente comporta a tela sem novas dependências.

- [X] T001 Confirmar Laravel, PHPUnit e estrutura web existentes em `composer.json`, `phpunit.xml` e `routes/web.php`; não adicionar dependências nem configuração de autenticação.

## Phase 2: Foundational

**Purpose**: Confirmar as entidades e a navegação existente que serão reutilizadas; não há infraestrutura ou schema novo nesta Feature.

- [X] T002 Verificar os campos e a relação `causes` em `app/Models/DeclaredNutritionCalculation.php`, `app/Models/DeclaredNutritionCause.php`, `database/migrations/2026_11_12_000007_create_declared_nutrition_calculations_table.php` e `database/migrations/2026_11_12_000012_create_declared_nutrition_causes_table.php` para confirmar que a listagem pode ser somente de leitura.
- [X] T003 Verificar a rota existente `labels.assembly` em `routes/web.php` e os padrões de tela em `app/Http/Controllers/LabelAssemblyScreenController.php` e `resources/views/layouts/rotulonutri.blade.php` antes da integração da nova listagem.

## Phase 3: User Story 1 — Consultar execuções de rótulo (Priority: P1) 🎯 MVP

**Goal**: Listar somente execuções declaradas já preservadas, com identificação, prontidão e causas, permitindo abrir a montagem existente; remover Configurações da navegação.

**Independent Test**: Com banco de teste isolado, GET `/rotulos` mostra estado vazio sem inserir dados ou lista paginada das execuções persistidas, incluindo IDs/origens, data, status e todas as causas; o link abre a execução existente; nenhuma execução/snapshot é modificada; Rótulos navega para a lista e Configurações não aparece.

### Tests for User Story 1

- [X] T004 [US1] Criar em `tests/Feature/Frontend/LabelIndexScreenTest.php` testes para resposta da lista vazia, ausência de escrita e ausência de valores/amostras fictícias.
- [X] T005 [US1] Acrescentar em `tests/Feature/Frontend/LabelIndexScreenTest.php` testes de múltiplas execuções, ordenação determinística, paginação, exibição de IDs de origem, prontidão e todas as causas, sem dependência do nome atual da Recipe.
- [X] T006 [US1] Acrescentar em `tests/Feature/Frontend/LabelIndexScreenTest.php` testes dos links de navegação: Rótulos aponta à listagem, Configurações não é renderizada e a montagem aponta à execução real selecionada.

### Implementation for User Story 1

- [X] T007 [US1] Implementar `app/Services/LabelExecutionListingService.php` para consultar `DeclaredNutritionCalculation` somente para leitura, eager-load da relação `causes`, ordenar `created_at DESC` e `id DESC` e paginar 25 registros; não consultar Recipe, executar cálculos ou escrever dados.
- [X] T008 [US1] Implementar `app/Http/Controllers/LabelIndexScreenController.php` para orquestrar a listagem usando `LabelExecutionListingService` e retornar a view, sem acesso direto a Models nem regras de domínio.
- [X] T009 [US1] Registrar em `routes/web.php` a rota nomeada `labels.index` para `GET /rotulos`, sem middleware de autenticação e sem alterar a rota existente `labels.assembly`.
- [X] T010 [US1] Criar `resources/views/rotulonutri/labels.blade.php` com estado vazio e lista paginada, apresentando cada ID da execução, IDs de origem, data, `is_label_ready` e todas as causas persistidas; incluir link de montagem da própria execução sem recalcular ou alterar dados.
- [X] T011 [US1] Atualizar `resources/views/layouts/rotulonutri.blade.php` para apontar “Rótulos” à rota `labels.index`, indicar estado ativo na listagem e remover integralmente “Configurações”; alinhar o teste existente em `tests/Feature/Frontend/ShellTest.php` à navegação aprovada.

**Checkpoint**: a consulta de execuções deve ser funcional e testável sem criar ou modificar dados e sem liberar resultados regulatórios bloqueados.

## Phase 4: Polish & Cross-Cutting Concerns

**Purpose**: Verificação final da história e de regressões das telas existentes.

- [X] T012 Executar os testes de `tests/Feature/Frontend/LabelIndexScreenTest.php`, os testes relacionados em `tests/Feature/Frontend` e a suíte `php artisan test`; corrigir somente falhas introduzidas por T004–T011.
- [X] T013 Executar `vendor\bin\pint --test`, `git diff --check` e validar manualmente o roteiro em `specs/009-label-workspace/quickstart.md`; registrar os resultados sem alterar escopo.

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: imediato; nenhuma dependência nova será instalada.
- **Foundational (Phase 2)**: depende de T001 e confirma a compatibilidade com o schema/rotas existentes; não cria migrations.
- **User Story 1 (Phase 3)**: depende de T001–T003; testes T004–T006 precedem a implementação T007–T011.
- **Polish (Phase 4)**: depende da implementação da US1.

### User Story Dependencies

- **US1 (P1)**: única história; pode ser entregue independentemente e não altera Features 001–008.

### Parallel Opportunities

- Não há paralelismo seguro obrigatório nesta história: os três testes modificam o mesmo arquivo, e a rota/view compartilham o contrato entre controller e apresentação.

## Parallel Example: User Story 1

```text
Executar T004–T006 sequencialmente no arquivo tests/Feature/Frontend/LabelIndexScreenTest.php.
Depois integrar T007–T011 respeitando as dependências entre serviço, controller, rota, view e layout.
```

## Implementation Strategy

### MVP First (US1)

1. Confirmar Setup e Foundation (T001–T003).
2. Escrever testes da história (T004–T006) e verificar que falham antes da implementação.
3. Implementar serviço de consulta, Controller, rota, view e navegação (T007–T011).
4. Validar isoladamente a US1 e executar regressões/verificações (T012–T013).

### Incremental Delivery

A Feature possui apenas uma história. O MVP é a tela de listagem de execuções reais com causas visíveis, estado vazio honesto, navegação existente para montagem e ausência do item Configurações.

## Notes

- Nenhuma task cria Model, migration, tabela, API, cálculo, fixture real ou configuração regulatória.
- Sem autenticação nesta etapa; não expor a aplicação publicamente.
- Não marcar o status `is_label_ready` como homologação regulatória externa.
