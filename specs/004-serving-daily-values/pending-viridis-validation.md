# Pendências técnicas e normativas — Feature 004

**Data da revisão**: 2026-11-10

**Estado**: CQ-001, CQ-002 e CQ-003 resolvidas para especificação e planejamento seguro; validações técnicas indicadas abaixo permanecem bloqueios dos cálculos dependentes.

## Evidências disponíveis

- A Specification registra as decisões funcionais já aprovadas para porção, medida caseira, incompletude, persistência e ciclo de vida.
- `storage/app/exports/nutrients-004.csv` contém os 66 registros reais de `Nutrient`, com `id`, `name` e `unit`, ordenados por ID e sem identificadores inventados.
- `resources/data/taco/processed/nutrients.csv` preserva os nomes e unidades importados da TACO, mas não contém correspondências regulatórias, fatores energéticos ou VDR.
- A Viridis informou que usa Google Forms para coleta dos clientes e uma planilha Excel como referência operacional. Esses arquivos não foram localizados como artefatos versionados no repositório.
- `regulatory-decision-register.md` consolida a versão `004-CQ002-2026-10-30.1` das correspondências, fatores, VDR e regras de governança. `DEC-004-001` a `DEC-004-009`, `MAP-001` a `MAP-010`, `ENE-001` a `ENE-015` e `VDR-001` a `VDR-043` foram revisadas individualmente e preservam os aceites de desenvolvimento e restrições descritos em cada linha. `VDR-003` registra ausência normativa de VDR para açúcares totais; as demais VDR preservam somente denominadores normativos candidatos. A coincidência nominal ou dimensional de um nutriente TACO não autoriza seu uso regulatório, e a conversão candidata de cobre de `mg` para `µg` continua sem autorização. Cloreto, cromo, flúor, iodo, molibdênio, selênio e colina permanecem sem `nutrient_id` e sem fonte quantitativa autorizada. Nenhum aceite libera fórmulas, fontes complementares, conversões químicas, fortificação, processamento, alegações ou matriz energética completa. A `DEC-004-008`, alinhada à Constitution 2.0.0, autoriza Plan para os componentes seguros e para a implementação dos bloqueios; a `DEC-004-009` preserva a fronteira com a futura rotulagem.
- Não foi localizada no repositório evidência documental de validação nutricional ou aprovação formal da Viridis vinculada à versão proposta.

## Já esclarecido

- **CQ-001**: origem e validação manual da porção, compatibilidade entre unidade da porção e `yield_unit`, tamanho positivo, medida caseira ou dispensa e proibição de conversão ou densidade inferida.
- A fonte normativa deve ser a legislação oficial vigente e aplicável para a IN nº 75/2020, com retificações e alterações efetivamente publicadas, identificação dos atos, anexos e data da consulta.
- Energia regulatória exige matriz completa e aprovada; a energia da TACO permanece um dado distinto da fonte nutricional.
- `%VD` exige VDR aprovado e unidade compatível e é tratado individualmente por nutriente.
- **CQ-003**: propagação de valores completos e parciais, estados qualitativos e ausência de relação; aritmética exata; causas regulatórias distintas; execução derivada imutável e novos resultados a cada recálculo.
- O peso da unidade coletado no formulário não constitui automaticamente porção regulatória.

## Manifestação administrativa registrada

- Em 2026-11-10, o responsável pelo projeto declarou expressamente, nesta sessão de
  trabalho, ter recebido da equipe da Viridis autoridade delegada para decidir as
  pendências e aprovou o conteúdo da versão `004-CQ002-2026-10-30.1`. A manifestação,
  sua data, sua origem e a versão abrangida ficam registradas. Nenhum documento, e-mail,
  ata ou assinatura externa foi apresentado ou inventado. Conforme a Constitution 2.0.0,
  a manifestação do diretor satisfaz a autorização administrativa de avanço sem
  transformar o relato verbal em evidência documental de terceiro.
- Aprovação das correspondências propostas entre nutrientes da TACO e campos da tabela nutricional.
- Uso da planilha Excel como referência operacional para cálculos nutricionais.
- Uso do Google Forms para coleta de receita, pesos do lote e da unidade, medida caseira, ingredientes compostos ou industrializados e açúcares adicionados.

Esses relatos e a manifestação global não são descartados nem devem ser repetidos como perguntas já respondidas. A revisão individual, os aceites de desenvolvimento e a autorização administrativa estão concluídos. As validações técnicas e normativas que as próprias linhas mantêm pendentes continuam necessárias somente para liberar os cálculos correspondentes.

## Confirmações e artefatos ainda necessários

