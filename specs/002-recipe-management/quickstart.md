# Quickstart Validation: 002-recipe-management

## Objetivo

Validar que receitas completas podem ser criadas, consultadas e editadas com sua
coleção ordenada de ingredientes, referenciando alimentos existentes sem modificar
a Base Nutricional e sem executar cálculos.

## Pré-requisitos

- PHP 8.4 e dependências Composer instaladas.
- PostgreSQL configurado no `.env` local; nenhuma credencial deve ser versionada.
- A conexão de testes permanece `pgsql`, conforme `phpunit.xml`.
- Feature `001-taco-nutrition-base` migrada e com alimentos disponíveis.

```bash
composer install
php artisan migrate
php artisan nutrition:import-taco
php artisan migrate:status
```

Confirme que as migrations de `recipes` e `recipe_ingredients` estão aplicadas.

## 1. Executar a aplicação

```bash
php artisan serve
```

A URL local resolvida para o contrato é:

`http://localhost:8000/api/recipes`

## 2. Selecionar alimentos existentes

Reutilize a busca da feature 001 e anote ao menos dois valores de `data[].id`:

```bash
curl "http://localhost:8000/api/foods?q=arroz"
```

Não crie alimentos durante os testes de receita.

## 3. Criar uma receita válida

Substitua `<food-id-1>` e `<food-id-2>` por identificadores existentes:

```bash
curl -X POST "http://localhost:8000/api/recipes" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Receita de validação",
    "yield_value": "850.125",
    "yield_unit": "g",
    "ingredients": [
      {"food_id": <food-id-1>, "quantity": "125.5", "unit": "g", "position": 1},
      {"food_id": <food-id-2>, "quantity": "0.375", "unit": "g", "position": 2}
    ]
  }'
```

Resultado esperado:

- resposta de criação contém `data.id`;
- nome, rendimento e unidade correspondem ao payload;
- os dois `food_id` permanecem associados;
- quantidades continuam como strings decimais, sem arredondamento;
- ingredientes são apresentados nas posições `1` e `2`.

## 4. Reabrir a receita

```bash
curl "http://localhost:8000/api/recipes/<recipe-id>"
```

Confirme que nome, rendimento, unidade, ingredientes, `food_id`, quantidade,
unidade e posição correspondem ao último salvamento.

## 5. Editar, remover, adicionar e reordenar

Envie o estado completo desejado. O exemplo altera nome, rendimento e quantidade,
remove um item anterior, adiciona outro e troca as posições:

```bash
curl -X PUT "http://localhost:8000/api/recipes/<recipe-id>" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Receita de validação atualizada",
    "yield_value": "900.5001",
    "yield_unit": "ml",
    "ingredients": [
      {"food_id": <food-id-2>, "quantity": "75.125", "unit": "g", "position": 1},
      {"food_id": <food-id-1>, "quantity": "200.0005", "unit": "g", "position": 2}
    ]
  }'
```

Consulte novamente e confirme:

- a coleção corresponde integralmente ao último payload;
- a ordem segue `position`;
- a alteração explícita de `food_id`, quando enviada, é preservada;
- reordenar não altera quantidade, unidade, rendimento ou `Food`;
- `900.5001`, `75.125` e `200.0005` não foram arredondados;
- nenhum registro de `foods` foi criado, alterado ou removido.

## 6. Validar rejeições

Repita a criação ou edição alterando somente o caso sob teste. Cada caso deve falhar
por validação e não pode persistir alteração parcial:

| Cenário | Entrada inválida | Resultado esperado |
|---|---|---|
| Receita sem ingredientes | `"ingredients": []` | Rejeitada; nenhum draft criado |
| Food inexistente | `"food_id": 999999999` | Rejeitada; nenhum Food substituto criado |
| Quantidade zero | `"quantity": "0"` | Rejeitada |
| Quantidade negativa | `"quantity": "-1"` | Rejeitada |
| Unidade de ingrediente | `"unit": "kg"` ou `"ml"` | Rejeitada |
| Rendimento zero | `"yield_value": "0"` | Rejeitada |
| Rendimento negativo | `"yield_value": "-1"` | Rejeitada |
| Unidade de rendimento | `"yield_unit": "kg"` | Rejeitada |
| Posição repetida | dois ingredientes com a mesma `position` | Rejeitada |

Valide também os casos aceitos:

- quantidade fracionária positiva em `g`;
- rendimento fracionário positivo em `g`;
- rendimento fracionário positivo em `ml`;
- mais casas decimais do que os exemplos, preservadas sem arredondamento;
- posições positivas distintas, inclusive com lacunas;
- troca explícita de `food_id` para outro alimento existente.

### Normalização de transporte HTTP

Valide separadamente o comportamento padrão executado antes do Form Request:

| Entrada HTTP | Valor submetido à validação | Resultado esperado |
|---|---|---|
| `"yield_value": " 1.250 "` | `"1.250"` | Aceito como string decimal, sem arredondamento |
| `"quantity": " 1.250 "` | `"1.250"` | Aceito como string decimal, sem arredondamento |
| `"unit": " g "` | `"g"` | Aceito sem conversão de unidade |
| Campo obrigatório com `""` | `null` | Rejeitado pela regra de obrigatoriedade |
| Campo obrigatório com `"   "` | `null` | Rejeitado pela regra de obrigatoriedade |

Confirme que arrays aninhados também recebem essa normalização, que a caixa não é
alterada e que nenhuma entrada é convertida para `float`, `double`, unidade diferente
ou decimal com escala fixa.

## 7. Confirmar que o rendimento não é calculado

Crie ou edite uma receita cujo `yield_value` seja diferente da soma das quantidades
dos ingredientes. Reabra a receita e confirme que o rendimento informado foi
preservado literalmente. Não deve ocorrer conversão entre `g` e `ml`, cálculo de
densidade ou inferência pela soma.

## 8. Executar validação automatizada

```bash
php artisan test
vendor/bin/pint --test
git diff --check
```

Os testes devem usar PostgreSQL e cobrir:

- criação atômica com um e múltiplos ingredientes;
- consulta e reprodução integral dos dados;
- substituição transacional da coleção;
- alteração de quantidade e troca explícita de `Food`;
- remoção e reordenação;
- todas as rejeições listadas acima;
- trim e conversão de string vazia para `null` antes do Form Request, inclusive em
  ingredientes aninhados;
- preservação de decimais sem `float` ou arredondamento;
- ausência de normalização de caixa e de conversão entre unidades;
- ausência de modificação na Base Nutricional.

## Critério de conclusão

A validação é concluída quando migrations, cenários manuais e testes automatizados
passam em PostgreSQL, os valores reabertos correspondem às entradas e nenhuma
funcionalidade de cálculo, autenticação, histórico, rotulagem ou exportação aparece
no fluxo.
