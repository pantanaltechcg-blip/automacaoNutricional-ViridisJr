# Tasks: Frontend do Protótipo RótuloNutri

**Input**: Design documents from `/specs/008-frontend-prototype-ui/`
**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md), [data-model.md](./data-model.md), [contracts/ui-contracts.md](./contracts/ui-contracts.md), [quickstart.md](./quickstart.md)
**Tests**: INCLUÍDOS (NFR-008; Constituição exige testes dos caminhos autorizados e dos bloqueios). Escrever cada teste antes da implementação correspondente e vê-lo falhar.
**Organization**: por user story (spec.md §4): US1 Receita (P1), US2 Tabela/bloqueios (P1), US3 Cadastro de ingrediente (P2), US4 Rótulo (P3).

## Format: `[ID] [P?] [Story] Description`
- **[P]**: paralelizável (arquivos distintos, sem dependência pendente)
- **[Story]**: US1–US4 (apenas nas fases de user story)

## Regras permanentes (valem para todas as tarefas)
- **PROIBIDO alterar**: `app/Models/*`, `app/Services/*` existentes, `app/Http/Controllers/*` existentes, `app/Http/Requests/*`, `app/Http/Resources/*`, `database/**`, `routes/api.php`, `specs/001–007/**`.
- **Permitido**: novos controllers/service de apresentação, `routes/web.php` (apenas acréscimo; rota `/` intacta), views Blade, `resources/css/app.css` (apenas acréscimo em `@theme`), `resources/js/**`, `tests/**`, `specs/008-frontend-prototype-ui/**`.
- Sem novas dependências (Composer/npm). Sem IDs regulatórios criados/fixados/mockados. Nenhum cálculo, arredondamento ou conversão no cliente. Vazio ≠ 0.
- Criar PHP com `php artisan make:*` e `--no-interaction`; ao final rodar `vendor/bin/pint --dirty --format agent`.

---

## Phase 1: Setup

- [X] T001 Verificar baseline: `git status` e suíte pré-frontend documentados no PR #8 da Feature 006 (222 testes, 27.591 asserções; commit-base `85825fc`, pai do primeiro commit frontend `96a383b`), antes de qualquer alteração (referência para NFR-011).
- [X] T002 [P] Amostrar as cores do protótipo em `docs/referencias/pagina-01.png`…`pagina-04.png` e definir os tokens (sidebar escura, verde primário, verde-claro de destaque, fundo esverdeado, campos, borda, vermelho do ×) em `@theme` de `resources/css/app.css` (acréscimo apenas; `welcome` inalterada; fonte Instrument Sans mantida, D-01/D-02).
- [X] T003 [P] Criar a estrutura de diretórios vazia: `resources/views/layouts/`, `resources/views/components/rotulonutri/`, `resources/views/rotulonutri/`, `resources/js/rotulonutri/pages/`, `tests/Feature/Frontend/`, `tests/Js/`.

---

## Phase 2: Foundational (bloqueia todas as user stories)

**⚠️ CRITICAL**: nenhuma user story começa antes do fim desta fase.

### Testes (primeiro)
- [X] T004 [P] Teste JS de `decimal` em `tests/Js/decimal.test.mjs` (`node --test`): normaliza `1,5`→`1.5`; rejeita notação científica; `+1` exato (`0.1`+1=`1.1`, `""`+1=`1`); `−` só retorna valor > 0, caso contrário `null`; soma exata (`0.1`+`0.2`=`0.3`); nunca usa `Number`.
- [X] T005 [P] Teste JS de `errors` em `tests/Js/errors.test.mjs`: 422 `{errors}` → lista `{field,message}`; chaves `ingredients.2.quantity` e `nutrients.1.amount_value` → índice; 5xx/rede/timeout → erro geral com `retryable: true`.
- [X] T006 [P] Teste JS de `draft` em `tests/Js/draft.test.mjs` (com `sessionStorage` simulado): grava/lê `rotulonutri.recipeDraft` e `rotulonutri.stageReport`; porção/medida caseira nunca aparecem no payload de receita.
- [X] T007 [P] Teste Feature do shell em `tests/Feature/Frontend/ShellTest.php` (`php artisan make:test --phpunit Frontend/ShellTest --no-interaction`): `GET /receitas/nova` retorna 200 com "Receitas", "Rótulos", "Configurações", logotipo e stepper de 3 etapas; "Rótulos" e "Configurações" marcados indisponíveis; `GET /` continua retornando 200.

