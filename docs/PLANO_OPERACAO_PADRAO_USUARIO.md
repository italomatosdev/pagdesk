# Plano de execução — Operação padrão (preferida) do usuário

## 1. Objetivo

Permitir que o usuário marque **uma** operação como **principal** (a que trabalha no dia a dia) entre as operações às quais está vinculado em `operacao_user`. Essa preferência será usada para **pré-selecionar** o valor nos `<select>` de operação em todo o sistema, reduzindo erros (ex.: aprovar ou agir na operação errada quando administra várias).

- **Opcional:** o usuário pode não marcar nenhuma; o comportamento volta ao atual (ex.: primeiro item da lista, ou regra já existente por tela).
- **Público:** faz sentido expor a UI principalmente para quem tem **mais de uma** operação; com uma só, pode omitir ou mostrar estado informativo.

---

## 2. Escopo funcional

| Item | Definição |
|------|-----------|
| **Onde define** | Tela **Minhas Operações** (`profile/operacoes`), acessível pelo menu do usuário. |
| **Persistência** | Tabela dedicada (ex.: `user_operacao_preferida`) — **não** coluna na pivot; opcionalidade = `operacao_id` **NULL** (não apenas `localStorage`). |
| **Cardinalidade** | **No máximo um** registro por `user_id` (preferência única ou “sem preferência”). |
| **Validação** | **Ao gravar:** só aceitar `operacao_id` se existir vínculo em `operacao_user` para o usuário. **Ao ler:** se o ID salvo na tabela de preferência **não** tiver mais vínculo (ex.: admin removeu o usuário da operação), tratar como “sem preferência” — ver seção 5. |
| **Leitura** | Back expõe o ID preferido; Blade/JS aplicam como default nos selects quando não houver `request`, `old()` ou outro contexto mais forte. |

---

## 3. Estado atual no código (referência)

- Pivot `operacao_user` com `role` e timestamps (`User::operacoes()` com `withPivot('role')`) — **permanece só para vínculo e papel**; não recebe campo de preferência.
- Existe `users.operacao_id` e relação `User::operacao()` documentada como **legado** (“operação principal”). A **fonte da verdade** desta feature é a **nova tabela**; em produção o campo legado está **vazio para todos** — ver seção 7.
- Tela **Minhas Operações** hoje só lista cards, sem edição de preferência (`ProfileController::operacoes`).

---

## 4. Banco de dados

1. **Nova tabela** (nome sugerido: `user_operacao_preferida`):
   - `id`
   - `user_id` — **unique**, FK → `users` (on delete cascade)
   - `operacao_id` — **nullable**, FK → `operacoes` (on delete set null ou restrict, conforme política ao excluir operação)
   - `timestamps` opcionais
2. **Semântica:** `operacao_id = NULL` = usuário **sem** operação preferida. Um único upsert por usuário cobre alterações sem tocar na pivot `operacao_user`.
3. **Por tabela separada:** evita acoplar preferência de UI a `attach`/`sync` na pivot e deixa o opcional explícito em uma linha.

---

## 5. Modelo e serviço

1. **Model** `UserOperacaoPreferida` (ou nome alinhado à tabela) com `user_id`, `operacao_id` (nullable).
2. **`User`:** relação `hasOne(UserOperacaoPreferida::class)` (ou nome semântico, ex.: `operacaoPreferida`).
3. **Métodos sugeridos no `User`:**
   - `getOperacaoPrincipalId(): ?int` — lê `operacao_id` na tabela nova e **confirma** que ainda existe linha em `operacao_user` para `(user_id, operacao_id)`. Se não existir (preferência **órfã** ou operação removida do usuário), retornar `null`; opcionalmente **persistir** a correção (`operacao_id` null na tabela de preferência) num único lugar para não repetir o problema.
   - `definirOperacaoPrincipal(?int $operacaoId): void` — se `$operacaoId` for `null`, gravar linha com `operacao_id` null (ou deletar o registro, **uma** política só); se preenchido, **obrigatoriamente** validar vínculo em `operacao_user` (rejeitar request / exceção se inválido) e só então **`updateOrCreate` por `user_id`**.
4. **`Operacao::usuarios()` / pivot:** **sem alteração** de `withPivot` para esta feature.
5. **Helper reutilizável (ex.: classe `OperacaoPreferida` ou método estático):**  
   `resolverIdPreferidoParaLista(Collection|array $idsPermitidos, ?int $requestOperacaoId, ?int $oldInput): ?int`  
   Ordem de precedência sugerida:
   1. Se houver filtro explícito na query string (`request('operacao_id')`) **e** a tela tratar isso como “valor inicial do select”, usar esse valor (se estiver nos permitidos).
   2. `old('operacao_id')` após erro de validação.
   3. `getOperacaoPrincipalId()` **se** estiver contido em `$idsPermitidos`.
   4. `null` para a view cair no fallback atual (`first()`, etc.).

   Ajustar a ordem **por tela** se hoje já existir regra específica (ex.: deep link só com query).

---

## 6. Back: rotas e controller

