# Research: Recipe Management

## 1. Estrutura relacional de Recipe e RecipeIngredient

**Decision**: Criar `recipes` como raiz do agregado e
`recipe_ingredients` como entidade dependente. Uma receita possui muitos
ingredientes; cada ingrediente pertence a exatamente uma receita e referencia
exatamente um `Food` existente.

**Rationale**: A estrutura representa diretamente as únicas entidades novas da
specification, preserva múltiplos ingredientes e mantém a Base Nutricional como
dependência somente de leitura.

**Alternatives considered**:

- Tabela pivot sem entidade própria: rejeitada porque quantidade, unidade e posição
  pertencem ao ingrediente da receita.
- Duplicar dados de `Food`: rejeitada por FR-005, FR-006 e pela feature 001.
- Adicionar entidades de produto, categoria ou estado da receita: rejeitado por
  ausência de requisito.

## 2. Representação decimal de quantidade e rendimento

**Decision**: Usar `NUMERIC` PostgreSQL sem precisão nem escala declaradas para
`recipe_ingredients.quantity` e `recipes.yield_value`. Na fronteira HTTP e na
serialização, representar esses valores como strings decimais de base 10, sem
expoente, para evitar conversão por ponto flutuante. As migrations continuam sendo
a fonte do schema, usando DDL PostgreSQL direcionado para os campos e constraints
que o Schema Builder não expressa sem escolher precisão e escala.

**Rationale**: A specification permite frações, não define precisão máxima e proíbe
arredondamento silencioso. O PostgreSQL documenta que `NUMERIC` sem parâmetros é
exato, não força uma escala e aceita valores até os limites próprios do banco. Já o
método `decimal()` do Laravel 13 exige `total` e `places` e, por padrão, produziria
`decimal(8,2)`, o que introduziria uma decisão arbitrária. Strings impedem que o PHP
converta previamente o valor para `float`.

Constraints nomeadas devem rejeitar valores não positivos e os valores especiais
`NaN` e `Infinity` aceitos pelo `NUMERIC` não parametrizado. A validação HTTP deve
aceitar somente notação decimal positiva comum e preservar a representação numérica
resultante da normalização de transporte.

**Alternatives considered**:

- `DECIMAL(10,2)`, `DECIMAL(10,3)` ou outra escala fixa: rejeitado porque
  arredondaria ou recusaria entradas com precisão não aprovada.
- `double precision`/`float`: rejeitado por ser inexato.
- Armazenar apenas texto: rejeitado porque enfraquece a integridade numérica e a
  constraint de positividade do PostgreSQL.
- Introduzir biblioteca de decimal: rejeitado porque nenhuma dependência nova foi
  aprovada e o PostgreSQL já fornece representação exata.

### Eloquent e preservação decimal

`quantity` e `yield_value` não devem utilizar casts Eloquent que
convertam os valores para `float`, `double` ou para um cast decimal com
escala fixa (`decimal:N`).

Os valores devem permanecer representados como strings decimais na
fronteira da aplicação, preservando exatamente a representação numérica
armazenada pelo PostgreSQL, sem arredondamento introduzido pelo PHP.

**Primary references**:

- PostgreSQL, Numeric Types: `https://www.postgresql.org/docs/current/datatype-numeric.html`
- Laravel 13, Migrations — `decimal()`: documentação e implementação instalada em
  `vendor/laravel/framework/src/Illuminate/Database/Schema/Blueprint.php` e
  `PostgresGrammar.php`.

## 3. Representação das unidades

**Decision**: Persistir `recipe_ingredients.unit` como valor obrigatório restrito a
`g`. Persistir `recipes.yield_unit` como valor obrigatório restrito a `g` ou `ml`.
Form Requests e constraints PostgreSQL aplicam as mesmas listas fechadas.

**Rationale**: As unidades são decisões funcionais explícitas e devem ser
preservadas, não inferidas. Colunas textuais curtas com `CHECK` evitam a criação de
tipos enumerados globais e mantêm a migration reversível.

**Alternatives considered**:

