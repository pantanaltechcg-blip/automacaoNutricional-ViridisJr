# Quickstart Validation: 004-serving-daily-values

## Objetivo

Validar porção exata, gates MAP/ENE/VDR, incompletude, rastreabilidade e histórico
imutável sem arredondamento ou apresentação de rótulo.

## Pré-requisitos

- PHP 8.4 e dependências Composer instaladas;
- PostgreSQL configurado sem credenciais versionadas;
- Features 001–003 migradas e testes passando;
- uma `NutritionCalculation` imutável existente;
- fixtures controladas de autorização de porção e perfil regulatório para testes.

```bash
composer install
php artisan migrate
php artisan migrate:status
composer show --direct brick/math
```

Confirme `brick/math` 1.0.0 como dependência direta. Não carregue como autorizados
valores MAP/ENE/VDR que permaneçam bloqueados no registro da Feature 004.

### Preparação reproduzível das fixtures

Os caminhos autorizados são preparados dentro dos testes em
`tests/Feature/ServingDailyValueCalculationTest.php` e
`tests/Feature/ServingDailyValueRegulatoryGateTest.php`, usando o PostgreSQL de testes e
transações/limpeza da suíte. Cada fixture autorizada deve conter:

- `technical_status = AUTHORIZED`;
- tupla de evidência completa e coerente;
- `evidence_kind = TEST_FIXTURE`;
- escopo vinculado aos IDs de `NutritionCalculation`, perfil e componente criados pelo
  próprio teste.

Execute os testes para preparar e consumir essas fixtures no mesmo cenário:

```bash
php artisan test --compact tests/Feature/ServingDailyValueCalculationTest.php
php artisan test --compact tests/Feature/ServingDailyValueRegulatoryGateTest.php
```

Não criar Seeder, migration de dados, comando de carga ou fixture persistente para
esses registros. `TEST_FIXTURE` deve ser rejeitada quando o ambiente não for `testing`.
Os placeholders dos exemplos `curl` somente podem ser substituídos por IDs de um banco
de testes controlado; nunca por promoção da matriz real bloqueada.

## 1. Validar o estado deny-by-default

Com uma origem 003 e autorização de porção válida, selecione o perfil correspondente
à versão documental atual, preservando seus bloqueios técnicos.

Crie a execução:

```bash
curl -X POST "http://localhost:8000/api/nutrition-calculations/<origin-id>/serving-daily-value-calculations" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "portion": {
      "value": "20",
      "unit": "g",
      "household_measure": "1 colher de sopa"
    },
    "portion_authorization_id": <portion-authorization-id>,
    "regulatory_profile_id": <blocked-profile-id>
  }'
```

Esperado:

- todos os nutrientes da origem aparecem com valor por porção racional ou `null`;
- MAP bloqueada produz valor regulatório `null` com causa;
- matriz ENE não autorizada produz energia `null`, sem subtotal;
- VDR sem numerador/MAP autorizados produz `%VD = null`;
- nenhum bloqueio vira zero ou impede nutrientes independentes.

## 2. Conferir cálculo por porção

Para cada nutriente com razão na origem:

```text
portion = source_total × portion_value ÷ source_yield_value
```

Valide:

- `yield_unit = g` aceita somente porção `g`;
- `yield_unit = ml` aceita somente porção `ml`;
- frações e dízimas permanecem razões exatas;
- zero `EXACT` permanece `0/1`;
- subtotal mantém `complete = false`, estados e ausência de relação;
- origem sem número produz `null` sem omitir o nutriente;
- não há densidade, conversão g↔ml ou arredondamento.

## 3. Validar porção e medida caseira

Use testes com autorizações controladas para cobrir:

- porção positiva fracionária exatamente igual à autorização;
- zero, negativo, unidade incompatível e autorização de outra formulação;
- medida `PROVIDED` obrigatória e não vazia;
- `WAIVED` e `NOT_APPLICABLE` com justificativa preservada e medida ausente;
- referência bloqueada ou inexistente;
- status autorizado sem evidência, evidência sem status, escopo incompatível e fixture
  usada fora do ambiente `testing`;
