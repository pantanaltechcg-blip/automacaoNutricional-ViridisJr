# Backlog — Cadastro manual de alimentos ausentes da TACO

**Origem**: solicitação da Viridis para permitir cadastrar um alimento quando ele não estiver disponível na base TACO.

**Estado**: aguardando Specification própria; nenhum número de feature foi atribuído.

## Separação de escopo

- A Feature 001 importa e preserva a base oficial TACO.
- A Feature 002 permite usar em receitas um `Food` já existente; ela não cria substitutos quando o alimento informado não existe.
- As Features 003 e 004 consomem dados nutricionais e execuções preservadas; não criam nem alteram alimentos.
- Portanto, cadastrar um novo alimento e sua composição nutricional não está coberto pelas Features 001–004 e não integra a Feature 004.

## Necessidade registrada

Avaliar uma funcionalidade própria e governada para cadastrar alimentos ausentes da TACO, permitir sua busca e uso futuro em receitas e preservar a origem e a qualidade dos dados nutricionais informados.

## Decisões necessárias antes de especificar

- identidade do alimento e prevenção ou tratamento de duplicidades;
- origem dos dados nutricionais e evidência associada;
- responsáveis e fluxo de validação e aprovação;
- nutrientes, unidades, valores e representação de dados qualitativos ou ausentes;
- distinção entre alimento simples e ingrediente composto ou industrializado;
- disponibilidade na busca e condições para uso em receitas;
- rastreabilidade da fonte, versão e alterações;
- efeito de correções futuras sobre novos cálculos;
- preservação integral dos cálculos e snapshots históricos já produzidos.

## Restrições desta anotação

- Não cria Food sintético nem composição aproximada.
- Não define Models, migrations, endpoints, telas ou mecanismo administrativo.
- Não altera a TACO nem resultados históricos das Features 003 e 004.
- Não atribui número de feature que possa conflitar com o planejamento existente.