- Omitir a unidade fixa do ingrediente: rejeitado porque o contrato deve preservar
  a unidade informada.
- Enum PostgreSQL: rejeitado por adicionar um tipo de schema desnecessário para
  listas pequenas e locais.
- Converter `kg`, `ml` ou medidas caseiras: rejeitado por estar fora do escopo.

## 4. Armazenamento da posição

**Decision**: Usar `recipe_ingredients.position` como inteiro positivo, com
unicidade composta `(recipe_id, position)`. Ingredientes são retornados em ordem
crescente de `position`. Não exigir posições contíguas e não reordenar por nome,
quantidade ou alimento.

**Rationale**: Uma posição positiva e única fornece ordem inequívoca e preserva
exatamente os valores escolhidos. Permitir lacunas evita introduzir uma regra de
normalização que a specification não aprovou.

**Alternatives considered**:

- Usar somente a ordem física de inserção: rejeitado por não ser garantia
  relacional.
- Normalizar automaticamente para `1..N`: rejeitado por criar comportamento extra.
- Ordenar por outro atributo: rejeitado expressamente pelo escopo.

## 5. Integridade referencial com foods.id

**Decision**: `recipe_ingredients.food_id` é obrigatório e possui foreign key para
`foods.id`, sem ação em cascata em direção a `Food`. A coluna recebe índice próprio
para consultas e verificações referenciais. Remover um ingrediente ou uma receita
remove somente dados do agregado de receita.

**Rationale**: A FK garante que o alimento exista e impede referências órfãs. A
ausência de cascade protege a Base Nutricional contra modificações produzidas pelo
gerenciamento de receitas.

**Alternatives considered**:

- Copiar alimento para a receita: rejeitado por duplicar a Base Nutricional.
- `ON DELETE CASCADE` a partir de `Food`: rejeitado porque transformaria uma ação
  externa sobre a base em exclusão silenciosa de ingredientes.
- Definir endpoint ou política funcional de exclusão de `Food`: não considerado;
  permanece fora do escopo. A ação padrão `NO ACTION` da FK é somente uma proteção
  de integridade, não uma nova operação funcional.

## 6. Transação para receita e ingredientes

**Decision**: `RecipeService` executa criação e edição dentro de
`DB::transaction`. A receita e toda a coleção de ingredientes confirmam juntas ou
são integralmente revertidas.

**Rationale**: A persistência de uma receita sem ingredientes é proibida. Uma
transação impede estados intermediários persistentes e é suportada nativamente por
Eloquent e Query Builder.

**Alternatives considered**:

- Salvar a receita antes e ingredientes depois sem transação: rejeitado porque uma
  falha deixaria receita inválida persistida.
- Transação no Controller: rejeitada pela separação constitucional de
  responsabilidades.

## 7. Atualização da coleção de ingredientes

**Decision**: `PUT /api/recipes/{recipe}` recebe o agregado completo. Após validar
todo o payload, o Service substitui a coleção existente pela coleção enviada dentro
da mesma transação. Itens omitidos são removidos; itens presentes são recriados com
`food_id`, quantidade, unidade e posição exatamente informados.

**Rationale**: A specification não exige identidade interna estável para
`RecipeIngredient`. A substituição integral reduz ambiguidade, cobre adicionar,
editar, remover e reordenar e torna o estado final reproduzível sem endpoints
especializados.

**Alternatives considered**:

- Endpoints separados para cada operação do ingrediente: rejeitados por ampliar a
  superfície HTTP sem necessidade.
- Upsert por `(recipe_id, food_id)`: rejeitado porque a specification não proíbe o
  mesmo `Food` em mais de uma posição.
- Expor `RecipeIngredient.id` como identidade estável: rejeitado por não ser
  necessário aos comportamentos aprovados.

## 8. Validação de receita completa

**Decision**: Form Requests exigem:

- `name` presente e textual após a normalização HTTP padrão, sem normalização de
  caixa ou limite funcional adicional;
