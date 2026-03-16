# Bloqueio de usuário (conta inativa)

## Visão geral

O sistema permite **bloquear** um usuário (deixar a conta **inativa**). Usuário bloqueado não consegue acessar o sistema: ao fazer login ou em qualquer requisição já autenticada, é deslogado e redirecionado para uma página informando que a conta está bloqueada. O bloqueio pode ter um **motivo** opcional, exibido nessa tela.

- **Implementação:** Opção A — um único campo `ativo` (boolean) no banco. Bloquear = `ativo = false`; desbloquear = `ativo = true`.
- **Motivo:** campo opcional `motivo_bloqueio` (texto), exibido na página de conta bloqueada.
- **Nunca deletar:** o registro do usuário permanece no banco; apenas o estado `ativo` e o `motivo_bloqueio` são alterados. Relacionamentos e relatórios históricos são preservados.

---

## Onde configurar

- **Menu:** Super Admin → **Usuários**
- **Listagem:** filtro por **Status** (Todos / Ativos / Bloqueados); coluna **Status** (Ativo / Bloqueado) na tabela.
- **Detalhe:** ao abrir um usuário, no formulário de edição há a seção **Bloquear / Desbloquear conta**:
  - Switch **Usuário ativo** — desmarcar bloqueia a conta.
  - Campo **Motivo do bloqueio (opcional)** — texto exibido para o usuário na tela de conta bloqueada.

Apenas **Super Admin** pode alterar o status da conta de outros usuários.

---

## Comportamento

### Usuário ativo (`ativo = true`)
- Pode fazer login e usar o sistema normalmente.
- Aparece em seleções de consultor/usuário para **novas** criações (ex.: novo empréstimo).
- Aparece em listas, relatórios e em Caixa (movimentação manual, fechamento).

### Usuário bloqueado (`ativo = false`)
- **Login:** após informar usuário e senha corretos, o sistema desloga imediatamente e redireciona para a página **Conta bloqueada** (`/conta-bloqueada`), podendo exibir o motivo do bloqueio.
- **Já logado:** em qualquer requisição seguinte (middleware), o usuário é deslogado e redirecionado para a mesma página. Ou seja, não consegue visualizar nada do sistema.
- **Novas atribuições:** **não** aparece em seletores de “quem criar/atribuir” (ex.: escolher consultor ao criar empréstimo, listas de consultores em parcelas atrasadas). Assim, ninguém consegue atribuir coisas novas a ele.
- **Listas e relatórios:** continua aparecendo (histórico, quem fez o quê, etc.).
- **Caixa / financeiro:** **continua disponível** nas listas de usuários para movimentação manual e fechamento de caixa, para que um gestor/admin possa, por exemplo, fazer uma movimentação em nome dele ou fechar o caixa dele e encerrar o ciclo.

### Fechamento de caixa (prestação de contas) — consultor bloqueado

Quando um fechamento de caixa está em status **Aprovado** e o consultor (dono do caixa) está **bloqueado**, ele não consegue acessar o sistema para anexar o comprovante de envio. Nesse caso:

- O **gestor ou administrador** da operação pode **marcar como pago** diretamente na tela do fechamento.
- Aparece o botão **"Marcar como pago (consultor bloqueado)"** apenas quando: status do fechamento é **Aprovado**, o consultor está **bloqueado** e o usuário logado é gestor ou administrador na operação.
- Ao marcar como pago, o sistema atualiza o fechamento para **Concluído** e gera as movimentações de caixa (saída do consultor, entrada do gestor), **sem exigir comprovante**. O ciclo do caixa do consultor bloqueado é encerrado.

**Rota:** `POST /fechamento-caixa/{id}/marcar-pago-consultor-bloqueado` (nome: `fechamento-caixa.marcar-pago-consultor-bloqueado`).  
**Service:** `SettlementService::marcarComoPagoConsultorBloqueado()`.

---

## Página de conta bloqueada

- **URL:** `/conta-bloqueada`
- **Rota:** `conta.bloqueada`
- **View:** `resources/views/pages-conta-bloqueada.blade.php`

Exibe a mensagem “Sua conta está bloqueada” e, se houver, o **motivo** informado pelo Super Admin. O usuário não tem acesso ao restante do sistema a partir daí.

---

## Banco de dados

- **Tabela:** `users`
- **Campos:**
  - `ativo` (boolean, default `true`) — `false` = conta bloqueada.
  - `motivo_bloqueio` (string 500, nullable) — motivo opcional do bloqueio.

**Migration:** `database/migrations/2026_03_16_160840_add_ativo_and_motivo_bloqueio_to_users_table.php`

**Produção:** ao rodar a migration em produção, a coluna `ativo` é criada com **default `true`**. Todos os usuários **já existentes** recebem `ativo = true`, ou seja, permanecem ativos. Nenhum usuário é bloqueado apenas pelo deploy.

---

## Arquivos envolvidos

| Função | Arquivo |
|--------|---------|
| Migration | `database/migrations/2026_03_16_160840_add_ativo_and_motivo_bloqueio_to_users_table.php` |
| Model | `app/Models/User.php` — `ativo`, `motivo_bloqueio`, `isAtivo()`, `isBloqueado()` |
| Middleware (desloga se inativo) | `app/Http/Middleware/CheckUsuarioAtivo.php` |
| Login (redireciona se inativo) | `app/Http/Controllers/Auth/LoginController.php` — método `authenticated()` |
| Página conta bloqueada | Rota em `routes/web.php`; view `resources/views/pages-conta-bloqueada.blade.php` |
| Super Admin – usuários | `app/Http/Controllers/SuperAdmin/UsuarioController.php`; views em `resources/views/super-admin/usuarios/` |
| Registro do middleware | `app/Http/Kernel.php` — grupo `web` |
| Selectors só ativos (criações) | `app/Modules/Loans/Controllers/EmprestimoController.php`, `ParcelaController.php` (consultores) |
| Caixa (inclui bloqueados) | Listas de usuários em `app/Modules/Cash/Controllers/` **não** filtram por `ativo` |
| Fechamento: marcar como pago (consultor bloqueado) | `app/Modules/Cash/Services/SettlementService.php` — `marcarComoPagoConsultorBloqueado()`; `FechamentoCaixaController::marcarComoPagoConsultorBloqueado()`; view `caixa/fechamento/show.blade.php` |

---

## Rotas

| Método | URI | Nome | Descrição |
|--------|-----|------|-----------|
| GET | `/conta-bloqueada` | `conta.bloqueada` | Página exibida quando o usuário está com conta bloqueada (após login ou após middleware). |

---

## Segurança

- Apenas **Super Admin** acessa a área de usuários e pode alterar `ativo` e `motivo_bloqueio`.
- Usuário bloqueado não consegue permanecer autenticado: o middleware `CheckUsuarioAtivo` desloga e redireciona em toda requisição web (exceto login, logout e a própria página conta-bloqueada).

---

## Resumo rápido

| Ação | Efeito |
|------|--------|
| Bloquear (Super Admin) | Desmarca “Usuário ativo”, opcionalmente preenche motivo. Usuário deixa de acessar o sistema e não aparece em seleções de novas atribuições; continua em listas, relatórios e em Caixa. |
| Desbloquear | Marca “Usuário ativo” e salva. Usuário volta a poder logar e a aparecer em seleções. |
| Fechamento com consultor bloqueado | Gestor/admin pode marcar o fechamento (status Aprovado) como pago na tela do fechamento, sem comprovante; movimentações de caixa são geradas e o ciclo é encerrado. |
| Deploy em produção | Migration com `ativo` default `true` → todos os usuários existentes permanecem ativos. |
