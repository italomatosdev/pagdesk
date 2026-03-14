# Empréstimo retroativo

Este documento descreve a funcionalidade de **empréstimo retroativo**: cadastro de empréstimos já existentes (com data no passado), opção de o consultor criar com aceite do gestor/administrador, e o registro de parcelas já pagas com opção de gerar ou não caixa.

Para o fluxo geral de **criação de empréstimo** (normal e retroativo) e a escolha do **consultor responsável**, veja também **[CRIACAO_EMPRESTIMO.md](CRIACAO_EMPRESTIMO.md)**.

---

## 1. Visão geral

O empréstimo retroativo serve para **lançar no sistema empréstimos que já existem na prática** (contrato e parcelas já em andamento). Permite:

- **Data de início no passado**
- **Vincular a um consultor** (gestor/admin escolhem no select; consultor que cria fica como responsável)
- **Registrar parcelas já pagas** na tela do empréstimo, com opção de **gerar movimentação de caixa** ou não

Dois fluxos de criação:

| Quem cria              | Comportamento                                                                 |
|------------------------|-------------------------------------------------------------------------------|
| **Gestor ou Administrador** | Empréstimo criado já **ativo**. Escolhem o **consultor responsável** (lista inclui "Nome (Você)" ao final). |
| **Consultor**           | Empréstimo criado em **aguardando aceite**. O responsável é o próprio usuário. Só fica ativo após aprovação em **Liberações**. |

---

## 2. Configuração por operação

Cada operação define se permite ou não empréstimo retroativo.

1. Acesse **Operações** → edite a operação desejada.
2. Marque **"Permitir empréstimo retroativo"**.
3. Salve.

Enquanto essa opção não estiver marcada, o bloco de empréstimo retroativo **não aparece** na tela de novo empréstimo para essa operação.

---

## 3. Criar empréstimo retroativo

### 3.1 Onde

**Empréstimos** → **Novo Empréstimo**. O bloco **"Empréstimo retroativo"** só é exibido quando:

- A **operação** selecionada tem "Permitir empréstimo retroativo" ativa.
- O usuário é **gestor**, **administrador** ou **consultor**.

### 3.2 Gestor ou administrador

1. Selecione a operação (com retroativo permitido).
2. Marque **"Empréstimo retroativo"**.
3. Escolha o **Consultor responsável** no select. A lista mostra os consultores da operação e, **sempre ao final**, a opção **"Nome (Você)"** para vincular o empréstimo a si mesmo, se desejar.
4. Preencha os demais campos (cliente, valor, parcelas, **data de início** pode ser no passado, tipo etc.).
5. Salve.

O empréstimo é criado com status **ativo**. Não há aprovação.

### 3.3 Consultor

1. Selecione a operação (com retroativo permitido).
2. Marque **"Empréstimo retroativo"**.
3. **Não** há select de consultor: o responsável é o próprio usuário.
4. Preencha os demais campos (cliente, valor, parcelas, data no passado, tipo etc.).
5. Salve.

O empréstimo é criado com status **Aguardando aceite (retroativo)**. Gestores e administradores recebem **notificação** e devem aprovar em **Liberações**.

---

## 4. Aceite (gestor/administrador)

Quando um **consultor** cria um empréstimo retroativo, o aceite é feito na área de **Liberações**.

### 4.1 Onde acessar

- **Menu** → **Liberações** (badge com quantidade de retroativos pendentes, se houver).
- Na página de Liberações, botão **"Empréstimos retroativos"** (com badge de pendentes).
- Ou pelo link da **notificação** ("Empréstimo retroativo aguardando aceite"), que leva direto à lista de pendentes.

### 4.2 Lista de pendentes

- Lista solicitações com: Empréstimo, Cliente, Operação, Solicitante, Valor, Data.
- Ações por linha: **Ver**, **Aprovar**, **Rejeitar**.
- **Seleção múltipla**: marque várias solicitações e use **"Aprovar selecionados"** para aprovar em lote.

