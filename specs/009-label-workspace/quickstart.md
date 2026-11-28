# Quickstart: Área de Rótulos

## Pré-requisitos

- Ambiente local do NutriLabel com PostgreSQL isolado de desenvolvimento/teste e migrations das Features anteriores aplicadas.
- Não usar nem expor dados de produção neste roteiro.
- A aplicação não possui autenticação nesta Feature e deve permanecer acessível apenas no ambiente/rede interna.

## Verificação funcional

1. Execute os testes de tela:

   ```powershell
   php artisan test --compact tests/Feature/Frontend/LabelIndexScreenTest.php
   ```

2. Execute os testes das telas relacionadas para regressão:

   ```powershell
   php artisan test --compact tests/Feature/Frontend
   ```

3. Abra `/rotulos`.
   - Sem execuções da Feature 005, deve aparecer estado vazio e nenhum valor nutricional fictício.
   - Com execuções persistidas, cada item deve mostrar IDs próprios/de origem, data, prontidão e todas as causas existentes.
   - A ordenação deve ser recente primeiro; navegue páginas quando houver mais de 25 registros.
   - “Rótulos” deve navegar para essa tela; “Configurações” não deve aparecer.
   - O link de montagem deve abrir somente a execução selecionada e não pode alterar seu snapshot.

4. Verifique que nenhuma consulta à listagem cria ou modifica execuções. Causas de bloqueio continuam visíveis; nenhum valor ausente é convertido em zero e nenhum status parcial é apresentado como resultado completo.

5. Rode a suíte completa e as verificações de estilo/patch antes de encerrar a implementação:

   ```powershell
   php artisan test
   vendor\bin\pint --test
   git diff --check
   ```

## Limites regulatórios e operacionais

Este roteiro valida somente a interface de consulta. Não valida nem aprova MAP, ENE, VDR, conversões ou fontes. Referências reais bloqueadas e o caso da Geleia de Amora continuam sob seus bloqueios. Homologação técnica de interface não equivale a homologação regulatória. Sem autenticação, não disponibilize o serviço fora da rede interna.
