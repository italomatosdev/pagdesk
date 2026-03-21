# Plano D1 — Nome do cliente com operação conhecida (exibição explícita)

**Decisão de produto:** em qualquer tela em que exista **`operacao_id` explícito** (via empréstimo, parcela, filtro, etc.), o **nome exibido** deve priorizar **`operacao_dados_clientes.nome`**; fallback **`Cliente->nome`**.

**Implementação técnica:** `App\Support\ClienteNomeExibicao` (uso em controllers/views; evita N+1 com `FichaContatoLookup` onde já existe mapa).

**Notificações:** continuam usando `NotificacaoClienteDisplayName`, que delega para a mesma lógica.

---

## Ondas de execução

### Onda 1 — **Feito** (primeira entrega)

| Área | Arquivos |
|------|----------|
| Helper + notificações | `ClienteNomeExibicao.php`, `NotificacaoClienteDisplayName.php` (delegação) |
| Empréstimo | `EmprestimoController` (show, index, export CSV), `emprestimos/show`, `emprestimos/index` |
| Liberação (detalhe) | `LiberacaoController@show`, `liberacoes/show` (`nomeClienteExibicao`, JS com `@json`) |
| Pagamentos / quitação | `PagamentoController` (create, multi-parcelas, quitar diárias), `QuitacaoController@quitar`, views correspondentes |
| Cheque | `ChequeController@showPagar`, `cheques/pagar` |
| Cobranças / parcelas atrasadas | `cobrancas/index`, `parcelas/atrasadas` (`fromParcelaMap` + mapa existente) |
| Relatório | `relatorios/parcelas-atrasadas` — aba **tabela** com `fromParcelaMap` + `fichasPorClienteOperacao`; aba rota já usava ficha no nome |

### Onda 2 — Pendente (próximas PRs)

- `liberacoes/index`, `liberacoes/consultor`, listagens de solicitações (`liberacoes/*`)
- `aprovacoes/index`, `emprestimos/retroativo-pendentes`, `garantias/*`, `cheques/index`
- `dashboard/*` (admin, gestor, consultor), `vendas/*`, `caixa/*`, `prestacoes/*`
- Relatórios `juros-quitacoes`, `quitacoes` (carregar mapa ou query por linha)
- `emprestimos/create` (resumo com empréstimo origem)

### Critério de pronto por tela

- Nome visível = ficha da operação do contexto quando `nome` da ficha preenchido; caso contrário comportamento anterior (`$cliente->nome`).
- Sem regressão: cliente sem linha em `operacao_dados_clientes` continua igual ao fallback.

---

*Complementa `docs/PLANO_VARREDURA_FASE4_DADOS.md` (Fase D1).*
