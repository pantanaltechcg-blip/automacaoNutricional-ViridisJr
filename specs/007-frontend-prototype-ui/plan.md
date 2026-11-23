      # Implementation Plan: Frontend do Protótipo RótuloNutri

**Branch**: `007-frontend-prototype-ui` | **Date**: 2026-11-22 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/007-frontend-prototype-ui/spec.md` (aprovada pelo diretor em 2026-11-22; CQ-001 a CQ-008 e decisões complementares incorporadas)

## Summary

Entregar as 4 telas do protótipo (`docs/referencias/pagina-01..04.png`) como páginas **Laravel Blade** servidas por **rotas web de apresentação** e **controllers de apresentação**, estilizadas com **Tailwind CSS 4** já instalado e com interações em **JavaScript Vanilla** empacotado pelo **Vite** já configurado. A interface consome somente os endpoints `/api` existentes (Features 001–006) e **não altera nenhum arquivo de backend nem a Feature 005**.

Abordagem técnica:

1. **Shell Blade** único (barra lateral, cabeçalho, indicador de etapas) com componentes Blade reutilizáveis (tabela nutricional, avisos de indisponibilidade/bloqueio, campos de formulário).
2. **Uma entrada JS por página** carregada por import dinâmico a partir de `resources/js/app.js` (sem alterar `vite.config.js`), com módulos puros e sem dependências: `decimal` (aritmética decimal com `BigInt`, para −/+ e total informativo), `api` (fetch + normalização de erros 422/5xx/rede), `draft` (estado local da receita em `sessionStorage`), `pipeline` (cadeia receita → 003 → 004 → 005 com interrupção explícita) e um módulo por tela.
3. **Cadeia regulatória honesta**: a etapa 004 só é tentada se um *resolvedor de referências regulatórias* retornar identificadores efetivamente disponibilizados pelo backend. Hoje não há fonte alguma; o resolvedor retorna "indisponível" e a cadeia termina na Feature 003 com mensagem da etapa bloqueada e da causa (CQ-002). Nenhum ID é criado, fixado ou simulado.
4. **Mapeamento de nutrientes (Tela 4)** feito por um service de apresentação somente leitura sobre a tabela `nutrients` (nome + unidade, sem `nutrient_id` fixo), com tratamento de correspondência ausente/ambígua (FR-022, FR-031).
5. **Tela 3 (Rótulo)** só é alcançável com uma execução 005 existente, via rota com *route-model binding* de `DeclaredNutritionCalculation` (somente leitura); sem execução → 404. "Baixar PDF" permanece desabilitado.

## Technical Context

**Language/Version**: PHP ^8.3 (ambiente PHP 8.4), Laravel ^13.17, Blade; JavaScript ES2022+ (Vanilla, módulos ES, `BigInt`).

**Primary Dependencies**: já instaladas — `laravel/framework`, `vite ^8`, `laravel-vite-plugin ^3.1`, `tailwindcss ^4`, `@tailwindcss/vite ^4`. **Nenhuma nova dependência** (Composer ou npm).

**Storage**: PostgreSQL existente, **somente leitura** pela Feature 007 (tabelas `nutrients` e `declared_nutrition_calculations` apenas via Eloquent). Nenhuma migration. Estado de edição no navegador (`sessionStorage`), nunca no backend.

**Testing**: PHPUnit 12 (`php artisan test --compact`) para rotas Blade, service de mapeamento e não regressão; `node --test` (runner nativo do Node 24, sem dependência) para módulos JS puros (`decimal`, `payloads`, `pipeline`, `errors`) em `tests/Js/`.

**Target Platform**: navegador desktop moderno (protótipo ≈ 1800 px) servido pelo Laravel (Sail/Docker, PostgreSQL).

**Project Type**: aplicação web Laravel monolítica (frontend Blade + assets Vite no mesmo projeto).

**Performance Goals**: busca com debounce (300 ms) e cancelamento de requisição obsoleta (`AbortController`); páginas sem bloqueio perceptível.

**Constraints**: sem SPA, sem frameworks externos, sem nova API, sem alteração de Models/Migrations/Controllers/Services/Requests/Resources existentes, `routes/api.php`, contratos ou Feature 005; nenhuma regra de cálculo/validação/arredondamento/conversão no cliente.

**Scale/Scope**: 4 telas, 5 rotas web (GET), 1 service de apresentação, ~8 módulos JS, ~6 componentes Blade.

### Pontos antes marcados "NEEDS CLARIFICATION" — resolvidos em [research.md](./research.md)
Fonte tipográfica, paleta, roteamento entre telas, persistência de rascunho, estratégia de testes JS, resolvedor de referências, mapeamento de erros 422.

## Constitution Check

*GATE: aprovado antes da Phase 0; reavaliado após a Phase 1 — sem violações.*

| Princípio / Regra | Avaliação | Evidência no plano |
|---|---|---|
| I. Não Invenção | ✅ | Nenhum ID, campo, regra ou valor criado; lacunas permanecem indisponíveis/bloqueadas; resolvedor de referências retorna "indisponível" |
| II. Stack Definida | ✅ | Laravel + Blade + Vite + Tailwind 4 já instalados + JS Vanilla; sem dependência nova; teste JS com `node --test` nativo |
| III. Arquitetura | ✅ | Frontend → Routes → Controllers (apresentação) → Services; regra de mapeamento em `Services`; cliente só apresenta e chama API existente |
| IV. Motor de Cálculo | ✅ | Zero cálculo nutricional/arredondamento no cliente; total informativo é soma de quantidades digitadas com aritmética decimal exata |
| V. Dados e Fontes | ✅ | `null`/TRACE/NA/ABSENT/REEVALUATION nunca viram 0; campo vazio nunca enviado como 0; `source` preservada na exibição |
| VI. Regulatório | ✅ | Tabela final só com execução 005; `is_label_ready`/causas exibidos como retornados |
| Autoridade/Integridade | ✅ | Pendências isoladas por FR-029/030; resultado parcial nunca apresentado como apto |
| Geração do Rótulo | ✅ | Sem geração/exportação PDF; "Baixar PDF" indisponível |
| Banco de Dados e Testes | ✅ | Nenhuma migration; testes automatizados dos caminhos autorizados e dos bloqueios |
| Spec Kit / Git | ✅ | Specify → Clarify → **Plan** (atual) → Tasks → Analyze → Implement |
| AGENTS.md (Laravel Boost) | ✅ | `php artisan make:*` para controller/teste; Pint nos PHP alterados; sem `.ai/rules` no repositório |

**Re-check pós-design**: o service de mapeamento e os controllers de apresentação são *novos* arquivos; nenhum existente é modificado. A rota `/` (welcome) permanece intacta. Sem itens em Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/007-frontend-prototype-ui/
├── spec.md                      # aprovada
├── plan.md                      # este arquivo
├── research.md                  # Phase 0
├── data-model.md                # Phase 1 (estado do cliente + leituras)
├── quickstart.md                # Phase 1
├── contracts/
│   └── ui-contracts.md          # rotas web, uso da API, payloads, erros, estados
├── checklists/requirements.md
└── tasks.md                     # Phase 2 (/speckit-tasks) — NÃO criado aqui
```

