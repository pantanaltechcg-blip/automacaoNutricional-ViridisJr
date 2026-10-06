# Research & Decisions: 001-taco-nutrition-base

## 1. Modelagem flexível dos componentes nutricionais

### Decision

Utilizar uma modelagem relacional composta por três conceitos principais:

- `Food`
- `Nutrient`
- `FoodNutrient`

A relação entre alimento e componente nutricional será representada por
`FoodNutrient`, que armazenará o valor numérico, quando existir, e o
estado qualitativo correspondente.

A modelagem não deve exigir uma coluna específica no schema para cada
componente nutricional da TACO.

### Rationale

A specification definiu que todos os componentes nutricionais
disponíveis na TACO devem ser preservados.

Uma estrutura com uma coluna fixa para cada nutriente criaria forte
acoplamento entre o schema do banco e a estrutura atual da fonte.

A separação entre:

Food
    ↓
FoodNutrient
    ↓
Nutrient

permite representar diferentes componentes nutricionais mantendo:

- identificação do alimento;
- identificação do componente;
- unidade;
- valor;
- estado do valor;
- rastreabilidade da fonte.

`FoodNutrient` deve ser tratado como uma entidade relacional com dados
próprios, e não apenas como uma tabela pivô sem significado de domínio.

### Alternatives considered

#### Colunas fixas em `foods`

Exemplo conceitual:

- protein
- carbohydrates
- sodium
- calcium
- iron
- etc.

Descartada porque a specification exige a preservação de todos os
componentes disponíveis na TACO, o que resultaria em forte acoplamento
ao formato atual da fonte e exigiria alterações no schema sempre que
novos componentes precisassem ser incorporados.

#### JSONB

Uma coluna JSONB permitiria armazenar componentes nutricionais de forma
flexível.

Não foi escolhida porque a estrutura relacional Food → FoodNutrient →
Nutrient preserva de forma mais explícita:

- identidade dos componentes;
- unidades;
- restrições de integridade;
- relacionamentos;
- estados dos valores.

A escolha não impede revisão futura, mas para esta feature será utilizada
a estrutura relacional definida acima.

---

## 2. Nomenclatura do domínio e origem dos dados

### Decision

Os conceitos principais do domínio não utilizarão `Taco` como prefixo.

Serão utilizados conceitos genéricos:

- `Food`
- `Nutrient`
- `FoodNutrient`

A origem dos dados deve ser preservada explicitamente no alimento através
dos dados de fonte.

Para esta feature, a fonte será:

`TACO`

A fonte autoritativa é o arquivo
`resources/data/taco/source/taco_4_edicao_ampliada_e_revisada.pdf`, correspondente à Tabela
Brasileira de Composição de Alimentos — TACO, 4ª edição revisada e
ampliada, NEPA/UNICAMP, 2011.

### Rationale

A TACO é a fonte inicial da base nutricional, mas não representa o
conceito de alimento em si.

Utilizar o nome da fonte como prefixo nas entidades acoplaria os conceitos
do domínio diretamente à TACO.

O planejamento técnico já determina que a origem dos dados deve permanecer
rastreável.

Portanto, o domínio será representado independentemente do nome da fonte,
sem que isso implique suporte a outras bases nutricionais nesta feature.

Esta feature continua limitada à TACO.

---

## 3. Identificação dos alimentos da fonte

### Decision

Cada alimento deve preservar:

- o nome/descrição original utilizado na TACO;
- categoria/grupo quando disponível;
- fonte;
- código ou identificação original da fonte quando disponível.

Para os alimentos provenientes da TACO nesta feature, a identidade lógica
utilizada durante importação e reimportação será:

`source + source_code`

Nesta feature, `source = TACO`, e `source_code` deve preservar o
número/código original do alimento fornecido pela TACO.

Durante a reimportação, quando existir um `Food` com o mesmo
`source + source_code`, esse registro deve ser atualizado sem criação de
duplicata e com preservação do mesmo `foods.id`.

Para registros da TACO que deveriam representar alimentos, a ausência de
`source_code` deve ser tratada como problema de importação. O nome não deve
ser utilizado como fallback silencioso.

O nome utilizado na aplicação deve preservar a descrição do alimento
proveniente da TACO.

Não será criado nesta feature um campo adicional de descrição ou
observação sem que exista um campo distinto correspondente na fonte.