### Implementação
- [X] T008 [P] Implementar `resources/js/rotulonutri/decimal.js` (BigInt/string; sem `Number`) conforme T004 e research D-05.
- [X] T009 [P] Implementar `resources/js/rotulonutri/errors.js` conforme T005 e contrato §5.
- [X] T010 [P] Implementar `resources/js/rotulonutri/api.js`: `fetch` JSON para `/api/*`, `Accept: application/json`, `AbortController`, timeout, normalização via `errors.js`; sem endpoints além dos de contracts §2.
- [X] T011 [P] Implementar `resources/js/rotulonutri/draft.js` (`sessionStorage`, chaves do data-model §2) conforme T006.
- [X] T012 [P] Implementar `resources/js/rotulonutri/regulatory-references.js` com `resolveRegulatoryReferences()` retornando `{available:false, cause}` (sem endpoint/seed/mecanismo documentado; D-06). Nenhum ID literal.
- [X] T013 Criar `resources/views/layouts/rotulonutri.blade.php`: sidebar escura (logotipo "Rótulo**Nutri**", itens Receitas/Rótulos/Configurações; os dois últimos indisponíveis com `aria-disabled` e explicação), cabeçalho (título + subtítulo "Base de dados: Tabela TACO"), `@fonts`, `@vite(['resources/css/app.css','resources/js/app.js'])`, `<body data-page>`; fiel a `pagina-01.png`.
- [X] T014 [P] Criar componentes Blade em `resources/views/components/rotulonutri/`: `stepper.blade.php` (3 pílulas; etapa atual; desabilitada quando inalcançável), `unavailable.blade.php` (aviso de indisponibilidade/bloqueio), `field.blade.php` (rótulo + input + área de erro), `nutrition-table.blade.php` (10 linhas × colunas 100 g/Porção/%VD*, entrada `null` ⇒ "—" com descrição acessível; contracts §7).
- [X] T015 Atualizar `resources/js/app.js` com roteador por `document.body.dataset.page` e import dinâmico `./rotulonutri/pages/{page}.js` (sem alterar `vite.config.js`).
- [X] T016 Criar `RecipeScreenController` com `php artisan make:controller RecipeScreenController --no-interaction`, método `create` retornando a view `rotulonutri.recipe` (view mínima com layout + stepper); registrar `GET /receitas/nova` (`recipes.create`) em `routes/web.php` sem alterar a rota `/`.

**Checkpoint**: T004–T007 verdes; shell renderiza; `npm run build` compila.

---

## Phase 3: User Story 1 — Montar e salvar a receita (P1) 🎯 MVP

**Goal**: Tela 1 completa (`pagina-01.png`): dados da receita, busca TACO, ingredientes com −/+, total informativo e salvamento.
**Independent Test**: preencher Tela 1, buscar "arroz", adicionar 2 ingredientes, salvar e reabrir a receita pelo `id` (quickstart §2.1–2.4, 2.6).

### Testes
- [X] T017 [P] [US1] Teste JS `tests/Js/payloads.test.mjs`: `buildRecipePayload(draft)` ⇒ `yield_unit` `g`/`ml` conforme tipo; `quantity` string com ponto; `unit:"g"`; `position` 1..n; **sem** porção, medida caseira nem total informativo; ingrediente com quantidade vazia/≤0 não é enviado como 0 (retorna erro local de presença).
- [X] T018 [P] [US1] Teste Feature `tests/Feature/Frontend/RecipeScreenTest.php`: no HTML inicial da Tela 1, validar a caixa estática "Ingrediente não está no banco de dados?" + "Cadastre manualmente em uma tela separada." e a ação "+ Cadastrar novo ingrediente"; a ação dinâmica de cadastro permanece implementada após os resultados da busca; a carga inicial não apresenta valor de kcal.

