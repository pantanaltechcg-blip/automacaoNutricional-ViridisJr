# Quickstart: Governed Manual Food Registration (`006-manual-food-registration`)

Este guia descreve os passos para validar ponta a ponta a Feature 006 após a conclusão das etapas de Tasks e Implementação.

## Pré-requisitos

1. Ambiente PHP 8.4+, Composer e PostgreSQL 17 em execução com extensão `unaccent`.
2. Migrations aplicadas (`php artisan migrate`) e base TACO importada (`php artisan nutrition:import-taco`).
3. Referências dos contratos e modelo de dados em:
   - [contracts/manual-food-registration.md](./contracts/manual-food-registration.md)
   - [data-model.md](./data-model.md)

---

## Cenários de validação ponta a ponta

### 1. Cadastro e busca imediata
1. Enviar `POST /api/manual-foods` com dados válidos de um alimento simples ausente da TACO (`source` diferente de `TACO`, `reference_unit = "g"`, metadados de evidência e pelo menos 1 nutriente informado com `EXACT` ou estado qualitativo).
2. Verificar retorno `201 Created` com `current_version_number = 1` e `is_active = true`.
3. Consultar `GET /api/foods?q=<trecho-sem-acento>` e confirmar que o novo alimento aparece na busca com sua origem distinta de `TACO` e os mesmos nutrientes informados.

### 2. Bloqueios de integridade (negativos)
1. Tentar cadastrar via `POST /api/manual-foods` com `source = "TACO"` ou `" taco "` → deve falhar com erro de validação `422`.
2. Tentar cadastrar novamente com mesmo `(source, source_code)` ou mesmo `(source, name)` (ignorando acentos/caixa) → deve falhar com erro de duplicidade `422`.
3. Tentar consultar, editar ou alterar status de um alimento TACO existente via `/api/manual-foods/{tacoFoodId}` → deve retornar `404` e manter a tabela `foods` e `food_nutrients` da TACO intactas.
4. Tentar enviar `amount_value` como número JSON (`float`), valor negativo ou com mais de 2 casas decimais, ou com estado `TRACE`/`NA`/`ABSENT`/`REEVALUATION` e `amount_value != null` → deve falhar com `422`.

### 3. Uso em receita, cálculo histórico e versionamento imutável
1. Criar uma receita (`POST /api/recipes`) utilizando o alimento manual recém-cadastrado.
2. Executar cálculo nutricional (`POST /api/recipes/{recipe}/nutrition-calculations`) e registrar o `calculationId` e os valores obtidos (`GET /api/nutrition-calculations/{calculationId}`).
3. Atualizar o alimento manual via `PUT /api/manual-foods/{food}` com `change_reason` preenchido e novo valor em um nutriente.
4. Verificar via `GET /api/manual-foods/{food}` que `current_version_number = 2` e `versions` preserva integralmente a versão `1` e a versão `2`.
5. Verificar novamente `GET /api/nutrition-calculations/{calculationId}`: os valores históricos devem permanecer **idênticos** à versão `1`.
6. Executar novo `POST /api/recipes/{recipe}/nutrition-calculations`: o novo cálculo deve refletir a versão `2` vigente.

### 4. Inativação lógica e proteção de histórico
1. Enviar `PATCH /api/manual-foods/{food}/status` com `{"is_active": false}`.
2. Confirmar que o alimento desaparece de `GET /api/foods?q=...` e é rejeitado em novas receitas (`POST /api/recipes`).
3. Confirmar que `GET /api/manual-foods/{food}`, a receita anterior e os cálculos históricos já criados continuam íntegros.

---

## Comandos de verificação automatizada

```powershell
php artisan test --compact
vendor/bin/pint --format agent
git diff --check
```
