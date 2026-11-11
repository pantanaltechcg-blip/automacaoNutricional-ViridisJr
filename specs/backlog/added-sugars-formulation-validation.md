# Backlog — Obtenção e validação de açúcares adicionados por formulação

**Origem**: dependência funcional identificada na revisão de `MAP-004` da Feature 004.

**Estado**: aguardando Specification própria ou alteração formal de escopo; nenhum número de feature foi atribuído.

## Necessidade registrada

Permitir futuramente obter e validar o valor de açúcares adicionados a partir de fontes e informações de formulação autorizadas, pois a Base Nutricional TACO atual não possui `nutrient_id` correspondente.

## Escopo a esclarecer antes de especificar

- identificação dos açúcares adicionados conforme a definição regulatória aplicável;
- tratamento de ingredientes simples, compostos e industrializados;
- uso e validação de informações fornecidas por fabricantes;
- condições, inclusões e exclusões previstas na norma;
- relação entre quantidades da formulação, rendimento e valor por porção;
- unidades, fontes, versões e evidências de aprovação;
- validação técnica nutricional e aprovação formal exigida;
- comportamento diante de dados ausentes, incompletos ou conflitantes;
- versionamento das formulações e preservação de execuções históricas.

## Relação com outros backlogs

Esta necessidade é distinta de `manual-food-registration.md`: cadastro manual cria e governa alimentos e sua composição nutricional, enquanto este item trata um dado regulatório derivado da formulação do produto. As funcionalidades podem futuramente compartilhar estruturas de proveniência e validação, mas nenhuma dependência técnica ou unificação de escopo é aprovada por este registro.

## Restrições desta anotação

- Não implementa cálculo, captura ou validação dentro da Feature 004.
- Não transforma o formulário da Viridis em fonte nutricional automaticamente aprovada.
- Não define fórmula, Model, migration, endpoint, tela ou fluxo administrativo.
- Não cria `nutrient_id` nem altera os dados TACO.
- Não aprova a linha VDR correspondente.
- Não atribui número de feature e não modifica execuções históricas.
