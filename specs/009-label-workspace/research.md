# Research: Área de Rótulos

## Decisões técnicas

### Consulta das execuções declaradas existentes

- **Decisão**: `LabelExecutionListingService` consulta `App\Models\DeclaredNutritionCalculation`, carrega antecipadamente a relação `causes` e pagina a listagem; o Controller apenas orquestra a chamada e a resposta de tela.
- **Evidência no projeto**: o Model já expõe `causes()` e `servingDailyValueCalculation()`; `DeclaredNutritionCause` relaciona cada causa à execução. A rota de montagem existente usa route model binding para `DeclaredNutritionCalculation`.
- **Alternativas descartadas**: consultar o Model diretamente no Controller violaria o fluxo arquitetural obrigatório da Constitution; criar uma nova entidade ou persistir outra cópia duplicaria o registro histórico da Feature 005; recalcular pela Recipe atual quebraria a imutabilidade e a rastreabilidade.

### Identidade histórica sem depender da Recipe atual

- **Decisão**: identificar uma execução por seu próprio ID, IDs de origem persistidos e data de criação; não consultar o nome atual da Recipe para compor a listagem.
- **Motivo**: a Recipe pode mudar depois da execução, enquanto os dados de origem copiados na execução declarada descrevem o cálculo histórico.
- **Limite**: a tela informa identificadores, não reconstrói nem completa snapshots.

### Estado e causas

- **Decisão**: mostrar `is_label_ready` e todas as causas persistidas da execução, sem tradução que altere semântica, sem omitir causas e sem promover indisponibilidade a sucesso.
- **Motivo**: a Specification requer consulta honesta de registros incompletos/bloqueados e proíbe criação de dados fictícios.
- **Limite**: a existência de um registro não é homologação nutricional ou regulatória; a prontidão é exibida conforme o que a Feature 005 persistiu.

### Paginação e ordenação

- **Decisão**: usar paginação Eloquent/Laravel de 25 execuções por página, ordenadas por `created_at DESC` e `id DESC` como desempate.
- **Motivo**: limita a quantidade carregada em uma consulta e garante ordem determinística para datas iguais. É decisão de apresentação, sem alterar regras de negócio.

### Acesso interno

- **Decisão**: não introduzir autenticação nesta Feature, conforme Clarify. Exposição externa não está autorizada; eventual hospedagem deverá restringir acesso operacionalmente ou exigir decisão posterior com controles apropriados.
- **Limite**: a rota não deve ser descrita como protegida por autenticação. Esta Feature não configura rede, proxy, firewall ou implantação.

## Componentes regulatórios permanecem fora da decisão

Esta tela não altera nem habilita MAP, ENE, VDR, fontes quantitativas, conversões, aplicabilidade ou a condição da Geleia de Amora. Ausência de execução declarada válida permanece um estado legítimo e deve ser mostrada sem substituição por valores estimados ou fixtures reais.
