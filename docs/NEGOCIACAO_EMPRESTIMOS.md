# Negociação de Empréstimos

## Visão geral

A **negociação** permite criar um **novo empréstimo** com o **saldo devedor** do atual e novas condições (tipo, frequência, taxa, número de parcelas, data de início). O empréstimo antigo é **finalizado**; nenhum pagamento em dinheiro é registrado nas parcelas antigas — a dívida é substituída pelo novo contrato.

Exemplo: empréstimo diário 12x de R$ 330 é negociado para 1 parcela mensal de R$ 330; o antigo fica finalizado e o novo nasce ativo com as novas condições.

---

## O que acontece com o empréstimo antigo

1. O empréstimo de origem passa a **status `finalizado`** e é vinculado ao novo empréstimo (`emprestimo_origem_id` no novo).
2. **Parcelas ainda pendentes/atrasadas** desse empréstimo são **encerradas** para não aparecerem como atrasadas:
   - **Status** da parcela: `paga`
   - **valor_pago**: `0` (nenhum valor foi efetivamente recebido naquela parcela)
   - **data_pagamento**: data da negociação (encerramento)

Assim, na tela do empréstimo finalizado as parcelas não aparecem como "atrasadas"; aparecem como encerradas (paga com valor 0). Nenhum registro é criado na tabela de **pagamentos** nem há movimentação de caixa.

---

## Diferença em relação à renovação

- **Renovação (só juros ou com abate):** registra **pagamento** nas parcelas do empréstimo antigo (juros e/ou principal). As parcelas ficam com `valor_pago` preenchido e status `paga` por pagamento real.
- **Negociação:** não há pagamento nas parcelas antigas; o contrato é substituído. Por isso as parcelas são apenas **encerradas** (status `paga`, `valor_pago = 0`) para efeito de exibição e relatórios.

---

## Exibição de "parcelas pagas" na tela do empréstimo

Na tela de detalhes do empréstimo, o texto **"(X/Y parcelas)"** ao lado de "Valor Já Pago" considera **apenas parcelas efetivamente pagas com dinheiro**:

- Contagem: parcelas com **status `paga`** **e** **`valor_pago > 0`**.

Parcelas encerradas por negociação (status `paga` e `valor_pago = 0`) **não** entram nessa contagem, para que relatórios e indicadores de "parcelas pagas" reflitam só o que foi recebido.

---

## Relatórios

- **Valor recebido / total pago:** vêm da tabela de **pagamentos** ou da soma de `valor_pago`; parcelas com `valor_pago = 0` não aumentam totais.
- **Parcelas atrasadas:** filtram por status diferente de `paga`; parcelas encerradas por negociação não aparecem.
- **Quitações / juros:** usam pagamentos registrados; negociação não gera pagamento, então não distorce esses relatórios.

---

## Arquivos principais

- `app/Modules/Loans/Services/EmprestimoService.php` — método `negociar()`: cria o novo empréstimo, encerra parcelas do antigo (paga, valor 0, data_pagamento) e finaliza o antigo.
- `resources/views/emprestimos/show.blade.php` — contagem de "parcelas pagas" com `status === 'paga'` e `valor_pago > 0`.

## Nota de implementação

O update das parcelas do empréstimo antigo é feito com `Parcela::withoutGlobalScope(EmpresaScope::class)` e `where('emprestimo_id', ...)`, para garantir que todas as parcelas pendentes/atrasadas sejam encerradas mesmo quando tiverem `empresa_id` nulo ou diferente (ex.: parcelas criadas antes da migração que adicionou o campo).