### 4.3 Aprovar

- **Aprovar** (uma ou em lote): a solicitação passa a "aprovado", o empréstimo passa para status **ativo**.
- A partir daí o empréstimo se comporta como qualquer retroativo ativo (incluindo "Registrar parcelas já pagas").

### 4.4 Rejeitar

- **Rejeitar**: é obrigatório informar **motivo** (mín. 5 caracteres). O empréstimo é alterado para status **cancelado**.

---

## 5. Registrar parcelas já pagas

Serve para marcar, em um empréstimo **retroativo já ativo**, parcelas que o cliente **já pagou** antes do sistema.

### 5.1 Onde aparece

Na **tela do empréstimo** (detalhe), em um card **"Registrar parcelas já pagas"**, desde que:

- O empréstimo seja **retroativo** (`is_retroativo`).
- O empréstimo **não** esteja aguardando aceite (status ativo).
- Exista **pelo menos uma parcela não paga** com **vencimento já passado** (ou vencendo hoje).
- O usuário seja **gestor**, **administrador** ou o **consultor responsável** do empréstimo.

### 5.2 O que fazer

1. Escolha **"Gerar caixa para parcelas já pagas?"**: **Sim** ou **Não**.
2. Para cada parcela que já foi paga:
   - Marque **"Marcar como já paga"**.
   - Preencha a **Data do pagamento**.
3. Clique em **"Registrar parcelas selecionadas"**.
4. Confirme no **Sweet Alert** (mensagem lembra se vai ou não gerar caixa).
5. As parcelas são atualizadas (valor pago, data, status paga) e, se **Sim**, são criados **Pagamento** e **movimentação de caixa** para cada uma.

### 5.3 Regras

- Só entram na lista parcelas cujo **vencimento já passou** (ou é hoje). Parcelas com vencimento futuro não aparecem para marcar como já pagas.
- **Gerar caixa = Sim**: cria registro em `pagamentos` e movimentação de entrada no caixa (via `CashService::registrarMovimentacao`), com a mesma lógica usada em pagamentos normais.
- **Gerar caixa = Não**: apenas atualiza a parcela (valor_pago, data_pagamento, status paga), sem pagamento nem caixa.
- Se todas as parcelas ficarem pagas, o sistema pode finalizar o empréstimo automaticamente (conforme regra de negócio do módulo).

---

## 6. Notificações

- **Tipo:** `emprestimo_retroativo_aguardando_aceite`
- **Quando:** consultor cria um empréstimo retroativo.
- **Quem recebe:** todos os usuários com papel **gestor** e **administrador**.
- **Link:** abre a **lista de empréstimos retroativos pendentes** (Liberações → Empréstimos retroativos).

---

## 7. Referência técnica (resumida)

### 7.1 Migrations

| Migration | Descrição |
|-----------|-----------|
| `2026_03_24_100000_add_permite_emprestimo_retroativo_to_operacoes_table` | Coluna `permite_emprestimo_retroativo` (boolean) em `operacoes` |
| `2026_03_24_100001_add_is_retroativo_to_emprestimos_table` | Coluna `is_retroativo` (boolean) em `emprestimos` |
| `2026_03_24_150000_create_solicitacoes_emprestimo_retroativo_table` | Tabela `solicitacoes_emprestimo_retroativo` (emprestimo_id, solicitante_id, status, aprovado_por, aprovado_em, motivo_rejeicao, empresa_id) |
| `2026_03_24_150001_add_aguardando_aceite_retroativo_to_emprestimos_status` | Novo valor no enum `status` de `emprestimos`: `aguardando_aceite_retroativo` |
| `2026_03_25_120003_add_emprestimo_retroativo_aguardando_aceite_to_notificacoes_tipo_enum` | Novo tipo em `notificacoes.tipo`: `emprestimo_retroativo_aguardando_aceite` |

### 7.2 Modelos

