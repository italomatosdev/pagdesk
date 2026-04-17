# Adiantamento de valor (empréstimo com 1 parcela)

Este documento descreve o fluxo **Adiantamento de valor**, usado quando o cliente quer **adiantar** parte do que deve **antes do vencimento**, sem gerar novo empréstimo e sem renegociar contrato.

## Objetivo

Permitir registrar um **pagamento parcial** em um empréstimo de **1 parcela**, abatendo o saldo nominal que falta na **mesma parcela**.

Exemplo:

- Parcela: R$ 780,00
- Valor já pago: R$ 0,00
- Cliente adianta: R$ 200,00
- Resultado:
  - Valor pago: R$ 200,00
  - Falta pagar (parcela): R$ 580,00
  - Status: permanece **pendente** até quitar

## Quando aparece (elegibilidade)

O bloco **Adiantamento de valor** aparece na tela do empréstimo apenas se:

- Empréstimo está **ativo**
- Empréstimo tem **1 parcela**
- Parcela **não está quitada**
- Parcela **não está atrasada**
- Parcela tem saldo nominal em aberto (`faltaPagar() > 0`)

## Como registrar

Na tela do empréstimo (show), no bloco **Adiantamento de valor**, clique em **Registrar adiantamento**.

Isso abre a tela de pagamento em modo adiantamento (`pagamentos.create?adiantamento=1`), onde:

- O campo **Valor do pagamento** fica **editável**
- O valor sugerido é o **Falta pagar (parcela)**
- O sistema valida que o valor informado é:
  - maior que zero
  - menor ou igual ao **falta pagar (parcela)**

## Efeito no contrato/parcela

O adiantamento:

- **não altera** o valor nominal da parcela (`parcela.valor`)
- **incrementa** `parcela.valor_pago`
- mantém a parcela como **pendente** até que `valor_pago >= valor`
- gera **entrada de caixa** normalmente (com descrição indicando adiantamento)

## Pagamento tradicional após adiantamento

Depois de um adiantamento, ao abrir o **Registrar pagamento** tradicional para a mesma parcela:

- O campo **Falta pagar (parcela)** mostra o saldo restante
- O input **Valor do pagamento** deve vir preenchido com o **valor que falta** (para evitar pagamento acima do devido)

## Regras importantes (v1)

- O adiantamento é apenas para parcela **em dia** (não atrasada).
- Não permite pagamento em **produto/objeto** neste modo (v1).
- Não faz recálculo de juros/principal do contrato; trata-se de abatimento nominal do saldo da parcela.

