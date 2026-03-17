# Vencimento mensal e exceção em fevereiro

Este documento descreve a regra de **data de vencimento** para empréstimos com frequência **mensal** (mesmo dia do mês) e a **exceção em fevereiro** no empréstimo retroativo.

---

## 1. Regra geral: mesmo dia do mês

Para frequência **mensal**, a data de vencimento de cada parcela segue o **mesmo dia do mês** em relação à referência (data de início ou parcela anterior), com ajustes para meses que não têm 31 ou 30 dias:

| Situação | Resultado |
|----------|-----------|
| Próximo mês tem 31 dias | Pode usar dia 31 (ex.: 31/03 → 31/04). |
| Próximo mês tem 30 dias | Usa no máximo dia 30 (ex.: 31/03 → 30/04). |
| Próximo mês é fevereiro | Dia 30 ou 31 não existe em fev → vencimento vai para **01/03**. |
| Dia 28 ou 29 em fevereiro | Mantém 28/02 ou 29/02 no ano; no mês seguinte usa o mesmo dia (ex.: 28/02 → 28/03) ou 28/29 conforme o mês. |

**Exemplos:**

- 30/01 → 1ª parcela **01/03** (fev não tem 30).
- 31/03 → próxima parcela **30/04** (abril tem 30 dias).
- 28/02 → próxima parcela **28/03** (ou 29/03 em ano bissexto, conforme regra de 29/02).
- 15/03 → 15/04 → 15/05.

A mesma lógica vale para **criação** de empréstimo, **renovação** e **simulação** na tela de novo empréstimo.

---

## 2. Exceção em fevereiro (retroativo, 1 parcela)

Quando o empréstimo é **retroativo**, **mensal**, com **1 parcela** e a **data de início é em fevereiro** (28/02 ou 29/02), o sistema oferece uma opção para que a **1ª parcela vença no dia 30/03** em vez de 28/03 ou 29/03.

### 2.1 Onde aparece na tela

Na tela **Novo Empréstimo**:

1. Marque **Empréstimo retroativo**.
2. Selecione **Frequência: Mensal**.
3. Selecione uma **data de início em fevereiro** (ex.: 28/02 ou 29/02).

Surge o texto:

- **"Fevereiro: com 1 parcela mensal, use 28/02 ou 29/02 e marque a opção abaixo para 1ª parcela em 30/03."**

Se além disso o número de parcelas for **1**, aparece também o **checkbox**:

- **"Quero que a 1ª parcela vença no dia 30 (30/03)"**

Ou seja: o **aviso** aparece sempre que houver retroativo + mensal + data em fevereiro; o **checkbox** só quando for **1 parcela**.

### 2.2 Como usar

1. Em **Empréstimo retroativo**, escolha **Mensal** e **1 parcela**.
2. Em **Data de início**, selecione **28/02** ou **29/02** (conforme o ano).
3. Marque **"Quero que a 1ª parcela vença no dia 30 (30/03)"**.
4. Preencha o restante e salve.

A 1ª parcela será gerada com vencimento em **30/03**. O backend grava a data de início normalmente (28/02 ou 29/02) e aplica a opção apenas na geração da parcela (sem novo campo no banco).

### 2.3 Simulação

Na mesma tela, ao clicar em **Simular**, se o checkbox estiver marcado e a data de início for em fevereiro, a **simulação** também mostra a 1ª parcela em **30/03**, alinhada ao que será gerado ao salvar.

---

## 3. Resumo

| Contexto | Comportamento |
|----------|----------------|
| **Mensal, qualquer mês** | Vencimento = mesmo dia do mês (com regras 31/30 e fev → 01/03). |
| **Mensal, data em fev, retroativo** | Aviso na tela explicando a exceção. |
| **Mensal, 1 parcela, data em fev, retroativo** | Checkbox "1ª parcela em 30/03"; ao marcar, 1ª parcela = 30/03. Simulação segue o checkbox. |

Para a **renovação** de empréstimos mensais (novo vencimento após pagar juros), a mesma regra de “mesmo dia do mês” é usada; a exceção do checkbox aplica-se apenas à **criação** (e simulação) de empréstimo retroativo com 1 parcela e data em fevereiro.
