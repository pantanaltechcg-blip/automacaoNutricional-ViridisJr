# Implementation Plan: Área de Rótulos

**Branch**: `codex/009-label-workspace` | **Date**: 2026-11-27 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/009-label-workspace/spec.md`.

## Summary

Disponibilizar uma tela interna de consulta, somente leitura, das execuções de declaração nutricional já persistidas pela Feature 005. A listagem apresentará identificação histórica, prontidão e todas as causas armazenadas, com navegação para a montagem já existente. A navegação principal removerá “Configurações” e passará a oferecer “Rótulos”. Não serão criadas execuções nem alterados cálculos, snapshots, referências ou bloqueios regulatórios.

## Technical Context

**Language/Version**: PHP 8.4; Blade, HTML, CSS e JavaScript existentes.
**Primary Dependencies**: Laravel 13 e dependências atuais do projeto; nenhuma dependência nova.
**Storage**: PostgreSQL existente, consultado sem escrita por esta tela.
**Testing**: PHPUnit via `php artisan test`; testes de tela existentes em `tests/Feature/Frontend`.
**Target Platform**: Aplicação web Laravel para uso interno da empresa.
**Project Type**: Aplicação web Laravel monolítica.
**Performance Goals**: A listagem deve paginar resultados e carregar causas em lote; não deve consultar receita ou calcular valores por registro.
**Constraints**: Sem autenticação nesta etapa por decisão aprovada. Não expor publicamente; ambiente/rede de execução deve limitar o acesso. Sem persistência nova, sem cálculos e sem apresentação de dados fictícios.
**Scale/Scope**: Uma tela de listagem e um ajuste na navegação existente, consumindo execuções da Feature 005.

## Constitution Check

- **Não invenção / regras regulatórias**: PASS. A tela apenas apresenta resultados e causas já persistidos; não cria valores nem altera gates MAP, ENE, VDR, fontes ou conversões.
- **Stack e dependências**: PASS. Reutiliza Laravel, Eloquent, Blade e PostgreSQL existentes; não adiciona pacote.
- **Separação de responsabilidades**: PASS. A rota delega ao Controller de tela; o Controller consulta Model; a view apresenta dados e não calcula.
- **Precisão e imutabilidade**: PASS. Nenhum cálculo, arredondamento, recálculo ou escrita será feito.
- **Segurança de uso interno**: PASS COM RESSALVA OPERACIONAL. Não adicionar autenticação atende à decisão do Clarify; a aplicação não pode ser publicada/exposta fora da rede interna sem decisão e controle de acesso posteriores.
- **Rastreabilidade/histórico**: PASS. Exibir IDs das execuções de origem e causas persistidas; não reconstruir identidade por dados atuais da Recipe.

## Research Decisions

1. **Fonte de listagem**: `DeclaredNutritionCalculation`, já responsável pelas execuções declaradas preservadas pela Feature 005.
2. **Identificação histórica**: usar `id`, `source_recipe_id`, `source_nutrition_calculation_id` e `created_at`. Não buscar o nome atual da receita, pois ele pode divergir do snapshot e a tela não deve depender do estado atual.
3. **Causas**: carregar a relação `causes` para cada execução e apresentar todas as causas fornecidas, sem filtrar por status ou reinterpretar códigos como sucesso.
4. **Consulta escalável**: ordenação determinística mais recente primeiro (`created_at`, depois `id`) e paginação padrão do Laravel, 25 itens por página.
5. **Navegação**: adicionar a rota nomeada `labels.index` em `/rotulos`; preservar a rota existente `labels.assembly` e só oferecer o link de montagem para um registro real.
6. **Acesso**: não há autenticação no escopo. Restrição a uso interno é requisito operacional de implantação, não um mecanismo de segurança implementado nesta feature.

## Architecture and Flow

```text
GET /rotulos
  -> Route labels.index
  -> LabelIndexScreenController (orquestração)
  -> LabelExecutionListingService (consulta paginada + eager-load de causes)
  -> DeclaredNutritionCalculation
  -> resources/views/rotulonutri/labels.blade.php
  -> link opcional para rota existente labels.assembly/{id}/montagem
