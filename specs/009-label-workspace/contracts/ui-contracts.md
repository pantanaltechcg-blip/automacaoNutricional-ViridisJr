# UI Contract: Área de Rótulos

Este é um contrato de navegação web server-rendered; não adiciona nem altera contrato de API.

## Listagem de execuções

- **Método/caminho**: `GET /rotulos`
- **Nome Laravel**: `labels.index`
- **Acesso**: sem autenticação nesta etapa; uso interno somente. Não publicar/expor externamente sem controle de acesso posterior.
- **Leitura**: execuções declaradas existentes da Feature 005, ordenadas mais recentes primeiro e paginadas; causas relacionadas são exibidas integralmente.
- **Resposta**: tela HTML com estado vazio quando não houver registros; caso contrário, linhas identificáveis por execução, com origem, data, prontidão, causas e link para montagem daquela execução.
- **Efeitos colaterais**: nenhum. Não cria execução, recalcula ou altera resultados/snapshots.

## Montagem existente

- **Método/caminho existente**: `GET /rotulos/{declaredNutritionCalculation}/montagem`
- **Nome Laravel existente**: `labels.assembly`
- Esta Feature apenas oferece o link para um ID real da listagem. O route model binding existente continua determinando a resposta para IDs inexistentes. Não é introduzido endpoint de escrita ou exportação.

## Navegação

- “Rótulos” deve apontar para `labels.index` e ter estado visual ativo na listagem.
- “Configurações” e qualquer página associada não devem aparecer nesta Feature.
- Nenhuma outra rota ou seção da navegação muda.
