# Research — Feature 007 (Frontend do Protótipo)

## D-01 Tipografia
- **Decision**: manter a fonte já configurada no projeto (`Instrument Sans` via `@fonts`/Bunny em `vite.config.js` e `@theme`).
- **Rationale**: o PDF não identifica a família tipográfica (sans geométrica); trocar exigiria fonte/dependência não aprovada. Proibido inventar.
- **Alternatives**: importar outra fonte (rejeitado: nova dependência externa); fonte de sistema (rejeitado: perde consistência). Desvio registrado para revisão visual (NFR-001).

## D-02 Paleta e tokens visuais
- **Decision**: definir tokens em `@theme` de `resources/css/app.css` (verde-escuro da sidebar, verde da ação primária, verde-claro de destaque, fundo esverdeado, cinza dos campos, vermelho do "×"), amostrando as cores diretamente das imagens do protótipo na implementação.
- **Rationale**: fidelidade ao protótipo; Tailwind 4 usa CSS-first `@theme`. Alteração aditiva não afeta `welcome`.
- **Alternatives**: valores arbitrários inline (rejeitado: baixa manutenção).

## D-03 Roteamento entre telas
- **Decision**: rotas web GET dedicadas (`/receitas/nova`, `/receitas/{recipe}/tabela`, `/rotulos/{declaredNutritionCalculation}/montagem`, `/ingredientes/novo`); navegação por links; dados transitórios no `sessionStorage`.
- **Rationale**: Blade multi-página (sem SPA); route-model binding dá 404 natural quando a receita/execução não existe; nenhuma rota POST web (sem CSRF).
- **Alternatives**: SPA/hash router (proibido); POST web para passar estado (rejeitado: CSRF e complexidade).

## D-04 Estado local da receita (CQ-001, FR-029, FR-011/023)
- **Decision**: `sessionStorage` com chave `rotulonutri.recipeDraft` (nome, tipo, rendimento, porção, medida caseira, ingredientes, `recipeId`) e `rotulonutri.stageReport` (andamento das etapas).
- **Rationale**: porção/medida caseira não têm persistência no backend; preservação ao ir à Tela 4 e voltar; escopo por aba.
- **Alternatives**: `localStorage` (rejeitado: vaza entre sessões); cookie/servidor (proibido persistência).

## D-05 Aritmética decimal no cliente
- **Decision**: módulo `decimal.js` com `BigInt` e escala por string (parse, normalização `,`→`.`, soma, +1, comparação > 0), sem `Number`.
- **Rationale**: −/+ e total informativo exatos (NFR-002); nunca ponto flutuante nem notação científica.
- **Alternatives**: `Number` (rejeitado: erro de ponto flutuante); biblioteca decimal (rejeitado: dependência nova).

## D-06 Resolvedor de referências regulatórias (CQ-002)
- **Decision**: módulo `regulatory-references.js` expõe `resolveRegulatoryReferences()` que retorna `{available:false, cause}` — não há endpoint, seeder nem mecanismo documentado. O pipeline só segue à Feature 004 se `available === true`.
- **Rationale**: ponto de extensão explícito sem inventar IDs; comportamento testável com stub **somente em teste**.
- **Alternatives**: IDs fixos/`.env`/mocks (proibido).

## D-07 Pipeline e relatório de etapas
- **Decision**: etapas `RECIPE_SAVED` → `CALC_003` → `SERVING_004` → `DECLARED_005`, cada uma `DONE | FAILED | BLOCKED | SKIPPED` com `cause`. Hoje: receita salva (ou falha), 003 (ou falha) e 004/005 `BLOCKED`. Resultados 003 NÃO preenchem a tabela (FR-030); apenas `id` e estado.
- **Rationale**: honestidade operacional; mensagens compreensíveis; reexecução cria nova execução (imutável).

## D-08 Erros
- **Decision**: `errors.js` normaliza (a) 422 → `{field, message}` mapeado para o campo da UI (incl. `ingredients.N.quantity`, `nutrients.N.amount_value`); (b) 5xx/rede/timeout → mensagem geral com ação "Tentar novamente".
- **Rationale**: diretriz 8 (mensagens da API nos respectivos campos).

## D-09 Mapeamento de nutrientes (FR-022/031)
- **Decision**: `IngredientFormNutrientMapper` lê `Nutrient` (somente leitura) e, para cada campo da UI, busca registros com nome e unidade exatos: Energia/kcal, Carboidrato/g, Proteína/g, Lipídeos/g, Ácidos graxos saturados/g, Fibra alimentar/g, Sódio/mg. 1 resultado → disponível (`nutrient_id` injetado no HTML); 0 ou >1 → indisponível com causa. Açúcares totais/adicionados e Gorduras trans: sempre indisponíveis (sem nome correspondente na base; nunca presumidos).
- **Rationale**: decisão aprovada; nomes são rótulos da TACO, não IDs; sem escrita.

## D-10 Testes
- **Decision**: PHPUnit Feature (rotas, conteúdo Blade, mapper com 0/1/2 registros, 404 da Tela 3, não regressão) + `node --test` para módulos JS puros em `tests/Js/`. Respostas simuladas da Feature 005 existem **apenas** em fixtures de teste.
- **Alternatives**: Vitest/Playwright/Dusk (rejeitado: novas dependências sem aprovação).

## D-11 Entrada JS por página
- **Decision**: `app.js` lê `document.body.dataset.page` e importa dinamicamente `pages/{page}.js`; `vite.config.js` inalterado.
- **Rationale**: um único bundle de entrada existente, code-splitting nativo do Vite.

## D-12 Rótulos de unidade dinâmicos (CQ-001)
- **Decision**: tipo sólido → `yield_unit=g`, rótulos "Peso final (g)"/"Porção (g)"; líquido → `ml`, "Volume final (ml)"/"Porção (ml)"; ingredientes sempre "g"; nenhuma conversão.