```

O Controller apenas orquestra a tela e delega a leitura ao `LabelExecutionListingService`; esse serviço executa somente a consulta Eloquent necessária, sem regra regulatória ou escrita. A view limita-se à apresentação e navegação. O layout deixa de renderizar “Configurações” e ativa “Rótulos”. A montagem existente continua sendo uma consulta à execução selecionada; esta feature não altera seu comportamento.

## Planned Files

```text
app/Http/Controllers/LabelIndexScreenController.php  # nova tela de consulta
app/Services/LabelExecutionListingService.php        # consulta de leitura e paginação
routes/web.php                                       # rota GET nomeada labels.index
resources/views/rotulonutri/labels.blade.php         # lista, causas e estado vazio
resources/views/layouts/rotulonutri.blade.php        # link Rótulos ativo; remover Configurações
tests/Feature/Frontend/LabelIndexScreenTest.php      # cobertura de leitura e navegação
tests/Feature/Frontend/ShellTest.php                 # alinhar expectativa do menu compartilhado
```

Não estão previstos Model, migration, API, alteração de schema, mudança funcional nas Features 001–008 ou novo asset/dependência. O ajuste em `ShellTest.php` atualiza somente a expectativa do menu compartilhado. O serviço previsto limita-se à consulta e paginação desta tela.

## Data Flow and Boundaries

- O serviço de listagem consulta somente `declared_nutrition_calculations` e suas causas persistidas.
- Cada linha mostra identificadores da própria execução e de suas origens, timestamp e status `is_label_ready`.
- Todas as causas associadas são exibidas sem omissão silenciosa. A apresentação não converte causas, ausência de relação, estados qualitativos ou valores nulos em sucesso ou zero.
- A listagem não consulta Recipe, Food, Nutrient nem referências atuais; não recompõe snapshots.
- A navegação para montagem usa o ID da execução declarada existente. ID inexistente mantém o 404 do route model binding.
- Nenhuma requisição da tela cria, atualiza ou remove dados.

## Test Strategy

- Testar GET da listagem com conjunto vazio e com múltiplas execuções.
- Verificar que IDs de execução, origem, prontidão e todas as causas aparecem corretamente, inclusive para registros bloqueados/incompletos.
- Verificar ordenação determinística, paginação e link de montagem com o ID correto; ID não existente deve continuar indisponível/404 na rota existente.
- Verificar que a tela não depende do nome atual da Recipe e não altera execuções nem snapshots.
- Verificar navegação: “Rótulos” aponta para a listagem e “Configurações” não aparece.
- Executar testes de regressão do fluxo de montagem e demais telas Frontend, seguido da suíte completa, Pint e `git diff --check`.
- Usar apenas fixtures isoladas em testes; nenhum dado de referência real ou valor regulatório deve ser promovido.

## Risks and Operational Notes

- **Exposição sem autenticação**: a aplicação não pode ser exposta publicamente; antes de qualquer publicação externa, deve haver decisão posterior e controles de acesso apropriados.
- **Interpretação do status**: `is_label_ready` é apresentado conforme persistido; não significa homologação regulatória externa e a tela deve preservar os avisos/canais de bloqueio existentes.
- **Dependência Feature 005**: a lista ficará vazia até existirem execuções declaradas reais. Isso é estado válido, não erro a contornar com amostras.
- **Geleia de Amora e matrizes**: MAP/ENE/VDR, fontes, conversões e correspondências permanecem condicionadas às autorizações e validações existentes; esta Feature não muda essas condições.

## Project Structure

```text
app/
├── Http/Controllers/
│   └── LabelIndexScreenController.php
└── Services/
    └── LabelExecutionListingService.php
resources/views/
├── layouts/
│   └── rotulonutri.blade.php
└── rotulonutri/
    └── labels.blade.php
routes/
└── web.php
tests/Feature/Frontend/
└── LabelIndexScreenTest.php
```

**Structure Decision**: seguir a estrutura monolítica Laravel já usada nas telas de Receitas, Tabela, Cálculo e Montagem; nenhuma nova camada ou diretório de aplicação será introduzido.

## Complexity Tracking

Sem violações da Constitution que exijam justificativa.
