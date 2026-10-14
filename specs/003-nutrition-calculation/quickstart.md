# Quickstart Validation: 003-nutrition-calculation

## Objetivo

Validar cálculo exato por ingrediente, consolidação, normalização por rendimento,
preservação de estados/ausências e histórico imutável, sem executar funcionalidades
regulatórias ou de rotulagem.

## Pré-requisitos

- PHP 8.4 e dependências Composer instaladas.
- PostgreSQL configurado localmente, sem credenciais versionadas.
- Features 001 e 002 migradas e TACO importada.

```bash
composer install
php artisan migrate
php artisan nutrition:import-taco
php artisan migrate:status
```

Após a task explícita de dependência da implementação, confirme que `brick/math`
está declarado diretamente e que a versão resolvida permanece compatível com 1.0.0:

```bash
composer show --direct brick/math
```

Antes dessa task, o pacote está instalado apenas transitivamente e o código da
aplicação ainda não deve importar `Brick\Math`.

## 1. Preparar uma Recipe existente

Use `GET /api/foods?q=...` para selecionar Foods existentes e crie uma Recipe pelo
contrato da feature 002. Ingredientes permanecem em g; use rendimento positivo em g
ou ml. Não crie nem altere dados nutricionais para acomodar o teste.

## 2. Criar uma execução

```bash
curl -X POST "http://localhost:8000/api/recipes/<recipe-id>/nutrition-calculations" \
  -H "Accept: application/json"
```

Anote `data.id` e confirme:

- `rules_version` está presente;
- rendimento e ocorrências correspondem ao snapshot da Recipe;
- todos os Nutrients aparecem uma vez;
- cada ocorrência possui uma contribuição para cada Nutrient;
- valores são razões com componentes string, nunca números JSON de ponto flutuante.

## 3. Conferir fórmula e referência

Para um `FoodNutrient` `EXACT`, valide sem arredondamento:

```text
contribuição = quantity × amount_value ÷ 100
total        = soma das contribuições EXACT do mesmo Nutrient
referência   = total ou subtotal × 100 ÷ yield_value
```

Confirme que:

- `yield_unit = g` produz referência `100 g`;
- `yield_unit = ml` produz referência `100 ml`;
- rendimento é o persistido, não a soma dos ingredientes;
- não ocorre conversão g↔ml ou inferência de densidade;
- energia da TACO é tratada como Nutrient da fonte, sem fatores de conversão.

Inclua casos cuja divisão seja periódica e compare numerador/denominador exatos.

## 4. Validar estados e indisponibilidade

Com fixtures controladas em testes PostgreSQL, cubra:

- `EXACT` positivo e `EXACT` zero;
- cada um de `TRACE`, `NA`, `ABSENT` e `REEVALUATION` isoladamente;
- todos os pares e conjuntos de estados qualitativos sem `EXACT`;
- ausência de relação isolada e combinada com cada estado;
- `EXACT` combinado com cada estado e com ausência de relação.

Resultados esperados:

- nenhum estado ou ausência vira zero;
- zero `EXACT` permanece `0/1`;
- conjunto completo de estados é preservado sem precedência;
- ausência de relação permanece em indicador separado;
- somente `EXACT` produz valor numérico;
- total sem `EXACT` é nulo e incompleto;
- subtotal com `EXACT` e informação não numérica é incompleto;
- a referência desse subtotal é calculada e continua incompleta, com os mesmos
  estados e indicador de ausência.

## 5. Validar snapshot e novas execuções

1. Consulte a execução:

```bash
curl "http://localhost:8000/api/nutrition-calculations/<calculation-id>"
```

2. Altere integralmente a Recipe usando o `PUT` da feature 002.
3. Consulte novamente o ID antigo e confirme resposta idêntica.
4. Solicite outro cálculo e confirme novo ID, novo snapshot e preservação do antigo.
5. Verifique que não há rota de update/delete para resultados.

## 6. Caso de referência “GELEIA DE AMORA”

Entradas aprovadas:

- 1.000 g de amora;
- 1.000 g de açúcar cristal;
- 50 g de suco de limão;
- rendimento de 1.680 g;
- referência de carboidratos aproximada: 65,21 g por 100 g.

Antes de executar, confirme que cada Food está inequivocamente identificado na fonte
aprovada por `source = TACO` e `source_code`. No conjunto processado atual:

- `Açúcar, cristal` está disponível (`source_code` 492);
- não foi localizado Food contendo “amora”;
- existem múltiplos limões e não há decisão que escolha qual corresponde a “suco de
  limão” do caso.

Portanto, a execução integral deste cenário permanece bloqueada por mapeamento de
fonte, não pela fórmula, e não pode ser marcada como PASS. Não criar Food sintético,
escolher limão arbitrariamente, usar correspondência aproximada, inventar composição
nem forçar 65,21. Esse bloqueio não impede os testes genéricos do motor com fixtures
controladas. Quando a Viridis/equipe responsável aprovar a identificação dos Foods,
execute o fluxo normal e explique eventual diferença somente pelos dados e regras
aprovados.

## 7. Validação automatizada

```bash
php artisan test
vendor/bin/pint --test
git diff --check
```

A suíte deve usar PostgreSQL e verificar:

- fórmulas como razões exatas, inclusive dízimas;
- todos os Nutrients e cada ocorrência repetida de Food;
- matriz completa de estados/ausência e completude;
- subtotal incompleto normalizado e ainda incompleto;
- transação integral e ausência de N+1;
- snapshot suficiente e leitura independente das fontes atuais;
- versão das regras;
- duas solicitações gerando dois resultados imutáveis;
- bloqueio de update/delete no banco;
- Recipe, ingredientes e Base Nutricional inalterados;
- inexistência de porção, %VD, arredondamento, energia por fatores e rótulo.

## Critério de conclusão

A validação termina quando migrations e testes passam em PostgreSQL, as razões
reproduzem exatamente as entradas, cada execução permanece imutável e consultável e
o único bloqueio documental remanescente é o mapeamento autorizado dos Foods do caso
“GELEIA DE AMORA”.