### Rationale

A specification exige preservação da identificação e descrição do
alimento, além da rastreabilidade de sua origem.

Não existe requisito aprovado para armazenar observações adicionais
criadas pelo sistema.

---

## 4. Representação dos componentes nutricionais

### Decision

Cada componente nutricional será representado separadamente e deverá
preservar:

- nome do componente;
- unidade utilizada pela fonte.

Não será criado nesta feature um campo de símbolo ou sigla próprio do
NutriLabel.

Caso a fonte possua um identificador oficial necessário à importação,
sua utilização deverá preservar exatamente a identificação existente na
fonte, sem criação de siglas pelo sistema.

A identidade lógica de um `Nutrient` nesta feature será:

`name + unit`

Durante a reimportação, um `Nutrient` existente deve ser reutilizado quando
`name + unit` coincidirem, sem criação de duplicata para o mesmo par.

### Rationale

A specification exige preservação dos componentes e de suas unidades,
mas não definiu símbolos ou abreviações próprias do NutriLabel.

Nenhuma identificação adicional deve ser inventada.

---

## 5. Representação de valores e estados

### Decision

A informação nutricional associada a um alimento e componente deverá
manter separadamente:

1. valor numérico, quando a fonte fornecer um valor numérico;
2. estado do dado.

Os estados definidos pela specification são:

- `EXACT`
- `TRACE`
- `NA`
- `ABSENT`

Correspondência conceitual:

- `EXACT` → EXATO
- `TRACE` → TRACO
- `NA` → NÃO APLICÁVEL
- `ABSENT` → AUSENTE

Nenhum desses estados poderá ser convertido silenciosamente para zero.

### Rationale

A Constitution e a specification determinam que:

- zero numérico;
- traço;
- não aplicável;
- ausência;

são situações diferentes.

A separação entre valor e estado impede a perda dessa distinção.

A interpretação matemática desses estados permanece explicitamente fora
desta feature.

---

## 6. Representação do estado AUSENTE

### Decision

`ABSENT` será utilizado somente quando a própria estrutura da fonte
apresentar explicitamente uma posição para determinado componente de um
alimento e essa posição estiver sem valor.

Não será criado automaticamente um registro `ABSENT` para todas as
combinações possíveis entre alimentos e nutrientes.

A importação não deve gerar o produto cartesiano entre todos os alimentos
e todos os componentes nutricionais.

### Rationale

A TACO diferencia valores numéricos, `Tr`, `NA` e células sem valor.

A ausência de uma relação `FoodNutrient` não deve ser automaticamente
interpretada como um valor nutricional ausente, pois isso poderia criar
uma informação que a própria fonte não declarou.

Quando existir na fonte uma célula correspondente ao componente e essa
célula estiver vazia, sua ausência poderá ser preservada explicitamente
como `ABSENT`.

A interpretação matemática de `ABSENT` continuará fora do escopo desta
feature.

---

## 7. Precisão dos valores numéricos

### Decision

A modelagem deve utilizar um tipo numérico capaz de preservar os valores
fornecidos pela TACO sem aplicar arredondamento adicional durante a
importação.

Nenhuma precisão ou escala fixa será definida neste documento sem
validação prévia dos valores existentes na fonte.

### Rationale

A Constitution determina que valores intermediários e dados de origem não
devem sofrer perda prematura de precisão.

Definir arbitrariamente algo como:

`DECIMAL(10,4)`

sem verificar a precisão necessária poderia modificar os valores originais.

A definição concreta utilizada na migration deverá garantir que os valores
da TACO sejam preservados sem perda.

---

## 8. Regra de unicidade alimento × nutriente

### Decision

Para esta feature, um alimento deve possuir no máximo um registro de
informação nutricional para o mesmo componente dentro da mesma base
importada.

A combinação:

`food_id + nutrient_id`

deve ser única em `food_nutrients`.

Durante a reimportação, a associação correspondente deve ser atualizada ou
criada utilizando `food_id + nutrient_id`, sem criação de duplicatas para a
mesma combinação.

### Rationale

O registro representa a informação daquele componente nutricional para
100 g de parte comestível do alimento conforme a fonte utilizada nesta
feature.

Registros duplicados para a mesma combinação comprometeriam a
identificação inequívoca do valor.

---

## 9. Busca parcial por alimento

