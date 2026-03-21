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

### Onda 2 — **Feito** (listagens, dashboards, relatórios, vendas)

| Área | Arquivos |
|------|----------|
| Liberações / aprovações / retroativo | `LiberacaoController` (várias listagens + mapa), `AprovacaoController`, `EmprestimoController@indexPendentesRetroativo`, views em `liberacoes/*`, `aprovacoes/index`, `retroativo-pendentes` |
| Garantias / cheques (lista) | `GarantiaController@index` + `@show`, `ChequeController@index` + `@hoje`, `garantias/*`, `cheques/index` |
| Dashboard | `DashboardController` (admin, gestor, consultor): mapa unificado `fichasContatoPorClienteOperacao`, blades `dashboard/*` |
| Vendas | `VendaController@index` + `@show`, `vendas/index`, `vendas/show` (`pairsFromVendas` em `FichaContatoLookup`) |
| Relatórios quitação | `RelatorioController` (`quitacoes`, `jurosQuitacoes`), `relatorios/quitacoes`, `relatorios/juros-quitacoes` |

### Onda 3 — **Feito** (caixa, prestações legado, negociação em create)

| Área | Arquivos |
|------|----------|
| Caixa / fechamento | `FechamentoCaixaController` (`conferir`, `show`), `caixa/fechamento/conferir`, `caixa/fechamento/show` — mapa via `FichaContatoLookup::mapFromCashLedgerEntries` + `fromParcelaMap` nas movimentações; `CashController@show` + `caixa/movimentacao/show` — `ClienteNomeExibicao::forEmprestimo` quando há vínculo pagamento → parcela |
| Prestações (views legado) | `SettlementController` (`show`, `preview`), `prestacoes/show`, `prestacoes/preview` — mesmo padrão |
| Empréstimo create (negociação) | `EmprestimoController@create` — `nomeClienteExibicaoOrigem`; `emprestimos/create` (alerta do empréstimo origem) |
| Suporte | `FichaContatoLookup::mapFromCashLedgerEntries` |

### Pontual (pós-onda 3) — **Feito**

| Área | Arquivos |
|------|----------|
| Quitação pendente | `QuitacaoController@indexPendentes`, `quitacao/pendentes` — mapa + `fromEmprestimoMap` |
| Busca global | `SearchController@buscar` — subtítulo de empréstimo com `fromEmprestimoMap` |
| Kanban | `KanbanService` — campo `cliente` dos cards (empréstimo / liberação / parcela) com mapa em lote |

**Telas globais (fora do critério D1):** o **mural de devedores** (`consultas/devedores`) segue a **mesma lógica do radar** (`radar/*`) — visão **global** na empresa, sem `operacao_id` único na listagem. Aí o nome exibido continua sendo o do **cadastro** (`Cliente->nome`); **não** é pendência de D1 nem inconsistência com o radar.

**API Select2 de clientes (`ClienteController@buscar`):** com query `operacao_id` (formulários **novo empréstimo** e **nova venda** enviam após escolher a operação), o texto da lista usa **ficha da operação** + busca estendida em nome/telefone/e-mail da ficha.

**Ainda fora do escopo D1 curto (outros):** telas de cadastro de cliente (contexto misto cadastro/ficha já tratado onde há operação), `caixa/index` sem coluna cliente, commands/logs internos.

### Critério de pronto por tela

- Nome visível = ficha da operação do contexto quando `nome` da ficha preenchido; caso contrário comportamento anterior (`$cliente->nome`).
- Sem regressão: cliente sem linha em `operacao_dados_clientes` continua igual ao fallback.

---

*Complementa `docs/PLANO_VARREDURA_FASE4_DADOS.md` (Fase D1).*