### Implementação
- [X] T019 [US1] Implementar `resources/js/rotulonutri/payloads.js` (`buildRecipePayload`) conforme T017 e contracts §3.
- [X] T020 [US1] Construir `resources/views/rotulonutri/recipe.blade.php` fiel a `pagina-01.png`: cartões "Informações da receita" e "Ingredientes e quantidades", rádios de tipo, campos, lista de resultados com etiqueta de origem, linhas de ingrediente (−, campo, +, "g", texto de indisponibilidade de "auto kcal", ×), barra "Total informativo dos ingredientes", caixa tracejada, botão "Gerar tabela nutricional".
- [X] T021 [US1] Implementar `resources/js/rotulonutri/pages/recipe.js`: (a) restaurar/gravar rascunho; (b) trocar rótulos de unidade por tipo (CQ-001, sem conversão); (c) busca com debounce 300 ms, termo vazio sem chamada, `AbortController`, exibição de `name`+`source`, ação final de cadastro manual (leva a `/ingredientes/novo` preservando rascunho); (d) adicionar/remover ingredientes (mesmo alimento pode repetir); (e) −/+ via `decimal.js`; (f) total informativo via `decimal.js`, nunca enviado; (g) "auto kcal" = texto de indisponibilidade.
- [X] T022 [US1] Em `pages/recipe.js`, implementar o salvamento: `POST /api/recipes` (ou `PUT /api/recipes/{id}` quando houver `recipeId`), exibir 422 nos campos/linhas (contracts §5), erros de rede/5xx com "Tentar novamente", preservando dados digitados; a falha de etapas posteriores NUNCA impede a receita salva.
- [X] T023 [US1] Mensagens locais de presença mínima (campos exigidos pelo endpoint ausentes) sem duplicar regras de conteúdo; validação autoritativa permanece do servidor (FR-026).

**Checkpoint**: US1 funcional e testável sozinha (receita salva e reaberta por `id`).

---

## Phase 4: User Story 2 — Gerar a tabela e entender os bloqueios (P1)

**Goal**: botão "Gerar tabela nutricional" executa receita → Feature 003 → interrupção explícita na 004; Tela 2 em estado bloqueado (`pagina-02.png`); suporte a renderizar execução 005 quando existir.
**Independent Test**: com o backend atual, gerar e verificar receita salva, 003 executada, 004 bloqueada com causa e nenhuma tabela concluída (quickstart §2.5).

### Testes
- [X] T024 [P] [US2] Teste JS `tests/Js/pipeline.test.mjs` (fetch simulado): receita OK + 003 OK + `resolveRegulatoryReferences` indisponível ⇒ estágios `RECIPE_SAVED=DONE`, `CALC_003=DONE`, `SERVING_004=BLOCKED` (causa), `DECLARED_005=BLOCKED`; **nenhuma chamada** a 004/005; falha na receita ⇒ 003 `SKIPPED`; falha 003 ⇒ `FAILED`; com stub de referências disponíveis (apenas no teste) ⇒ chama 004 com o corpo do contrato e 005 em seguida; porção/medida caseira só entram no corpo da 004.
- [X] T025 [P] [US2] Teste Feature `tests/Feature/Frontend/NutritionTableScreenTest.php`: `GET /receitas/{recipe}/tabela` (Recipe via factory) retorna 200 com "Tabela Nutricional", "INFORMAÇÃO NUTRICIONAL", as 10 linhas, colunas "100 g", "Porção", "%VD*", "Porções por embalagem: —", "Modelo do cliente", "Vertical (padrão)", "Aplicar ao rótulo", "Voltar à receita"; receita inexistente ⇒ 404; nenhuma célula com "0" fictício.
- [X] T026 [P] [US2] Teste JS `tests/Js/table-render.test.mjs`: dada uma execução 005 de fixture, `renderDeclaredTable` exibe valores exatamente como recebidos, `null` ⇒ "—", estados qualitativos nunca como 0, `is_label_ready=false` ⇒ aviso "não apto à rotulagem" + causas exatamente como retornadas.