| Ação | Proposta |
|------|----------|
| **GET** `profile/operacoes` | Carregar operações da pivot + preferência atual (`operacao_id` na tabela nova ou relação `operacaoPreferida`). |
| **POST/PATCH** novo | Ex.: `profile.operacoes.principal` — body: `operacao_id` nullable (ou valor sentinela “nenhuma”). |
| **Policy** | Apenas o próprio usuário autenticado; sem atribuir preferência a terceiros. |

---

## 7. Legado `users.operacao_id`

Em **produção**, hoje **nenhum** usuário tem `users.operacao_id` preenchido. Portanto:

- **Não** é necessária migration de dados do legado para `user_operacao_preferida`.
- **Não** é necessário fallback em `getOperacaoPrincipalId()` para `users.operacao_id` nesta entrega.
- A coluna em `users` pode permanecer no schema; esta feature **não** a usa. Se no futuro alguém preencher o legado por outro fluxo, avaliar migração ou remoção do campo.

---

## 8. Super admin e usuários sem pivot

- Definir regra de produto explícita, por exemplo:
  - **Super admin** sem linhas em `operacao_user`: não exibir bloco “operação principal” **ou** exibir apenas se houver lista de operações filtrada por empresa/escopo atual.
  - Se não for possível listar operações “do usuário” da mesma forma que demais perfis, **não persistir** preferência até existir vínculo na pivot.

Documentar a decisão neste arquivo ao implementar.

---

## 9. Front — Minhas Operações

- Para **2+ operações:** controles claros — radio “Nenhuma” + uma opção por operação, ou botão “Definir como principal” por card (com feedback visual na que está ativa).
- Mensagem curta explicando o efeito (pré-seleção em filtros/formulários).
- Uma submissão por alteração ou formulário único com envio; preferir **uma rota** e mensagem flash de sucesso/erro.

---

## 10. Inventário de telas (select / filtro `operacao_id`)

Mapeamento a partir de `resources/views/**/*.blade.php` com `name="operacao_id"`. Em todas, a ideia é aplicar o **resolver** da seção 5 quando **não** houver query string, `old()`, ou contexto fixo (ex.: ficha já definida).

### 10.1 Regra geral por tipo de tela

| Tipo | Comportamento esperado |
|------|-------------------------|
| **Filtro GET** (listagens, relatórios, dashboards) | Se `request('operacao_id')` ausente, pré-selecionar a operação principal (se permitida na lista da tela). |
| **Formulário POST** (criação/edição) | Se `old('operacao_id')` ausente, default = operação principal; senão primeiro item (regra atual). |
| **Dois selects na mesma página** | Ex.: `caixa/movimentacao/create.blade.php` (`#operacao_id` e `#modal_categoria_operacao_id`) — alinhar ambos ao default inicial e manter sincronismo já existente no JS. |
| **`input type="hidden"`** | Não aplicar preferência no lugar do valor fixo; só onde o hidden repete o filtro escolhido (ex.: `prestacoes/fechamento-caixa`) o select já define o contexto. |

### 10.2 Prioridade v1 (maior risco operacional)

Telas onde erro de operação tem impacto direto em **aprovação**, **caixa**, **liberações** e **filtros** muito usados.

| Área | Arquivo |
|------|---------|
| Aprovações | `aprovacoes/index.blade.php` |
| Caixa | `caixa/index.blade.php` |
| Caixa | `caixa/movimentacao/create.blade.php` |
| Caixa | `caixa/sangria/create.blade.php` |
| Caixa | `caixa/transferencia_operacao/create.blade.php` |
| Caixa — fechamento | `caixa/fechamento/index.blade.php` |
| Liberações (central) | `liberacoes/index.blade.php` |
| Liberações (consultor) | `liberacoes/consultor.blade.php` |
| Liberações — filiais | `liberacoes/pagamentos-produto-objeto.blade.php`, `liberacoes/produtos-objeto-recebidos.blade.php`, `liberacoes/negociacoes.blade.php`, `liberacoes/solicitacoes-juros-contrato-reduzido.blade.php`, `liberacoes/solicitacoes-juros-parcial.blade.php`, `liberacoes/solicitacoes-renovacao-abate.blade.php` |
| Empréstimos | `emprestimos/index.blade.php` |
| Clientes | `clientes/index.blade.php` |
| Dashboards | `dashboard/admin.blade.php`, `dashboard/gestor.blade.php`, `dashboard/consultor.blade.php` |

### 10.3 Prioridade v2 (restante do núcleo operacional)

| Área | Arquivo |
|------|---------|
| Clientes | `clientes/create.blade.php`, `clientes/link-cadastro.blade.php` |
| Empréstimos | `emprestimos/create.blade.php`, `emprestimos/retroativo-pendentes.blade.php` |
| Caixa — categorias | `caixa/categorias/index.blade.php`, `caixa/categorias/create.blade.php`, `caixa/categorias/edit.blade.php` |
| Crédito / cobrança | `parcelas/atrasadas.blade.php`, `cobrancas/index.blade.php`, `garantias/index.blade.php`, `cheques/index.blade.php`, `renovacoes/index.blade.php` |
| Kanban | `kanban/index.blade.php` |
| Prestações / vendas / produtos | `prestacoes/index.blade.php`, `prestacoes/create.blade.php`, `prestacoes/fechamento-caixa.blade.php`, `vendas/index.blade.php`, `vendas/create.blade.php`, `produtos/index.blade.php`, `produtos/create.blade.php`, `produtos/edit.blade.php` |
| Quitação | `quitacao/pendentes.blade.php` |

