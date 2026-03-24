# Plano de execução — Sangria / transferência para o Caixa da Operação

## 1. Objetivo

Permitir que **apenas gestor ou administrador** transfira valor do **próprio caixa** (na operação selecionada) para o **Caixa da Operação** (`consultor_id = NULL`), em um único fluxo que gere **dois lançamentos contábeis pareados** (saída no usuário + entrada no caixa da operação), com valor **à escolha** e **limitado ao saldo disponível** do executor naquela operação.

---

## 2. Escopo funcional

| Item | Definição |
|------|-----------|
| **Quem executa** | Usuário autenticado com papel **gestor** ou **administrador** na operação (validar `temAlgumPapelNaOperacao(..., ['gestor','administrador'])`). |
| **Origem** | Sempre o **próprio** `consultor_id` = `auth()->id()` (não transferir do caixa de terceiros neste fluxo). |
| **Destino** | Sempre **Caixa da Operação** na mesma `operacao_id` (`consultor_id` NULL). |
| **Valor** | `0 < valor <= saldo_atual` do executor na operação, com arredondamento monetário (2 casas) alinhado ao restante do caixa. |
| **Fora de escopo (v1)** | Transferência de terceiros; múltiplas operações num único clique; estorno automático (pode ser fase 2). |

---

## 3. Comportamento contábil (ledger)

**Decisão (padrão do sistema):** `origem = automatica` nos dois lançamentos (fluxo gerado pelo sistema após ação do usuário, como em fechamento/pagamentos), e **diferenciação por categoria** — criar/usar categorias **Sangria** (ou nomes alinhados) para **entrada** e **saída** por empresa/operação, via `CashCategoriaAutomaticaService` / `registrarMovimentacao`, **sem** novo valor em `origem`.

Em **uma transação de banco** (`DB::transaction`):

1. **Saída** — `CashLedgerEntry`: `tipo = saida`, `operacao_id`, `consultor_id = auth()->id()`, `origem = automatica`, `categoria_id` = categoria sangria/saída, `valor`, `data_movimentacao`, `descricao` padronizada (ex.: `Sangria para o Caixa da Operação`), `referencia_tipo` + `referencia_id` opcional se existir entidade de “transferência”.
2. **Entrada** — `CashLedgerEntry`: `tipo = entrada`, **mesma** `operacao_id`, **`consultor_id = NULL`**, `origem = automatica`, `categoria_id` = categoria sangria/entrada, mesmo `valor`, mesma data (ou mesma regra de data), descrição complementar ou espelhada, mesma referência se houver.

**Invariante:** soma dos saldos da operação (por definição atual do sistema) pode mudar só na forma de **composição** (mais no caixa “NULL”, menos no usuário); validar com `CashService::calcularSaldo` / regras de totais já usadas em movimentações.

---

## 4. Camadas técnicas

| Camada | Ação |
|--------|------|
| **Service** | Novo método, ex.: `CashService::transferirParaCaixaOperacao(int $usuarioId, int $operacaoId, float $valor, ?string $observacoes): array` — valida saldo, papel, operação; cria os dois lançamentos; audita (`Auditable`). |
| **Controller** | Novo endpoint POST (ex.: `POST /caixa/sangria` ou `POST /caixa/transferencia-operacao`) com `auth` + middleware implícito de gestor/admin na operação. |
| **Rotas** | Dentro do grupo `caixa` existente em `routes/web.php`. |
| **View** | **Somente** na tela **Movimentações de Caixa** (`caixa/index`): botão (gestor/admin) que leva ao formulário de sangria ou modal na própria página — select de operação (se >1), valor, saldo disponível, observações, confirmar. **Sem** item extra no menu lateral / sidebar. |
| **Validação** | Request class ou `$request->validate`: `operacao_id`, `valor` (min 0.01, regex/decimal), `observacoes` nullable. |

---

## 5. Regras de negócio e segurança

- Recusar se `round(saldo, 2) < valor`.
- Recusar se usuário não for gestor/admin **na operação informada**.
- Super Admin: manter política atual (ex.: não acessar caixa — igual `CashController`).
- **Idempotência:** opcional em v2 (ex.: `client_request_id`); v1 pode bast transação única.
- **Auditoria:** log de auditoria nos dois registros + evento de negócio “sangria” se já existir padrão no projeto.

---

## 6. UX (resumo)

**Onde:** exclusivamente na **tela de Movimentações de Caixa** (`/caixa`): botão ao lado de “Nova movimentação manual” (ou equivalente), visível só para gestor/admin — **não** duplicar no menu lateral.

| Tela | Conteúdo |
|------|----------|
| Entrada | Título: “Transferir para o Caixa da Operação” / “Sangria”. |
| Info | Saldo atual do usuário na operação selecionada. |
| Form | Operação, valor (máx. = saldo), observação opcional, **comprovante opcional** (PDF/imagem, mesmo arquivo nos dois lançamentos), confirmação. |
| Pós-sucesso | Redirect com flash + mensagem clara; opcional link para movimentações filtradas. |

---

## 7. Testes (mínimo)

- Gestor com saldo 1000 transfere 300 → saldo usuário 700; `calcularSaldoCaixaOperacao` aumenta 300.
- Valor > saldo → erro de validação.
- Consultor sem papel gestor → 403.
- Operação sem acesso → 403.
- Transação: se segunda inserção falhar, primeira não persiste (rollback).

---

## 8. Ordem de implementação sugerida

1. `CashService::transferirParaCaixaOperacao` + testes unitários/feature.
2. Controller + rota + policy/mesmas checagens do `CashController::store`.
3. View: botão na **tela de movimentações de caixa** apenas (`caixa/index`) + rota/formulário (só gestor/admin).
4. Ajuste de labels/documentação interna (`MODELO_CAIXA_OPERACAO.md` / CHANGELOG).
5. (Opcional) Relatório ou filtro por **categoria Sangria** nas movimentações.

---

## 9. Riscos e mitigação

| Risco | Mitigação |
|-------|-----------|
| Concorrência (dois cliques) | Transação DB + revalidar saldo dentro da transação. |
| Confusão com movimentação manual duplicada | Texto de ajuda + categorias **Sangria** distintas na listagem / filtros. |
| Expectativa de dinheiro físico | Texto de rodapé: operação registra posição contábil; conferência física é processo apartado. |

---

## 10. Dependências

- Modelo atual de `cash_ledger_entries` com `consultor_id` nullable.
- `CashService::registrarMovimentacao` e `calcularSaldo` já existentes.

---

*Documento de planejamento — implementado em 2026-01-24 (ver `CHANGELOG.md`).*