- `yield_value` como string decimal positiva;
- `yield_unit` em `g` ou `ml`;
- `ingredients` como array com ao menos um item;
- `food_id` existente em `foods.id`;
- `quantity` como string decimal positiva;
- `unit` exatamente `g`;
- `position` inteira, positiva e distinta dentro do payload.

O Service recebe somente dados validados e aplica a transação. Constraints no banco
repetem as invariantes essenciais: valores positivos e finitos, unidades fechadas,
FKs e posição única.

**Rationale**: A validação em camadas produz erros HTTP antes da escrita e mantém a
integridade quando o banco é acessado por outro caminho. Antes da resolução e da
validação do Form Request, permanecem ativos os middlewares globais do Laravel
`TrimStrings` e `ConvertEmptyStringsToNull`: todas as strings, inclusive em arrays
aninhados, recebem trim de espaços externos e uma string vazia resultante é
convertida para `null`. O Form Request valida esse valor resultante.

Esse comportamento é somente normalização de transporte HTTP. Não ocorre
normalização de caixa, conversão entre unidades, arredondamento, correção automática
de valores de domínio ou conversão silenciosa das strings decimais em valores
numéricos. `quantity` e `yield_value` continuam atravessando a fronteira da
aplicação como strings decimais de base 10.

**Alternatives considered**:

- Aceitar receita vazia como rascunho: rejeitado pela clarification.
- Aceitar número JSON como `float`: rejeitado pelo risco de perda de precisão antes
  da persistência.
- Definir tamanho máximo de nome ou casas decimais: rejeitado por ausência de
  decisão funcional.

## 9. Contratos HTTP mínimos

**Decision**: Expor somente:

- `POST /api/recipes` — cria uma receita completa;
- `GET /api/recipes/{recipe}` — consulta uma receita;
- `PUT /api/recipes/{recipe}` — substitui a receita e sua coleção completa.

As respostas usam o envelope `data`, seguindo a convenção de
`GET /api/foods`. O contrato de escrita inclui nome, rendimento, unidade do
rendimento e todos os ingredientes com `food_id`, quantidade, unidade e posição.

**Rationale**: Os três endpoints cobrem integralmente criar, salvar, reabrir,
editar, adicionar, remover e reordenar. Não há requisito para listar, pesquisar,
duplicar ou excluir receitas.

**Alternatives considered**:

- `PATCH` parcial: rejeitado porque tornaria as regras de coleção parcial e
  completude mais ambíguas.
- Endpoints de item e reordenação: rejeitados porque a substituição do agregado já
  cobre esses comportamentos.
- Endpoint de exclusão ou listagem: rejeitado por não constar na specification.

## 10. Estratégia de testes com PostgreSQL

**Decision**: Usar PHPUnit/Laravel Testing com `RefreshDatabase` sobre a conexão
`pgsql` já selecionada em `phpunit.xml`. Testes de feature validam contratos HTTP e
reabertura; testes de persistência verificam transação, constraints, referências a
`Food`, substituição da coleção e preservação decimal sem conversão para `float`.

**Rationale**: `NUMERIC` sem escala, constraints e FKs são comportamentos do
PostgreSQL e não devem ser simulados por SQLite. O ambiente existente já executa a
suíte com PostgreSQL e não versiona credenciais.

**Alternatives considered**:

- SQLite em memória: rejeitado por não estar disponível e não reproduzir as
  decisões específicas do PostgreSQL.
- Mocks para persistência: rejeitados porque não validam schema, transações ou FKs.
- Testar cálculos nutricionais: rejeitado por estar fora do escopo.

## Pendências funcionais

Nenhuma pendência bloqueia o design aprovado. A política funcional de exclusão de
um `Food` já referenciado continua deliberadamente fora desta feature; não será
criado endpoint nem comportamento para isso. Qualquer mudança futura exige nova
decisão de specification. Códigos HTTP específicos também não foram aprovados e,
seguindo a decisão já adotada no contrato da feature 001, não são fixados neste
plano; rotas, payloads, resultados e condições de falha permanecem documentados.