### 10.4 Relatórios

| Arquivo |
|---------|
| `relatorios/quitacoes.blade.php` |
| `relatorios/juros-quitacoes.blade.php` |
| `relatorios/receber-por-cliente.blade.php` |
| `relatorios/recebimento-juros-dia.blade.php` |
| `relatorios/parcelas-atrasadas.blade.php` |
| `relatorios/comissoes.blade.php` |
| `relatorios/entradas-saidas-categoria.blade.php` |

**Nota:** `relatorios/partials/consultores-operacao-ajax.blade.php` só reage ao `#relatorio-operacao-id`; ao definir o default no select pai, o fluxo AJAX existente continua válido.

### 10.5 Telas especiais (sem aplicar ou aplicar com cuidado)

| Arquivo | Motivo |
|---------|--------|
| `caixa/fechamento/conferir.blade.php` | `operacao_id` vem como **hidden** fixo do contexto do fechamento — não substituir pela preferência. |
| `prestacoes/preview.blade.php` | Hidden com valor já validado no fluxo — não aplicar. |
| `super-admin/sandbox/index.blade.php` | Ferramenta de sandbox; avaliar se a preferência faz sentido ou manter comportamento isolado. |

### 10.6 Fora deste inventário (não é “operação padrão” do usuário)

| Arquivo | Campo / motivo |
|---------|----------------|
| `usuarios/create.blade.php`, `usuarios/show.blade.php` | `operacao_role[...]` — papel por operação ao **vincular** usuário, não preferência de trabalho do usuário logado. |
| `super-admin/empresas/usuarios/create.blade.php` | Idem. |
| `super-admin/usuarios/show.blade.php` | Idem. |

### 10.7 Implementação técnica (resumo)

- Onde hoje se usa `old('operacao_id', $operacoes->first()->id)` ou `request('operacao_id') == ...`, passar a incluir **`$operacaoIdPreferida`** (ou equivalente) do **resolver** da seção 5 na cadeia de fallbacks.
- **JavaScript:** quando o default não vier só do HTML, injetar do Blade (ex.: `data-default-operacao-id` ou variável em `@section('scripts')`), alinhado ao que já existe em telas como `caixa/transferencia_operacao/create.blade.php` (`operacaoIdDefault`).

---

## 11. Testes sugeridos

- Marcar operação A como preferida → registro em `user_operacao_preferida` com `operacao_id = A`.
- Marcar “nenhuma” → `operacao_id` **NULL** no registro (ou registro ausente, conforme política escolhida na seção 5).
- Tentativa de POST com `operacao_id` de operação **não** vinculada ao usuário em `operacao_user` → 422 ou redirect com erro.
- Preferência salva com operação X; ao remover o vínculo do usuário em X (cenário admin ou sync) → `getOperacaoPrincipalId()` deve retornar `null` (e, se implementado, limpar o registro).
- Usuário com uma operação — comportamento da UI (omitir ou informativo).

---

## 12. Ordem de implementação sugerida

1. Migration da tabela `user_operacao_preferida` + model + relação/`getOperacaoPrincipalId` no `User`.
2. Endpoint de gravação + testes de feature.
3. UI **Minhas Operações**.
4. Helper/resolver + ajuste das telas da **seção 10.2 (v1)**.
5. Ampliar para **seções 10.3 e 10.4 (v2)** + atualizar **`docs/CHANGELOG.md`** (ver seção 14).

*(Migração a partir de `users.operacao_id` não consta no escopo: em produção o campo está vazio para todos — seção 7.)*

---

## 13. Fora de escopo (v1)

- Preferência por **tipo de tela** (ex.: principal em caixa e outra em empréstimos).
- Sincronização com app mobile separado além da API/back existente.
- Auditoria granular além do que o Laravel já registra em updates na tabela de preferência (avaliar fase 2).

---

## 14. CHANGELOG (`docs/CHANGELOG.md`)

Mesma regra dos demais planos de funcionalidade: **toda entrega** (v1, v2 ou release único) deve ser **registrada no changelog** do projeto.

| O que incluir | Detalhe |
|----------------|---------|
| **O quê** | Operação **principal/preferida** persistida em **`user_operacao_preferida`** (ou nome final); UI em **Minhas Operações**; uso como default nos selects/filtros (referência às seções 10.2–10.4). |
| **Técnico** | Migration da nova tabela, model, relação no `User`, rota(s) de perfil. (Sem migração a partir de `users.operacao_id` — vazio em produção; seção 7.) |
| **Quando** | Data da entrega; se houver **duas ondas** (v1 depois v2), duas entradas ou uma entrada com subitens claros. |

*Implementado em 2026-03-24 (ver `CHANGELOG.md`).*