### Implementação
- [X] T027 [US2] Implementar `resources/js/rotulonutri/pipeline.js` (etapas `RECIPE_SAVED`→`CALC_003`→`SERVING_004`→`DECLARED_005`, estados `DONE|FAILED|BLOCKED|SKIPPED`, grava `stageReport`; resultados da 003 NÃO são convertidos nem usados na tabela — FR-030; porção/medida caseira nunca enviadas enquanto 004 bloqueada — FR-029).
- [X] T028 [US2] Integrar o pipeline ao botão "Gerar tabela nutricional" em `pages/recipe.js` e redirecionar para `/receitas/{id}/tabela`; mensagem clara "Etapa bloqueada: Feature 004 — …" (contracts §6).
- [X] T029 [US2] Criar `NutritionTableScreenController` (`php artisan make:controller … --no-interaction`; `show(Recipe $recipe)`) e registrar `GET /receitas/{recipe}/tabela` (`recipes.table`) em `routes/web.php`.
- [X] T030 [US2] Construir `resources/views/rotulonutri/table.blade.php` fiel a `pagina-02.png`: stepper (etapa 2), componente `nutrition-table` (todas "—"), linha "Porções por embalagem: — · Porção: —", painel "Modelo do cliente" (controles apenas visuais, sem persistência), "Aplicar ao rótulo" sem efeito, "Voltar à receita" (volta preservando o rascunho), área de andamento das etapas e avisos de bloqueio.
- [X] T031 [US2] Implementar `resources/js/rotulonutri/pages/table.js`: ler `stageReport`, exibir andamento/estados/erros da API (sem valores nutricionais da 003); quando houver execução 005 retornada, renderizar valores exatos (`renderDeclaredTable`), `is_label_ready` e `label_readiness_causes` conforme contrato; sem relatório na sessão ⇒ aviso informativo.
- [X] T032 [US2] Garantir que "Aplicar ao rótulo" não gera, altera ou persiste modelo e que o stepper mantém a etapa 3 desabilitada sem execução 005 válida (FR-002, FR-016).

**Checkpoint**: US1 + US2 funcionam; fluxo real termina na 004 bloqueada, sem valores fictícios.

---

## Phase 5: User Story 3 — Cadastrar ingrediente ausente na TACO (P2)

**Goal**: Tela 4 (`pagina-04.png`) com todos os campos exigidos pela Feature 006 e retorno à receita.
**Independent Test**: cadastrar alimento válido, voltar à Tela 1 e encontrá-lo na busca (quickstart §2.7).

### Testes
- [X] T033 [P] [US3] Teste Feature do mapper `tests/Feature/Frontend/IngredientFormNutrientMapperTest.php`: com 1 registro por (nome, unidade) ⇒ campo disponível com `nutrientId` do registro; 0 registros ⇒ `NOT_FOUND`; 2 registros idênticos ⇒ `AMBIGUOUS`; Açúcares totais/adicionados/Gorduras trans ⇒ `UNSUPPORTED`; nenhuma escrita em `nutrients`; "Energia" kcal e kJ distinguidos pela unidade.
- [X] T034 [P] [US3] Teste Feature `tests/Feature/Frontend/IngredientScreenTest.php`: `GET /ingredientes/novo` contém "Cadastrar Ingrediente", "Dados do ingrediente", campos de nome, fonte, código da fonte, evidência (referência e fonte), responsável, data do registro (todos vazios/sem default), "100 g" selecionado, "100 ml" desabilitado com explicação, seletor de estado (`EXACT`, `TRACE`, `NA`, `ABSENT`, `REEVALUATION`) por nutriente, 3 nutrientes sem suporte desabilitados, "Salvar no banco e voltar à receita", "Cancelar", "Como funciona"; nenhum `nutrient_id` literal no código-fonte da view (os valores vêm do mapper).
- [X] T035 [P] [US3] Estender `tests/Js/payloads.test.mjs`: `buildManualFoodPayload` ⇒ `reference_unit:"g"`; `EXACT` com `amount_value` string (ponto); demais estados com `amount_value:null`; nutriente sem estado omitido (nunca 0); `category` ausente; `registered_on` e `registered_by` nunca pré-preenchidos.

