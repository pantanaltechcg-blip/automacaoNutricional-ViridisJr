# Data Model: Recipe Management

## Visão geral

```text
Recipe
  1
  │
  └── N RecipeIngredient
             N
             │
             1 Food (existente na feature 001)
```

Esta feature cria somente `Recipe` e `RecipeIngredient`. `Food` permanece como
entidade da Base Nutricional e não recebe cópia de seus dados nutricionais.

## Recipe

Representa uma receita completa e persistida.

### Tabela `recipes`

| Campo | Tipo PostgreSQL | Obrigatório | Regra |
|---|---|---:|---|
| `id` | `BIGINT` | sim | Chave primária gerada pelo Laravel |
| `name` | `TEXT` | sim | Nome resultante do trim HTTP padrão; sem características adicionais, normalização de caixa ou limite funcional definido nesta feature |
| `yield_value` | `NUMERIC` sem precisão/escala | sim | Decimal exato, finito e maior que zero |
| `yield_unit` | `VARCHAR(2)` | sim | Somente `g` ou `ml` |
| `created_at` | timestamp Laravel | padrão | Auditoria técnica padrão |
| `updated_at` | timestamp Laravel | padrão | Auditoria técnica padrão |

### Constraints

- `PRIMARY KEY (id)`.
- `CHECK (yield_value > 0 AND yield_value <> 'Infinity'::numeric AND yield_value <> 'NaN'::numeric)`.
- `CHECK (yield_unit IN ('g', 'ml'))`.
- Não existe unicidade por nome.
- Não existem campos de marca, tipo, descrição, estado ou ownership.

### Relacionamento Eloquent

- `Recipe hasMany RecipeIngredient`, ordenado por `position` quando a coleção for
  apresentada.

## RecipeIngredient

Representa uma posição da composição informada da receita.

### Tabela `recipe_ingredients`

| Campo | Tipo PostgreSQL | Obrigatório | Regra |
|---|---|---:|---|
| `id` | `BIGINT` | sim | Chave primária técnica; o contrato não promete estabilidade desse identificador |
| `recipe_id` | `BIGINT` | sim | FK para `recipes.id` |
| `food_id` | `BIGINT` | sim | FK para `foods.id` existente |
| `quantity` | `NUMERIC` sem precisão/escala | sim | Decimal exato, finito e maior que zero |
| `unit` | `VARCHAR(1)` | sim | Somente `g` |
| `position` | `BIGINT` | sim | Inteiro maior que zero, único dentro da receita |
| `created_at` | timestamp Laravel | padrão | Auditoria técnica padrão |
| `updated_at` | timestamp Laravel | padrão | Auditoria técnica padrão |

### Constraints e índices

- `PRIMARY KEY (id)`.
- `FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE`.
- `FOREIGN KEY (food_id) REFERENCES foods(id)` sem cascade para `Food`.
- `CHECK (quantity > 0 AND quantity <> 'Infinity'::numeric AND quantity <> 'NaN'::numeric)`.
- `CHECK (unit = 'g')`.
- `CHECK (position > 0)`.
- `UNIQUE (recipe_id, position)` para ordem inequívoca.
- Índice em `food_id` para associação e verificação referencial.
- Não existe `UNIQUE (recipe_id, food_id)`; a specification não proíbe repetir o
  mesmo alimento em posições diferentes.

### Relacionamentos Eloquent

- `RecipeIngredient belongsTo Recipe`.
- `RecipeIngredient belongsTo Food`.
- Não é definido relacionamento inverso em `Food` nesta feature; o Model existente
  permanece inalterado.

## Regras de validade do agregado

Uma receita somente pode ser persistida quando:

- `name` está presente como texto;
- `yield_value` é um decimal positivo e finito;
- `yield_unit` é `g` ou `ml`;
- existe ao menos um `RecipeIngredient` válido;
- cada ingrediente referencia um `foods.id` existente;
- cada quantidade é decimal positiva e finita;
- cada unidade de ingrediente é `g`;
- todas as posições são positivas e distintas dentro da receita.

Na fronteira HTTP, o middleware padrão do Laravel remove espaços externos das
strings, inclusive dentro de arrays, e converte strings vazias resultantes para
`null` antes do Form Request. As regras de obrigatoriedade e validade são aplicadas
ao valor normalizado.

Valores decimais são recebidos e apresentados como strings após essa normalização de
transporte. Não são convertidos para `float`, arredondados, truncados, normalizados
quanto à caixa ou convertidos entre unidades.

## Ordem dos ingredientes

`position` determina exclusivamente a sequência. A leitura ordena por esse campo em
ordem crescente. Lacunas são permitidas. O sistema não:

- renumera automaticamente;
- ordena alfabeticamente;
- ordena por quantidade;
- ordena por `Food`;
- altera outro dado durante a reordenação.

## Persistência e edição

### Criação

1. Validar todo o agregado.
2. Iniciar transação.
3. Persistir `Recipe`.
4. Persistir todos os `RecipeIngredient`.
5. Confirmar a transação somente quando a coleção inteira for válida.

### Edição

1. Validar o agregado completo enviado.
2. Iniciar transação.
3. Atualizar nome e rendimento da receita.
4. Substituir a coleção persistida pela coleção enviada.
5. Confirmar a transação somente após todos os ingredientes serem persistidos.

A ausência de um ingrediente no payload integral significa sua remoção. Uma troca de
`food_id` somente ocorre quando o novo valor é enviado explicitamente. O Service não
altera `Food` e não cria alimentos substitutos.

## Transições de estado

Não existem estados de receita nesta feature. Receita incompleta ou sem ingredientes
é rejeitada e não é persistida como draft. A edição bem-sucedida substitui um estado
válido por outro estado válido dentro de uma única transação.

## Exclusões fora do modelo

Não são armazenados:

- resultados nutricionais;
- contribuições por ingrediente;
- valores por rendimento, 100 g, 100 ml ou porção;
- %VD ou valor energético;
- dados de rotulagem;
- autenticação, usuário proprietário ou histórico;
- características adicionais do produto.