### Decision

A busca será realizada exclusivamente pelo nome/descrição original
armazenado em `foods.name`.

O comportamento definido pela specification é:

- correspondência parcial;
- ignorar diferenças entre maiúsculas e minúsculas;
- ignorar diferenças de acentuação.

O parâmetro `q` deve ter os espaços externos removidos antes da validação
e antes de ser entregue ao serviço de busca. Por exemplo, `" maca "` será
normalizado para `"maca"`. Após a normalização, um valor com conteúdo será
pesquisado; um valor vazio falhará por validação e nenhuma pesquisa será
executada.

No PostgreSQL, a implementação utilizará:

- `ILIKE` para comparação sem diferenciação entre maiúsculas/minúsculas;
- extensão `unaccent` para normalização de acentuação.

### Rationale

Esse comportamento corresponde exatamente ao definido durante a etapa
Clarify.

Não fazem parte desta feature:

- Full-Text Search;
- busca por categoria;
- ranqueamento;
- busca semântica;
- sistemas externos de pesquisa.

### Technical Requirement

A extensão `unaccent` do PostgreSQL deverá ser habilitada e validada no
ambiente do projeto.

Essa utilização não altera o comportamento funcional definido na
specification.

---

## 10. Importação da TACO

### Decision

A feature deve possuir um processo controlado para transformar os dados da
TACO em registros da base nutricional.

A fonte autoritativa desse processo é o arquivo
`resources/data/taco/source/taco_4_edicao_ampliada_e_revisada.pdf`, Tabela Brasileira de Composição de
Alimentos — TACO, 4ª edição revisada e ampliada, NEPA/UNICAMP, 2011.

Os artefatos estruturados derivados exclusivamente dessa fonte serão:

- `resources/data/taco/processed/foods.csv`, contendo os alimentos e os
  dados aprovados para `Food`;
- `resources/data/taco/processed/nutrients.csv`, contendo os componentes
  nutricionais e suas unidades;
- `resources/data/taco/processed/food_nutrients.csv`, contendo as relações
  entre alimentos e nutrientes, com valor e estado `EXACT`, `TRACE`, `NA`
  ou `ABSENT` conforme as regras aprovadas.

A importação será executada pelo Laravel Artisan Command:

`php artisan nutrition:import-taco`

O Command consumirá os três arquivos em `resources/data/taco/processed/`
e delegará a persistência e as regras aprovadas ao
`NutritionBaseService`. O Command não conterá cálculos nutricionais nem
novas regras de domínio.

A implementação deverá preservar:

- alimentos;
- categorias/grupos quando disponíveis;
- identificação original;
- componentes nutricionais;
- unidades;
- valores numéricos;
- `TRACE`;
- `NA`;
- `ABSENT` somente nas condições definidas neste research.

O processo de importação não realizará cálculos nutricionais.

### Rationale

A base nutricional precisa ser criada a partir da fonte fornecida ao
projeto de forma reproduzível e verificável.

### Constraint

Este research não exige que a importação seja implementada
especificamente como:

- Seeder;
- Controller;
- endpoint HTTP.

A forma concreta deve permanecer compatível com a arquitetura Laravel e
ser escolhida durante a implementação sem alterar os requisitos definidos
neste documento.

---

## 11. Integração com a arquitetura Laravel

### Decision

A feature seguirá a arquitetura definida pela Constitution:

Frontend
    ↓
Routes
    ↓
Controllers
    ↓
Services
    ↓
Models
    ↓
PostgreSQL

Os conceitos principais do domínio serão:

- `Food`
- `Nutrient`
- `FoodNutrient`

Controllers devem permanecer responsáveis apenas pela orquestração das
requisições.

A lógica relacionada à consulta da base nutricional deve permanecer fora
dos Controllers.

### Rationale

Essa separação já foi determinada pela Constitution do NutriLabel.

A fonte TACO não deve aparecer como prefixo obrigatório dos principais
conceitos de domínio.

---

## 12. Escopo preservado

As decisões deste research não adicionam suporte a:

- receitas;
- cálculos nutricionais;
- cálculo por porção;
- %VD;
- arredondamento regulatório;
- rotulagem;
- PDF;
- autenticação;
- outras bases nutricionais.

Essas funcionalidades permanecem fora da feature
`001-taco-nutrition-base`.