### Implementação
- [X] T036 [US3] Criar `app/Services/IngredientFormNutrientMapper.php` (`php artisan make:class Services/IngredientFormNutrientMapper --no-interaction`): leitura somente de `Nutrient` por nome+unidade exatos (tabela em data-model §1), retorno `{field,label,unit,nutrientId|null,available,reason|null}`; sem IDs fixos, sem escrita, sem escolher registro em caso de ambiguidade (FR-022/031).
- [X] T037 [US3] Criar `IngredientScreenController` (`make:controller`), injetar o mapper e entregar o mapa à view; registrar `GET /ingredientes/novo` (`ingredients.create`) em `routes/web.php`.
- [X] T038 [US3] Construir `resources/views/rotulonutri/ingredient.blade.php` fiel a `pagina-04.png` (trilha "Receita › Ingredientes", cartões, grade de 3 colunas, cartão "Como funciona", ações) com os campos adicionais exigidos pelo backend em estilo do protótipo, estados por nutriente e indisponibilidades explicadas (`UNSUPPORTED`/`NOT_FOUND`/`AMBIGUOUS`).
- [X] T039 [US3] Estender `payloads.js` com `buildManualFoodPayload` conforme T035 e contracts §4.
- [X] T040 [US3] Implementar `resources/js/rotulonutri/pages/ingredient.js`: montar payload, `POST /api/manual-foods`, exibir 422 nos campos (inclui `nutrients.{i}.*` via mapa índice→campo), erros de rede/5xx com "Tentar novamente"; após `201` voltar a `/receitas/nova` com rascunho preservado e aviso de sucesso (sem adicionar o ingrediente automaticamente); "Cancelar" volta sem persistir.
- [X] T041 [US3] Garantir que `source = TACO` exibe o 422 da API no campo (sem validação duplicada como fonte de verdade).

**Checkpoint**: US1–US3 funcionam; novo alimento aparece na busca.

---

## Phase 6: User Story 4 — Montagem do rótulo e PDF (P3, indisponível)

**Goal**: Tela 3 (`pagina-03.png`) só alcançável com execução 005 existente; PDF indisponível.
**Independent Test**: `/rotulos/999999/montagem` ⇒ 404; com execução 005 de fixture, preview renderiza e "Baixar PDF" fica desabilitado.

### Testes
- [X] T042 [P] [US4] Teste Feature `tests/Feature/Frontend/LabelAssemblyScreenTest.php`: sem execução ⇒ 404; com execução 005 criada por fixture/factory de teste existente ⇒ 200 com "Montagem do Rótulo", "[Nome do produto]"-estrutura, "Ingredientes:", tabela, "Arquivo para impressão", "Tamanho do rótulo", "Formato", "Nome do arquivo", "Baixar PDF" desabilitado com explicação, "Editar informações"; nenhuma lista de ingredientes/lote/validade/contato inventada.

### Implementação
- [X] T043 [US4] Criar `LabelAssemblyScreenController` (`show(DeclaredNutritionCalculation $declaredNutritionCalculation)`, apenas leitura) e registrar `GET /rotulos/{declaredNutritionCalculation}/montagem` (`labels.assembly`) em `routes/web.php`.
- [X] T044 [US4] Construir `resources/views/rotulonutri/label.blade.php` fiel a `pagina-03.png`: preview com `nutrition-table`, placeholders do protótipo para ingredientes e "Lote · Validade · Peso líquido · Contato", painel "Arquivo para impressão" (campos apenas visuais), "Baixar PDF" desabilitado e explicado, "Editar informações" voltando à Tela 1.
- [X] T045 [US4] Implementar `resources/js/rotulonutri/pages/label.js`: carregar `GET /api/declared-nutrition-calculations/{id}`, renderizar via `renderDeclaredTable`, exibir `is_label_ready`/causas exatamente como retornados; erros de rede/5xx com nova tentativa.

**Checkpoint**: Todas as telas existem; Tela 3 inalcançável sem execução 005 real.

---

## Phase 7: Polish & Cross-Cutting

