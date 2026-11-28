# Data Model: Área de Rótulos

## Persistência

Esta Feature não adiciona nem modifica tabelas, colunas, constraints, índices, triggers ou Models. A listagem é uma projeção somente de leitura dos registros já preservados pela Feature 005.

## Fonte consultada

| Registro existente | Campos/relacionamentos usados na tela | Finalidade |
|---|---|---|
| `DeclaredNutritionCalculation` | `id`, `source_recipe_id`, `source_nutrition_calculation_id`, `created_at`, `is_label_ready`, relação `causes` | Identificar e distinguir a execução, sua origem, data, estado e causas |
| `DeclaredNutritionCause` | campos persistidos da causa associados pela relação `causes` | Explicar indisponibilidades e bloqueios sem omissão |

Os nomes e a semântica dos campos são consumidos conforme o Model e schema existentes; não se cria cópia nem snapshot adicional.

## Regras de leitura

- Uma linha representa uma execução declarada realmente persistida.
- Uma execução pode ter zero ou mais causas conforme os dados armazenados; a tela não inventa motivos.
- A tela não consulta o estado atual de Recipe, Food, Nutrient ou referências para completar uma execução antiga.
- A consulta não executa inserções, atualizações, remoções ou recálculos.
- A navegação para montagem utiliza a chave da execução declarada já existente.

## Não entidades

Não serão criados modelos de configuração, perfil regulatório, execução, resultado ou histórico por esta Feature.