### Source Code (repository root)

```text
routes/
└── web.php                                   # + rotas de apresentação (GET); '/' inalterada

app/
├── Http/Controllers/
│   ├── RecipeScreenController.php            # Tela 1 (create) — NOVO
│   ├── NutritionTableScreenController.php    # Tela 2 (show) — NOVO
│   ├── LabelAssemblyScreenController.php     # Tela 3 (show; binding DeclaredNutritionCalculation) — NOVO
│   └── IngredientScreenController.php        # Tela 4 (create) — NOVO
└── Services/
    └── IngredientFormNutrientMapper.php      # leitura somente de `nutrients`; nome+unidade — NOVO

resources/
├── views/
│   ├── layouts/rotulonutri.blade.php         # shell: sidebar, header, @vite
│   ├── components/rotulonutri/               # stepper, nutrition-table, unavailable, field, nav-item
│   └── rotulonutri/                          # recipe, table, label, ingredient
├── css/app.css                               # + tokens @theme do protótipo (aditivo)
└── js/
    ├── app.js                                # + roteador por data-page (import dinâmico)
    └── rotulonutri/
        ├── decimal.js  api.js  errors.js  draft.js  payloads.js
        ├── pipeline.js  regulatory-references.js
        └── pages/ recipe.js  table.js  label.js  ingredient.js

tests/
├── Feature/Frontend/                         # PHPUnit: rotas, Blade, mapper, 404 Tela 3
├── Unit/                                     # (se necessário) mapper puro
└── Js/                                       # node --test: decimal, payloads, errors, pipeline
```

**Structure Decision**: aplicação Laravel única; sem diretórios-base novos (apenas subpastas em `resources/views`, `resources/js`, `tests`). Nenhum arquivo de `app/Models`, `app/Services` existentes, `app/Http/Requests`, `app/Http/Resources`, `database/`, `routes/api.php` ou `specs/005-*` é tocado.

## Estratégia por tela (resumo; detalhes em contracts/ui-contracts.md)

| Tela | Rota web | Dados do servidor | Comportamento JS |
|---|---|---|---|
| 1 Receita | `GET /receitas/nova` | nenhum | rascunho, busca `/api/foods`, −/+, total informativo, salvar receita, cadeia 003→(004 bloqueada) |
| 2 Tabela | `GET /receitas/{recipe}/tabela` | receita existente (binding) | lê relatório de etapas da sessão; tabela "—"; avisos; se existir execução 005 retornada, renderiza valores exatos |
| 3 Rótulo | `GET /rotulos/{declaredNutritionCalculation}/montagem` | execução 005 existente (binding, leitura) | renderiza preview; PDF desabilitado; 404 sem execução |
| 4 Ingrediente | `GET /ingredientes/novo` | mapa de nutrientes (service somente leitura) | formulário completo, `POST /api/manual-foods`, retorno à Tela 1 |

## Riscos e mitigação

| Risco | Mitigação |
|---|---|
| Nenhum caminho real para as telas 2/3 exibirem valores finais hoje (GAP-01) | Comportamento especificado e testado com respostas simuladas **apenas em testes automatizados**, nunca na aplicação |
| Fonte do protótipo desconhecida | Manter fonte já configurada no projeto (research D-01); desvio documentado |
| Sem runner JS oficial | `node --test` nativo; sem dependência |
| Ambiguidade de nutrientes | FR-031; testes com 0 e 2 registros |
| Perda de rascunho ao recarregar | `sessionStorage` por aba; documentado |

## Complexity Tracking

Sem violações da Constituição; seção não aplicável.
