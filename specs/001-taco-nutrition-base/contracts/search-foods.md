# Contract: Food Search

## Endpoint

`GET /api/foods`

## Query Parameters

### `q`

Parâmetro obrigatório utilizado como termo de pesquisa pelo
nome/descrição do alimento.

Antes da validação e antes de ser entregue ao serviço de busca, o valor
deve ser normalizado pela remoção de espaços externos.

Exemplo:

`" maca "` → `"maca"`

Após a normalização, quando `q` possuir conteúdo, a busca deve ser
executada. Quando estiver ausente ou ficar vazio, a requisição deve falhar
por validação e nenhuma pesquisa deve ser executada.

## Search Behavior

A busca deve:

- aceitar correspondência parcial;
- ignorar diferenças entre maiúsculas e minúsculas;
- ignorar diferenças de acentuação.

## Response

A resposta deve seguir o formato definido em:

`contracts/search-response.json`

A estrutura da resposta é normativa. Cada alimento em `data` contém:

- `id`;
- `name`;
- `category`, quando disponível;
- `source`;
- `source_code`, quando disponível;
- `nutrients`.

Cada item de `nutrients` contém:

- `id`;
- `name`;
- `unit`;
- `amount_value`;
- `status`.

`amount_value` pode ser `null` quando `status` não for `EXACT`.

Os estados permitidos são:

- `EXACT`;
- `TRACE`;
- `NA`;
- `ABSENT`.

Os valores nutricionais representam a composição por `100 g de parte
comestível`, conforme a fonte TACO utilizada. Esta feature não realiza
conversão ou cálculo desses valores.

## Food Identification

O campo `id` representa a identificação interna, estável e não ambígua
do alimento dentro do NutriLabel.

Os campos `source` e `source_code` preservam a rastreabilidade em
relação à fonte original.

Nesta feature:

`source = TACO`
