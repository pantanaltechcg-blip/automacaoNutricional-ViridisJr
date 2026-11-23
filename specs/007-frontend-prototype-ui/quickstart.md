# Quickstart — Validação da Feature 007

Guia de validação (não contém código de implementação). Referências: [spec.md](./spec.md), [contracts/ui-contracts.md](./contracts/ui-contracts.md), [data-model.md](./data-model.md).

## Pré-requisitos
- Ambiente Laravel Sail/PostgreSQL ativo, migrations e importação TACO aplicadas.
- `npm install` já feito (sem dependências novas); `npm run dev` ou `npm run build`.

## 1. Testes automatizados
```bash
php artisan test --compact tests/Feature/Frontend
node --test tests/Js
php artisan test --compact          # não regressão das Features 001–006
vendor/bin/pint --dirty --format agent
```
Esperado: tudo verde; nenhum arquivo de backend existente alterado (`git diff --stat` limitado a `routes/web.php`, novos controllers/service, views, JS, CSS, testes e `specs/007-*`).

## 2. Validação manual (backend atual)
1. Abrir `/receitas/nova`: layout igual a `docs/referencias/pagina-01.png`.
2. Alternar "Líquido": rótulos viram "Volume final (ml)"/"Porção (ml)"; ingredientes seguem em "g".
3. Buscar "arroz": resultados com `source`; adicionar 2 ingredientes; testar −/+ (passo 1 g; "−" não leva a ≤ 0); digitar "1,5" e conferir envio `"1.5"`.
4. Conferir "Total informativo dos ingredientes" e que `yield_value` não muda.
5. "Gerar tabela nutricional": receita salva, Feature 003 executada, mensagem "Etapa bloqueada: Feature 004 …"; abrir Tela 2 com tabela em "—", "Porções por embalagem: —".
6. Provocar 422 (nome vazio ou quantidade inválida): erro no campo da API. Desligar a rede: mensagem e "Tentar novamente".
7. Abrir `/ingredientes/novo`: campos obrigatórios da Feature 006 sem pré-preenchimento; "100 ml" e os 3 nutrientes sem suporte desabilitados com explicação; `source = TACO` → erro 422; salvar válido → retorno à Tela 1 com rascunho preservado; novo ingrediente aparece na busca.
8. `/rotulos/999999/montagem` → 404; "Baixar PDF" desabilitado; "Rótulos"/"Configurações" indisponíveis.
9. Comparar cada tela lado a lado com `pagina-01..04.png`.

## 3. Critérios de aceitação (resumo)
SC-001 a SC-007 da spec. Sem valores fictícios, sem IDs regulatórios criados, sem zero fictício, backend e Feature 005 intactos.
