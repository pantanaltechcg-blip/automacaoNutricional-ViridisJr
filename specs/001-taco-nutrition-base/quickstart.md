# Quickstart Validation: 001-taco-nutrition-base

## Objetivo

Este documento descreve como preparar e validar a feature
`001-taco-nutrition-base` no ambiente de desenvolvimento.

A validação deve confirmar:

- preservação dos alimentos provenientes da TACO;
- preservação dos componentes nutricionais;
- preservação das unidades;
- distinção entre `EXACT`, `TRACE`, `NA` e `ABSENT`;
- rastreabilidade da fonte;
- pesquisa parcial pelo nome/descrição;
- pesquisa independente de maiúsculas/minúsculas e acentuação.

Esta feature não realiza cálculos nutricionais.

---

## Pré-requisitos

- PHP conforme versão utilizada pelo projeto.
- Laravel conforme versão instalada no projeto.
- PostgreSQL configurado e em execução.
- Banco de desenvolvimento configurado no `.env`.
- Dependências PHP instaladas.

Antes da validação:

```bash
composer install
```

Caso necessário, configure o `.env` local conforme o ambiente de
desenvolvimento.

---

## 1. Preparar o Banco de Dados

Execute as migrations da aplicação:

```bash
php artisan migrate
```

As migrations desta feature devem preparar a estrutura necessária para:

- `foods`;
- `nutrients`;
- `food_nutrients`;
- demais requisitos técnicos aprovados nos artefatos desta feature.

Não é necessário utilizar `migrate:fresh` para validar a feature em um
banco de desenvolvimento já existente.

---

## 2. Preparar a Base Nutricional

Execute o mecanismo de importação definido durante a implementação desta
feature para carregar os dados da TACO.

O mecanismo concreto de importação deve seguir as decisões documentadas
em:

- `spec.md`;
- `plan.md`;
- `research.md`;
- `data-model.md`.

A importação deve preservar:

- nome/descrição original do alimento;
- categoria/grupo quando disponível;
- origem dos dados;
- código original da fonte quando disponível;
- componentes nutricionais;
- unidades;
- valores numéricos;
- `TRACE`;
- `NA`;
- `ABSENT` somente quando aplicável segundo as regras definidas no
  `research.md`.

A importação não deve realizar cálculos nutricionais ou converter
marcadores qualitativos para zero.

---

## 3. Validar os Dados Importados

Após a importação, selecione registros de referência e compare os dados
armazenados com a fonte TACO utilizada pelo projeto.

Verifique especificamente:

### Valor numérico

Um valor numérico deve resultar conceitualmente em:

```text
status = EXACT
amount_value = valor original
```

### Traço

Quando a fonte apresentar `Tr`:

```text
status = TRACE
amount_value = sem valor numérico
```

### Não aplicável

Quando a fonte apresentar `NA`:

```text
status = NA
amount_value = sem valor numérico
```

### Ausente

Quando existir explicitamente uma posição na estrutura da fonte sem
valor:

```text
status = ABSENT
amount_value = sem valor numérico
```

A ausência de uma relação entre alimento e nutriente não deve ser
automaticamente interpretada como `ABSENT`.

---

## 4. Testar a Pesquisa de Alimentos

A feature deve disponibilizar a consulta definida no contrato desta
feature.

A pesquisa deve:

- utilizar o nome/descrição do alimento;
- aceitar correspondência parcial;
- ignorar diferenças entre maiúsculas e minúsculas;
- ignorar diferenças de acentuação.

Exemplo conceitual:

```text
Consulta:
maca

Deve conseguir localizar:
Maçã, ...
```

A rota HTTP concreta deve corresponder ao contrato aprovado em
`contracts/`.

Não fazem parte deste teste:

- pesquisa por categoria;
- Full-Text Search;
- ranqueamento;
- busca semântica.

---

## 5. Validar a Resposta

A resposta da consulta deve preservar, no mínimo:

- identificação do alimento;
- nome;
- categoria quando disponível;
- fonte;
- código original da fonte quando disponível;
- componentes nutricionais;
- unidade de cada componente;
- valor numérico quando disponível;
- estado do valor.

Exemplo de estrutura:

```json
{
  "data": [
    {
      "id": 1,
      "name": "Exemplo de alimento",
      "category": "Exemplo de categoria",
      "source": "TACO",
      "source_code": "EXEMPLO",
      "nutrients": [
        {
          "id": 1,
          "name": "Exemplo de componente",
          "unit": "g",
          "amount_value": 1.25,
          "status": "EXACT"
        }
      ]
    }
  ]
}
```

Os valores acima são apenas exemplos de formato e não representam dados
reais da TACO.

---

## 6. Executar os Testes Automatizados

Execute os testes da aplicação:

```bash
php artisan test
```

Os testes referentes a esta feature devem validar, no mínimo:

- importação/preservação do alimento;
- preservação da fonte;
- preservação do código original quando disponível;
- preservação dos componentes nutricionais;
- preservação das unidades;
- valor numérico com status `EXACT`;
- `TRACE` diferente de zero;
- `NA` diferente de zero;
- `ABSENT` diferente de zero;
- distinção entre `TRACE`, `NA` e `ABSENT`;
- unicidade da combinação alimento + nutriente;
- busca parcial por nome;
- busca sem diferenciação entre maiúsculas/minúsculas;
- busca sem diferenciação de acentuação.

---

## 7. Critério de Validação da Feature

A feature poderá ser considerada validada quando:

1. os dados de referência importados corresponderem à fonte;
2. nenhum marcador `TRACE`, `NA` ou `ABSENT` tiver sido convertido
   silenciosamente para zero;
3. os componentes nutricionais preservarem suas unidades;
4. a origem TACO permanecer rastreável;
5. a pesquisa pelo nome funcionar conforme definido na specification;
6. os testes automatizados relacionados à feature estiverem passando.

A validação desta feature não inclui receitas, cálculos nutricionais,
porções, %VD, arredondamento regulatório ou geração de rótulos.