- **Operacao:** `permite_emprestimo_retroativo` (fillable, cast boolean).
- **Emprestimo:** `is_retroativo` (fillable, cast boolean); `solicitacaoRetroativo()` (hasOne SolicitacaoEmprestimoRetroativo); `isAguardandoAceiteRetroativo()`.
- **SolicitacaoEmprestimoRetroativo:** tabela `solicitacoes_emprestimo_retroativo`; status `aguardando` | `aprovado` | `rejeitado`; relacionamentos `emprestimo`, `solicitante`, `aprovador`; EmpresaScope.

### 7.3 Rotas (web)

| Método | Rota | Nome | Descrição |
|--------|------|------|-----------|
| GET | `/emprestimos-retroativo/pendentes` | `emprestimos.retroativo.pendentes` | Lista de retroativos aguardando aceite |
| POST | `/emprestimos-retroativo/pendentes/aprovar-lote` | `emprestimos.retroativo.aprovar-lote` | Aprovar múltiplas solicitações |
| POST | `/emprestimos-retroativo/pendentes/{id}/aprovar` | `emprestimos.retroativo.aprovar` | Aprovar uma solicitação |
| POST | `/emprestimos-retroativo/pendentes/{id}/rejeitar` | `emprestimos.retroativo.rejeitar` | Rejeitar uma solicitação (motivo obrigatório) |
| POST | `/emprestimos/{id}/parcelas-retroativo` | `emprestimos.parcelas-retroativo` | Registrar parcelas já pagas (payload JSON em `parcelas`, `gerar_caixa_global`) |

### 7.4 Controllers / Services

- **EmprestimoController:** `store` (retroativo gestor vs consultor), `indexPendentesRetroativo`, `aprovarRetroativo`, `aprovarRetroativoLote`, `rejeitarRetroativo`, `registrarParcelasPagasRetroativo`.
- **EmprestimoService:** ao criar, se `is_retroativo` e `solicitar_aceite_retroativo` (consultor), status `aguardando_aceite_retroativo` e criação de `SolicitacaoEmprestimoRetroativo` + notificação para gestor e administrador.
- **PagamentoService:** `verificarFinalizacaoEmprestimo` é **público** para ser chamado após registrar parcelas pagas no retroativo.

### 7.5 Views principais

- **emprestimos/create.blade.php:** bloco retroativo (todos); select **consultor responsável** sempre visível para gestor e administrador (normal e retroativo); lista = consultores da operação + **"Nome (Você)"** ao final; texto diferente para consultor.
- **emprestimos/show.blade.php:** alerta "aguardando aceite"; card "Registrar parcelas já pagas" (com tabela, checkbox, data, gerar caixa, Sweet confirm no submit).
- **emprestimos/retroativo-pendentes.blade.php:** lista com checkbox, "Aprovar selecionados", Aprovar/Rejeitar por linha, modal rejeitar; "Voltar a Liberações".
- **liberacoes/index.blade.php:** botão "Empréstimos retroativos" e contagem de pendentes.
- **layouts/sidebar.blade.php:** badge de retroativos pendentes no item Liberações (gestor/admin).

---

## 8. Fluxo resumido

```
[Operação: Permitir empréstimo retroativo = Sim]

Gestor/Admin cria retroativo
  → Empréstimo ativo, consultor escolhido
  → Pode usar "Registrar parcelas já pagas" na tela do empréstimo

Consultor cria retroativo
  → Empréstimo "Aguardando aceite"
  → Notificação para gestor/admin
  → Liberações → Empréstimos retroativos → Aprovar (uma ou lote) ou Rejeitar
  → Aprovado → Empréstimo ativo → "Registrar parcelas já pagas" liberado
  → Rejeitado → Empréstimo cancelado
```

---

*Documento referente à implementação de empréstimo retroativo (aceite para consultor, liberações, aprovar em lote, registrar parcelas já pagas com opção de gerar caixa e Sweet confirm).*
