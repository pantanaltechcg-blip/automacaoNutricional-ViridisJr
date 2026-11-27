# UI Contracts — Feature 008

Contratos de **apresentação**. Nenhum contrato da API é criado ou alterado; os contratos de `/api` são os das Features 001–007.

## 1. Rotas web (novas, somente GET)

| Nome | Método/URI | Controller | Resposta | Falhas |
|---|---|---|---|---|
| `recipes.create` | `GET /receitas/nova` | `RecipeScreenController@create` | Blade Tela 1 | — |
| `recipes.table` | `GET /receitas/{recipe}/tabela` | `NutritionTableScreenController@show` | Blade Tela 2 | 404 se receita inexistente |
| `labels.assembly` | `GET /rotulos/{declaredNutritionCalculation}/montagem` | `LabelAssemblyScreenController@show` | Blade Tela 3 | 404 sem execução 005 |
| `ingredients.create` | `GET /ingredientes/novo` | `IngredientScreenController@create` | Blade Tela 4 + mapa de nutrientes | — |

`GET /` (welcome) permanece inalterada. Itens "Rótulos" e "Configurações" não possuem rota (indisponíveis). Sem rotas POST/PUT web.

Cada view define `<body data-page="recipe|table|label|ingredient">`; `app.js` importa `pages/{page}.js`.

## 2. Uso dos endpoints da API (somente os existentes)

| Ação da UI | Chamada | Corpo | Sucesso | Tratamento de falha |
|---|---|---|---|---|
| Buscar ingrediente | `GET /api/foods?q={termo}` | — | `data[]` | termo vazio → sem chamada; 422/5xx/rede → mensagem e nova tentativa |
| Salvar receita (nova) | `POST /api/recipes` | `{name, yield_value, yield_unit, ingredients[{food_id, quantity, unit:"g", position}]}` | `data.id` | 422 → campos |
| Salvar receita (existente) | `PUT /api/recipes/{id}` | idem | `data` | idem |
| Calcular Feature 003 | `POST /api/recipes/{id}/nutrition-calculations` | — | `data.id` | falha → etapa `FAILED` |
| Feature 004 | `POST /api/nutrition-calculations/{id}/serving-daily-value-calculations` | `{portion{value,unit,household_measure}, portion_authorization_id, regulatory_profile_id}` | — | **Não chamada** enquanto `resolveRegulatoryReferences().available === false` |
| Feature 005 | `POST /api/serving-daily-value-calculations/{id}/declared-nutrition-calculations` | `{regulatory_declaration_profile_id}` | — | **Não chamada** (depende da 004) |
| Exibir execução 005 | `GET /api/declared-nutrition-calculations/{id}` | — | `data` | apresentação exata |
| Cadastrar ingrediente | `POST /api/manual-foods` | ver §4 | `201` | 422 → campos |

Proibido: criar/fixar/inferir `portion_authorization_id`, `regulatory_profile_id`, `regulatory_declaration_profile_id`; enviar fatores/VDR/status/evidências; chamar qualquer endpoint inexistente.

## 3. Mapeamento receita (UI → API)

| UI | API | Regra |
|---|---|---|
| Nome da receita | `name` | texto (trim) |
| Tipo "Sólido ou semissólido" | `yield_unit = "g"` | rótulos "Peso final (g)", "Porção (g)" |
| Tipo "Líquido" | `yield_unit = "ml"` | rótulos "Volume final (ml)", "Porção (ml)" |
| Peso/Volume final | `yield_value` | string decimal, ponto |
| Quantidade do ingrediente | `ingredients[i].quantity` | string decimal > 0; `unit = "g"` |
| Ordem das linhas | `ingredients[i].position` | 1..n sequencial |
| Porção, Medida caseira | **não enviados** | estado local (FR-029) |
| Total informativo | **não enviado** | soma local (FR-008) |

Controles de quantidade: `+` soma 1 (decimal exato; vazio ⇒ 0+1); `−` só se resultado > 0; digitação aceita `,` ou `.`; saída sempre com ponto, sem expoente.

## 4. Cadastro manual (Tela 4 → `POST /api/manual-foods`)

```json
{
  "name": "…", "source": "…", "source_code": "…",
  "reference_unit": "g",
  "evidence_reference": "…", "evidence_source": "…",
  "registered_by": "…", "registered_on": "YYYY-MM-DD",
  "nutrients": [
    {"nutrient_id": 0, "status": "EXACT", "amount_value": "12.5"},
    {"nutrient_id": 0, "status": "TRACE", "amount_value": null}
  ]
}
```
(`nutrient_id` ilustrativo; sempre vindo do mapper, nunca fixo.) `category` não é enviada. Sem valores pré-preenchidos em campos obrigatórios. `source` igual a `TACO` → 422 exibido. Nutriente sem estado: omitido. Estados: `EXACT` (com valor), `TRACE`, `NA`, `ABSENT`, `REEVALUATION` (valor `null`).

## 5. Mapeamento de erros 422

| Chave da API | Campo da UI |
|---|---|
| `name`, `yield_value`, `yield_unit` | campos homônimos da Tela 1 |
| `ingredients` | aviso geral da lista |
| `ingredients.{i}.food_id` / `.quantity` / `.unit` / `.position` | linha de ingrediente `i` |
| `source`, `source_code`, `evidence_*`, `registered_*`, `name` | campos da Tela 4 |
| `nutrients` | aviso do cartão "Informação nutricional" |
| `nutrients.{i}.amount_value` / `.status` | campo do nutriente `i` (índice → campo pelo mapa enviado) |
| chave desconhecida | aviso geral do formulário com a mensagem da API |

Rede/timeout/5xx: aviso geral compreensível + botão "Tentar novamente"; dados digitados preservados.

## 6. Estados e textos de indisponibilidade/bloqueio (Blade)

| Situação | Apresentação |
|---|---|
| Etapa 004 bloqueada | "Etapa bloqueada: Feature 004 — referências regulatórias (autorização de porção e perfil regulatório) não disponibilizadas pelo sistema." |
| Etapa 005 bloqueada | "Etapa bloqueada: Feature 005 — depende da execução da Feature 004." |
| Célula da tabela sem valor | "—" com descrição acessível da causa |
| Porções por embalagem | "—" (sem suporte) |
| Porção sem execução 004 | "—" |
| "auto kcal" | texto de indisponibilidade; sem número |
| 100 ml / Açúcares totais / Açúcares adicionados / Gorduras trans | desabilitados + explicação "sem suporte no backend atual" |
| Nutriente com correspondência ausente/ambígua | desabilitado + causa (`NOT_FOUND`/`AMBIGUOUS`) |
| "Baixar PDF" | desabilitado + "Exportação em PDF indisponível no sistema atual" |
| "Aplicar ao rótulo", Modelo do cliente | elementos visuais; sem efeito/persistência |
| `is_label_ready = false` (execução 005) | "Resultado não apto à rotulagem" + lista de `label_readiness_causes` exatamente como retornada |

## 7. Tabela nutricional (componente Blade `nutrition-table`)

Linhas fixas: Valor energético, Carboidratos, Açúcares totais, Açúcares adicionados, Proteínas, Gorduras totais, Gorduras saturadas, Gorduras trans, Fibra alimentar, Sódio. Colunas: 100 g (ou 100 ml conforme a execução), Porção, %VD*. Entrada: execução 005 retornada pela API ou `null` (todas "—"). Valores exibidos exatamente como recebidos; `null` → "—"; sem arredondar, converter ou calcular.