| Pendência | Informação exata a fornecer | Impacto na Feature 004 |
|---|---|---|
| Correspondências regulatórias | Todas as MAP receberam aceite de desenvolvimento; concluir suas validações nutricionais, documentais e normativas, resolver fontes indisponíveis e confirmar aplicabilidade, cobertura e restrições registradas | Aceite de desenvolvimento não torna mapeamentos bloqueados, incompatíveis ou ausentes utilizáveis regulatoriamente |
| Fatores energéticos | Concluir as validações externas e resolver os bloqueios de `ENE-001` a `ENE-015`; confirmar por produto aplicabilidade, fontes quantitativas autorizadas, separações e controles contra dupla contagem | A revisão individual e o aceite de desenvolvimento das 15 linhas não tornam a matriz aplicável completa ou aprovada; nenhum valor ou subtotal de energia derivada pode ser calculado até atender as condições aprovadas |
| Sobreposições | Aprovar regra nutricional para carboidratos TACO, que incluem fibra, além de polióis e fibras solúveis | Impede dupla contagem; nenhuma fórmula alternativa foi aprovada |
| Componentes não aplicáveis | Informar por produto os componentes comprovadamente não aplicáveis e a evidência correspondente | Ausência na TACO não prova inaplicabilidade; sem a regra, não é possível determinar completude |
| VDR e unidades | Concluir as validações externas de `VDR-001` a `VDR-043`, especialmente correspondências ausentes, aplicabilidade condicional e incompatibilidades de unidade, conceito ou metodologia | A revisão individual foi concluída, mas os aceites restritos não liberam aplicação; ausência normativa, VDR candidato ou coincidência nominal e dimensional não suprem dados ou validações metodológicas; a conversão candidata de cobre de `mg` para `µg` não está autorizada; sem validação e numerador regulatório completo, os `%VD` correspondentes permanecem indisponíveis |
| Nutrientes sem `%VD` | Confirmar o registro de açúcares totais sem VDR e qualquer outra exceção aplicável ao produto | Impede que ausência de VDR seja confundida com pendência ou dado faltante |
| Fronteira de arredondamento | A DEC-004-009 define que esta feature preserva resultados racionais exatos anteriores à declaração final; as regras detalhadas de arredondamento, quantidades não significativas e apresentação pertencem à futura feature de rotulagem | Resultados exatos desta feature não podem ser classificados silenciosamente como resultados regulatórios finais |
| Proveniência normativa | Confirmar a transcrição contra o texto oficial em português publicado no DOU e retificações vigentes | Necessário para conformidade e reprodução histórica |
| Validação técnica nutricional | Identificar responsável técnico, escopo validado, versão, data, evidência e ressalvas; resolver ou confirmar formalmente os bloqueios técnicos mantidos nas linhas `MAP`, `ENE` e `VDR` | A aprovação global do documento não torna utilizáveis correspondências, fatores, unidades, conversões ou fontes que o próprio documento qualifica como bloqueados ou pendentes |
| Conformidade normativa | Identificar responsável, fontes oficiais e versões conferidas, data, evidência e ressalvas da validação normativa | Sem essa evidência, denominadores candidatos e fatores transcritos não comprovam aplicação correta ao produto |
| Referências operacionais | Cópia ou exportação identificável da versão do formulário e da planilha usada como referência | Permite rastrear o fluxo informado, mas não substitui a aprovação da matriz regulatória |

O arquivo `viridis-approval-ratification-template.md` permanece disponível para registrar
eventual evidência externa técnica ou normativa. Seu preenchimento não é gate
administrativo para Plan, Tasks ou Implement, e campos em branco não podem ser
completados por inferência.

## Pacote técnico externo, se fornecido

Qualquer validação externa futura da versão `004-CQ002-2026-10-30.1` deve informar, para cada componente aplicável:

- denominação do componente regulatório;
- `nutrient_id`, nome e unidade reais conforme `nutrients-004.csv`;
- unidade regulatória aplicável;
- fator energético e respectiva unidade, quando aplicável;
- regra de sobreposição ou de prevenção de dupla contagem;
- condição comprovada de não aplicabilidade, quando houver;
- valor e unidade do VDR, quando houver;
- indicação explícita de que o componente possui ou não `%VD`;
- ato normativo, anexo, versão ou vigência e data de consulta;
- versão do documento aprovado, data e evidência da aprovação formal pela Viridis.

A forma tabular acima descreve as informações necessárias para validação e não antecipa schema, Model ou mecanismo de armazenamento.

## Impacto sobre o encerramento do Clarify

- CQ-001 e CQ-003 estão encerradas e não precisam ser reabertas.
- CQ-002 está encerrada para especificação e planejamento seguro: todos os estados tecnicamente utilizáveis, indisponíveis, não aplicáveis, incompletos ou bloqueados possuem comportamento verificável.
- Persistem bloqueios de uso, incompatibilidades, dados ausentes, aplicabilidade condicional e validações técnicas e normativas. Eles impedem somente os cálculos dependentes, não o Plan dos componentes independentes e dos bloqueios seguros.
- O Plan da Feature 004 está administrativamente autorizado pela Constitution 2.0.0 e deve preservar integralmente esses bloqueios.

## Alinhamentos documentais consolidados para implementação segura

As decisões aprovadas não devem ser perdidas. O alinhamento consolidado exige:

- manter CQ-002 alinhada aos aceites de desenvolvimento das decisões gerais, das dez MAP, das quinze ENE e de `VDR-001` a `VDR-043`, distinguindo-os das validações técnicas e dos bloqueios regulatórios ainda pendentes;
- substituir ou qualificar expressões que tratam energia racional exata e `%VD` racional exato como resultados regulatórios finais, preservando-os como derivados anteriores à declaração e ao recálculo final da futura rotulagem conforme a `DEC-004-009`;
- explicitar no snapshot de execução derivada os dados aprovados na `DEC-004-005` para justificativas de não aplicabilidade: produto, versão da formulação, componente, justificativa, fonte, validação, data e versão disponíveis.
