# Plano: Repartição Juros / Investido — pagamentos só juros em renovações

**Status:** **implementado** — heurística em `RelatorioController::repartirInvestidoJurosParaRelatorio` + testes em `tests/Unit/RelatorioControllerRepartirJurosTest.php`.

**Contexto:** No relatório *Recebimento e juros por dia*, a função `RelatorioController::repartirInvestidoJurosParaRelatorio` reparte cada `pagamento` em **Juros** e **Investido** usando a proporção `parcela.valor_juros / parcela.valor` sobre o valor pago (após `valor_juros` de atraso). Em empréstimos **1x mensal** com parcela grande (ex.: R$ 379.250 = R$ 370.000 principal + R$ 9.250 juros), um pagamento de **apenas R$ 9.250** (só juros antes da renovação) aparece com **quase tudo em Investido**, o que **não** reflete o negócio (zero amortização nesse ato; principal rola no novo empréstimo).

**Objetivo:** Sem migration nem alteração obrigatória de registros históricos em `pagamentos`, ajustar **só a lógica de repartição na leitura** para identificar esses casos e atribuir o valor (após juros de atraso) majoritariamente ou integralmente a **Juros**.

---

## Abordagem preferida (mínima mudança de dados)

1. **Alterar apenas** `repartirInvestidoJurosParaRelatorio` em `app/Modules/Core/Controllers/RelatorioController.php` (e manter o mesmo contrato de retorno `juros` / `investido` / subtotais se usados em comissões).
2. **Antes** da regra atual (proporção pela parcela), avaliar uma condição **heurística** usando dados já existentes:
   - `Pagamento` com `parcela` e `parcela.emprestimo` carregados quando o relatório buscar pagamentos (garantir `with()` se necessário).
   - `valorSemAtraso = max(0, pagamento.valor - pagamento.valor_juros)` (juros de atraso explícitos).
   - `parcela.valor_juros > 0`, `parcela.valor > 0`.
   - **Cadeia de renovação:** `Emprestimo::participaCadeiaRenovacaoRelatorio()` — é renovação (`emprestimo_origem_id`) **ou** contrato que já gerou ao menos uma renovação (`renovacoes`), pois o pagamento só-juros costuma estar no **contrato antigo** (sem `origem_id`).
   - **Pagamento compatível com “só juros do contrato”:** por exemplo  
     `valorSemAtraso <= parcela.valor_juros + ε` (ε = tolerância centavos, ex. 0,02)  
     ou, mais restrito, `abs(valorSemAtraso - parcela.valor_juros) <= ε` se quiser só o caso “pagamento = juros da linha”.
3. **Se a condição for verdadeira:**  
   - `juros_contrato` (para efeito do total `juros`) = `valorSemAtraso` (ou o mínimo entre `valorSemAtraso` e `parcela.valor_juros` se preferir teto explícito).  
   - **Não** aplicar a proporção `valor_juros/valor` sobre esse pedaço.  
   - Manter juros de atraso (`pagamento.valor_juros`) e **juros incorporados** proporcional como hoje, se ainda fizer sentido neste branch (definir na implementação: incorporados sobre `valorSemAtraso` inteiro ou só sobre o que sobrar).
   - `investido = round(valor - juros_total, 2)` para fechar `recebido = investido + juros`.

4. **Se falso:** manter o comportamento **atual** (proporção + fallback principal/parcela).

---

## Casos a validar antes de merge

| Caso | Expectativa |
|------|-------------|
| Golf: 1 parcela, paga exatamente `valor_juros`, empréstimo renovação | Quase 100% Juros no relatório |
| Price diário: parcela com juros+amortização, pagamento = valor da parcela | **Não** deve cair na heurística se `valorSemAtraso > parcela.valor_juros` ou se não for renovação |
| Pagamento com juros de atraso | Atraso continua em `valor_juros` do pagamento; base repartida é `valorSemAtraso` |
| Renovação + pagamento parcial menor que `valor_juros` | Ainda pode ser “só juros” → `<= valor_juros` cobre |
| Empréstimo sem cadeia de renovação, mesmo valor = `valor_juros` | **Não** entra na heurística (mantém proporção) |

---

## Arquivos envolvidos

- `app/Modules/Core/Controllers/RelatorioController.php` — `repartirInvestidoJurosParaRelatorio` (+ eventual `with` na query de `recebimentoJurosDia`).
- Testes (opcional mas recomendado): teste unitário isolado da função (refatorar para método testável ou service estático) com `Pagamento` / `Parcela` / `Emprestimo` mockados.

---

## Fora de escopo (neste plano)

- Alterar `pagamentos` ou `parcelas` em produção retroativamente.
- Nova coluna em banco (só se a heurística provar insuficiente depois).

---

## Rollout

1. Implementar em branch.  
2. Conferir relatório *Recebimento e juros por dia* e, se aplicável, detalhe de **comissões** que reutiliza a mesma repartição.  
3. Comparar um dia conhecido (ex.: 30/03/2026 Golf + NC) antes/depois.  
4. Deploy; sem passo de migration.

---

## Referência rápida (comportamento atual)

Ver comentário e corpo em `RelatorioController::repartirInvestidoJurosParaRelatorio` (linhas ~80–150): juros de atraso explícitos + proporção contratual na parcela + incorporados.