- [X] T046 [P] Revisão de acessibilidade (NFR-004): rótulos associados, foco visível, navegação por teclado, `aria-disabled`/descrição nos itens indisponíveis, estados não dependentes só de cor.
- [X] T047 [P] Responsividade mínima funcional sem perda de dados (NFR-003) em `resources/css/app.css`/views, sem criar novo design.
- [X] T048 [P] Revisão de segurança (NFR-006): escape Blade, uso de `textContent` no DOM, ausência de rotas web de escrita, sem credenciais no cliente.
- [X] T049 Revisão visual lado a lado das 4 telas contra `docs/referencias/pagina-01..04.png` (NFR-001); registrar desvios (fonte; acréscimos exigidos pelo backend) para aprovação. Comparação concluída: os campos adicionais de rastreabilidade e estados não suportados da Tela 4 são exigidos pela Feature 006; o shell global também aparece na Tela 4 embora não esteja na referência visual. Os bloqueios honestos da 004/005 e controles da Tela 3 permanecem conforme especificado; nenhum desvio exige mudança funcional.
- [X] T050 Rodar `npm run build`, `node --test --test-isolation=none tests/Js/*.test.mjs`, `php artisan test --compact tests/Feature/Frontend` e `php artisan test --compact` completo (NFR-011). Verificação final: build aprovado (com aviso não bloqueante da dependência opcional `fontaine`); 18 testes JS e 13 testes Frontend aprovados; suíte completa `vendor/bin/phpunit --no-coverage` aprovada em banco PostgreSQL isolado recém-migrado e importado (270 testes, 28.286 asserções, 2 deprecações PHPUnit). No banco `testing` preexistente, quatro verificações de contagem falharam por registros persistentes de execuções anteriores; nenhum dado foi removido. O teste do mapper foi isolado em tabela temporária para não colidir com os 66 Nutrients importados.
- [X] T051 Executar `vendor/bin/pint --dirty --format agent` e corrigir (sem arquivos PHP fora do padrão).
- [X] T052 Verificar por `git diff --stat` e `git status` que **nenhum** arquivo proibido foi alterado (SC-007/FR-028) e que `package.json`/`composer.json` não mudaram.
- [X] T053 Executar a validação manual de [quickstart.md](./quickstart.md) e registrar o resultado. Roteiro concluído no ambiente isolado: cadastro inválido com `source = TACO` rejeitado; cadastro `TEST_FIXTURE` aceito e localizado pela busca; receita salva e reaberta; etapas 003/004/005 e bloqueio regulatório conferidos; falha de conexão exibiu a mensagem e a ação “Tentar novamente”; telas comparadas às referências; rota inexistente e ações indisponíveis conferidas. Dados e banco descartáveis não foram usados fora de testing.

---

## Dependencies & Execution Order

- **Phase 1** → **Phase 2** (bloqueia tudo) → US1 → US2 → (US3 e US4 podem ocorrer em paralelo após Phase 2/US1) → Polish.
- **US1** depende de T008–T016. **US2** depende de US1 (botão e rascunho) e T012/T014. **US3** depende de Phase 2 (draft/api/layout) e retorna à Tela 1 (US1). **US4** depende de Phase 2 e do componente `nutrition-table`/`renderDeclaredTable` (T014, T031).
- Dentro de cada story: testes → módulos JS puros → controller/rota → Blade → JS da página.
- Arquivos compartilhados sem paralelismo: `routes/web.php` (T016, T029, T037, T043), `resources/js/rotulonutri/payloads.js` (T019, T039), `pages/recipe.js` (T021–T023, T028), `tests/Js/payloads.test.mjs` (T017, T035).

## Parallel Examples

```text
Phase 2 testes:   T004 ∥ T005 ∥ T006 ∥ T007
Phase 2 módulos:  T008 ∥ T009 ∥ T010 ∥ T011 ∥ T012 ∥ T014
US1 testes:       T017 ∥ T018
US2 testes:       T024 ∥ T025 ∥ T026
US3 testes:       T033 ∥ T034 ∥ T035   (US3 e US4 em paralelo após US1)
Polish:           T046 ∥ T047 ∥ T048
```

## Implementation Strategy

- **MVP**: Phase 1 + Phase 2 + US1 (Tela 1 salvando receita). Valida shell, busca, −/+, total informativo e erros.
- **Entrega incremental**: + US2 (cadeia com bloqueio honesto e Tela 2) → + US3 (cadastro manual) → + US4 (Tela 3 condicionada) → Polish.
- **Ponto de atenção**: enquanto o backend não disponibilizar as referências regulatórias, US2/US4 não exibirão valores finais reais; isso é o comportamento aprovado (CQ-002), e os caminhos "com execução 005" são cobertos apenas por testes com fixtures.

## Mapa de rastreabilidade (resumo)

| Requisito | Tarefas |
|---|---|
| FR-001–FR-003 | T013, T014, T020, T030, T038, T044, T049 |
| FR-004, NFR-007 | T013–T016, T029, T037, T043, T052 |
| FR-005–FR-011 | T017–T023, T028 |
| FR-012–FR-016 | T024–T032 |
| FR-017–FR-018 | T042–T045 |
| FR-019–FR-023, FR-031 | T033–T041 |
| FR-024–FR-028 | T005, T009, T010, T022, T023, T052 |
| FR-029, FR-030 | T012, T024, T027, T031 |
| NFR-001–NFR-011 | T046–T053 |