- espaços externos removidos pelo middleware e string vazia/espaços convertida a
  `null` antes da validação.

Falhas não persistem raiz nem filhos.

## 4. Validar caminho ENE autorizado com fixture controlada

Uma fixture de teste pode representar perfil completo e autorizado sem promover o
registro real. Cubra:

1. todos os componentes aplicáveis completos → energia racional completa;
2. matriz autorizada com dado nutricional incompleto → subtotal parcial e causas;
3. nenhum componente numérico → energia `null` incompleta;
4. componente `NOT_APPLICABLE` → evidência completa no snapshot, sem zero;
5. `UNKNOWN`, fonte ausente ou linha bloqueada → matriz bloqueada, sem subtotal;
6. possível dupla contagem sem regra autorizada → energia bloqueada;
7. energia TACO permanece apenas dado da origem e não substitui o derivado.
8. perfil autorizado não libera MAP/ENE dependente sem autorização e evidência próprias;
9. `APPLICABLE` sem evidência e `NOT_APPLICABLE` sem justificativa/evidência bloqueiam;
10. conversão sem autorização e evidência próprias permanece bloqueada.

## 5. Validar `%VD` com fixture controlada

Para cada linha:

```text
daily_value_percent = authorized_regulatory_portion_value × 100 ÷ authorized_vdr
```

Confirme:

- completo → percentual racional completo;
- subtotal → percentual racional parcial com as mesmas causas;
- numerador `null` → percentual `null`;
- zero completo → `0/1` completo;
- MAP/VDR bloqueado, unidade incompatível ou conversão não autorizada → `null`;
- `NO_NORMATIVE_VDR` é distinto de VDR pendente;
- nenhuma linha é apresentada como percentual final do rótulo.

## 6. Validar histórico

Consulte a execução:

```bash
curl "http://localhost:8000/api/serving-daily-value-calculations/<derived-id>" \
  -H "Accept: application/json"
```

Depois, em teste transacional:

1. crie novas versões das referências técnicas, sem alterar as anteriores;
2. consulte novamente o ID histórico;
3. confirme resposta idêntica e baseada somente no snapshot, incluindo justificativas,
   proveniência e evidências originais;
4. solicite novo cálculo e confirme novo ID;
5. tente `UPDATE`/`DELETE` direto nas referências e no histórico e confirme bloqueio
   PostgreSQL;
6. confirme que `NutritionCalculation` e suas fontes não foram alteradas.

## 7. Caso “GELEIA DE AMORA”

O caso real continua condicionado ao mapeamento autorizado de amora e suco de limão.
Não criar Food sintético, escolher por aproximação ou forçar `13,04 g`/`54,82 kcal`.
Até a autorização, o caso não pode ser marcado como homologado; isso não impede testes
genéricos com fixtures controladas.

## 8. Validação automatizada

```bash
php artisan test --compact
vendor/bin/pint --test
git diff --check
```

A suíte PostgreSQL deve provar:

- fórmulas racionais exatas e ausência de casts inexatos;
- cobertura integral dos nutrientes da origem;
- matriz de zero/null/estados/ausência/completude;
- todos os gates e causas regulatórias;
- matriz ENE completa antes de qualquer energia;
- independência dos resultados não bloqueados;
- constraints, rollback e imutabilidade;
- GET histórico independente de referências atuais;
- nenhuma implementação de arredondamento, valor declarado final ou rótulo.

## Critério de conclusão

A validação termina quando os caminhos seguros e bloqueados passam integralmente em
PostgreSQL, o histórico é imutável e nenhum dado pendente é promovido. A ausência de
matriz real autorizada pode manter energia/%VD indisponíveis em produção sem impedir a
conclusão da infraestrutura e dos valores por porção aprovados.
